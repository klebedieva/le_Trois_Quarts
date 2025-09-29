<?php

namespace App\Controller\Admin;

use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ArrayField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use App\Repository\AllergenRepository;

#[IsGranted('ROLE_ADMIN')]
class MenuItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return MenuItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Plat')
            ->setEntityLabelInPlural('Carte - Plats')
            ->setPageTitle('index', 'Gestion de la carte')
            ->setPageTitle('edit', 'Modifier le plat')
            ->setPageTitle('new', 'Nouveau plat')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20);
    }

    public function configureFields(string $pageName): iterable
    {
        $imageField = ImageField::new('image', 'Image')
            ->setBasePath('/uploads/menu')
            ->setUploadDir('public/uploads/menu')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setHelp('Téléversez une image; le fichier sera copié dans /public/uploads/menu');

        // On edit, keep existing image and make upload optional
        if ($pageName === Crud::PAGE_EDIT) {
            $imageField = $imageField->setRequired(false);
        }

        return [
            IdField::new('id')->hideOnForm()->hideOnIndex(),
            TextField::new('name', 'Nom')->setRequired(true),
            TextareaField::new('description', 'Description')->setNumOfRows(4)->hideOnIndex(),
            MoneyField::new('price', 'Prix')->setCurrency('EUR')->setStoredAsCents(false),
            $imageField,
            ChoiceField::new('category', 'Catégorie')
                ->setChoices([
                    'Entrées' => 'entrees',
                    'Plats' => 'plats',
                    'Desserts' => 'desserts',
                ]),
            TextareaField::new('ingredients', 'Ingrédients')
                ->hideOnIndex()
                ->setHelp('Liste d\'ingrédients. Saisissez un par ligne (recommandé) ou un tableau JSON ["item1","item2"].')
                ->formatValue(function ($value, $entity) {
                    if (!$value) return '';
                    
                    // Если это JSON, показываем как построчный список для удобства редактирования
                    $decoded = json_decode($value, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        return implode("\n", $decoded);
                    }
                    
                    return $value;
                })
                ->setFormTypeOptions([
                    'attr' => ['rows' => 6]
                ]),
            TextareaField::new('preparation', 'Préparation')->hideOnIndex(),
            IntegerField::new('prepTimeMin', 'Temps de préparation min (min)')
                ->hideOnIndex()
                ->setHelp('Temps minimum de préparation en minutes'),
            IntegerField::new('prepTimeMax', 'Temps de préparation max (min)')
                ->hideOnIndex()
                ->setHelp('Temps maximum de préparation en minutes. Si rempli avec min, affichera "15 - 20 minutes"'),
            TextareaField::new('chefTip', 'Conseil du chef')->hideOnIndex(),
            AssociationField::new('allergens', 'Allergènes')
                ->setFormTypeOptions([
                    'by_reference' => false,
                    'choice_label' => 'name',
                    'multiple' => true,
                ])
                ->setQueryBuilder(fn (\Doctrine\ORM\QueryBuilder $qb) => $qb->orderBy('entity.name', 'ASC'))
                ->setHelp('Sélectionnez un ou plusieurs allergènes dans la liste prédéfinie (issue du projet Restaurant).')
                ->hideOnIndex(),
            AssociationField::new('badges', 'Badges')
                ->setFormTypeOptions(['by_reference' => false])
                ->hideOnIndex()
                ->formatValue(function ($value, $entity) {
                    if (!$entity || !method_exists($entity, 'getBadges')) { return ''; }
                    $names = [];
                    foreach ($entity->getBadges() as $badge) {
                        $names[] = method_exists($badge, 'getName') ? $badge->getName() : (string) $badge;
                    }
                    return implode(', ', $names);
                }),
            AssociationField::new('tags', 'Tags')
                ->setFormTypeOptions(['by_reference' => false])
                ->hideOnIndex()
                ->formatValue(function ($value, $entity) {
                    if (!$entity || !method_exists($entity, 'getTags')) { return ''; }
                    $names = [];
                    foreach ($entity->getTags() as $tag) {
                        // показываем code или name, если есть
                        if (method_exists($tag, 'getName') && $tag->getName()) {
                            $names[] = $tag->getName();
                        } else {
                            $names[] = method_exists($tag, 'getCode') ? $tag->getCode() : (string) $tag;
                        }
                    }
                    return implode(', ', $names);
                }),
            // Nutrition (embedded)
            IntegerField::new('nutrition.caloriesKcal', 'Calories (kcal)')->hideOnIndex(),
            TextField::new('nutrition.proteinsG', 'Protéines (g)')->hideOnIndex(),
            TextField::new('nutrition.carbsG', 'Glucides (g)')->hideOnIndex(),
            TextField::new('nutrition.fatsG', 'Lipides (g)')->hideOnIndex(),
            TextField::new('nutrition.fiberG', 'Fibres (g)')->hideOnIndex(),
            IntegerField::new('nutrition.sodiumMg', 'Sodium (mg)')->hideOnIndex(),
            DateTimeField::new('createdAt', 'Créé le')->hideOnForm(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function(Action $action){
                return $action->setCssClass('action-delete btn btn-soft-danger btn-sm');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function(Action $action){
                return $action->setCssClass('btn btn-soft-success btn-sm');
            })
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function(Action $action){
                return $action->setCssClass('btn btn-soft-info btn-sm');
            })
            ->setPermission(Action::DELETE, 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Nom'))
            ->add(TextFilter::new('category', 'Catégorie'))
            ->add(NumericFilter::new('price', 'Prix'))
            ->add(BooleanFilter::new('active', 'Actif'));
    }
}


