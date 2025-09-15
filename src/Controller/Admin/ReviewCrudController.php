<?php

namespace App\Controller\Admin;

use App\Entity\Review;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class ReviewCrudController extends AbstractCrudController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return Review::class;
    }



    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Avis')
            ->setEntityLabelInPlural('Avis')
            ->setPageTitle('index', 'Gestion des avis')
            ->setPageTitle('detail', 'Détails de l\'avis')
            ->setPageTitle('edit', 'Modifier l\'avis')
            ->setPageTitle('new', 'Nouvel avis')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->hideOnForm()
                ->hideOnIndex(),
            
            TextField::new('name', 'Nom')
                ->setRequired(true)
                ->setHelp('Nom de la personne qui a laissé l\'avis'),
            
            EmailField::new('email', 'Email')
                ->setRequired(false)
                ->setHelp('Email de contact (optionnel)'),
            
            IntegerField::new('rating', 'Note')
                ->setRequired(true)
                ->setHelp('Note de 1 à 5 étoiles')
                ->setFormTypeOption('attr', ['min' => 1, 'max' => 5]),
            
            TextareaField::new('comment', 'Commentaire')
                ->setRequired(true)
                ->setHelp('Commentaire de l\'avis')
                ->setMaxLength(1000)
                ->setNumOfRows(4)
                ->formatValue(function ($value, $entity) use ($pageName) {
                    if (!$value) {
                        return '';
                    }
                    $text = nl2br(htmlspecialchars($value)); // preserve line breaks and escape HTML
                    // Only truncate on index page
                    if ($pageName === Crud::PAGE_INDEX) {
                        $maxLength = 120; // character count for comment
                        if (mb_strlen($value) > $maxLength) {
                            return mb_substr($text, 0, $maxLength) . '...';
                        }
                    }
                    return $text;
                })
                ->renderAsHtml(), // enable HTML rendering for <br> tags
            
            BooleanField::new('isApproved', 'Approuvé')
                ->setHelp('Cochez pour approuver l\'avis et le rendre visible sur le site'),
            
            DateTimeField::new('createdAt', 'Date de création')
                ->hideOnForm()
                ->setFormat('dd/MM/yyyy HH:mm')
                ->setHelp('Date et heure de soumission de l\'avis'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->remove(Crud::PAGE_INDEX, Action::NEW)
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action
                    ->setIcon('fa fa-edit')
                    ->setLabel('Modifier')
                    ->displayIf(function ($entity) {
                        return $entity instanceof Review && $entity->getId() !== null;
                    });
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action
                    ->setIcon('fa fa-trash')
                    ->setLabel('Supprimer')
                    ->displayIf(function ($entity) {
                        return $entity instanceof Review && $entity->getId() !== null;
                    });
            });
    }


    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Nom'))
            ->add(TextFilter::new('email', 'Email'))
            ->add(NumericFilter::new('rating', 'Note'))
            ->add(BooleanFilter::new('isApproved', 'Approuvé'))
            ->add(DateTimeFilter::new('createdAt', 'Date de création'));
    }
}
