<?php

namespace App\Controller\Admin;

use App\Entity\GalleryImage;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_ADMIN')]
class GalleryImageCrudController extends AbstractCrudController
{
    private EntityManagerInterface $entityManager;

    public function __construct(EntityManagerInterface $entityManager)
    {
        $this->entityManager = $entityManager;
    }

    public static function getEntityFqcn(): string
    {
        return GalleryImage::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Image de galerie')
            ->setEntityLabelInPlural('Images de galerie')
            ->setPageTitle('index', 'Gestion de la galerie')
            ->setPageTitle('detail', 'Détails de l\'image')
            ->setPageTitle('edit', 'Modifier l\'image')
            ->setPageTitle('new', 'Nouvelle image')
            ->setDefaultSort(['displayOrder' => 'ASC', 'createdAt' => 'DESC'])
            ->setPaginatorPageSize(30)
            ->setFormThemes(['@EasyAdmin/crud/form_theme.html.twig']);
    }

    public function configureFields(string $pageName): iterable
    {
        $imageBasePath = 'assets/img/';
        $imageUploadPath = 'public/assets/img/';

        return [
            IdField::new('id')
                ->hideOnForm()
                ->hideOnIndex(),
            
            TextField::new('title', 'Titre')
                ->setRequired(true)
                ->setHelp('Titre de l\'image (affiché sur la galerie)')
                ->setColumns(6),
            
            ChoiceField::new('category', 'Catégorie')
                ->setRequired(true)
                ->setChoices([
                    'Terrasse' => 'terrasse',
                    'Intérieur' => 'interieur',
                    'Plats' => 'plats',
                    'Ambiance' => 'ambiance',
                ])
                ->setHelp('Catégorie pour le filtrage')
                ->setColumns(6),
            
            TextareaField::new('description', 'Description')
                ->setRequired(true)
                ->setHelp('Description de l\'image (affichée au survol)')
                ->setMaxLength(500)
                ->setNumOfRows(3)
                ->formatValue(function ($value, $entity) use ($pageName) {
                    if (!$value) {
                        return '';
                    }
                    // Only truncate on index page
                    if ($pageName === Crud::PAGE_INDEX) {
                        $maxLength = 80;
                        if (mb_strlen($value) > $maxLength) {
                            return mb_substr($value, 0, $maxLength) . '...';
                        }
                    }
                    return $value;
                }),
            
            ImageField::new('imagePath', 'Image')
                ->setBasePath($imageBasePath)
                ->setUploadDir($imageUploadPath)
                ->setRequired(true)
                ->setHelp('Nom du fichier (ex: terrasse_1.jpg). Le fichier doit être dans public/assets/img/')
                ->setFormTypeOptions([
                    'upload_new' => function ($file) {
                        return $file->getClientOriginalName();
                    }
                ])
                ->setColumns(12),
            
            IntegerField::new('displayOrder', 'Ordre d\'affichage')
                ->setRequired(true)
                ->setHelp('Ordre d\'affichage (plus petit = affiché en premier)')
                ->setFormTypeOption('attr', ['min' => 0])
                ->setColumns(6),
            
            BooleanField::new('isActive', 'Active')
                ->setHelp('Cochez pour afficher l\'image sur le site')
                ->setColumns(6),
            
            DateTimeField::new('createdAt', 'Date de création')
                ->hideOnForm()
                ->setFormat('dd/MM/yyyy HH:mm')
                ->setHelp('Date et heure de création'),
            
            DateTimeField::new('updatedAt', 'Date de modification')
                ->hideOnForm()
                ->hideOnIndex()
                ->setFormat('dd/MM/yyyy HH:mm')
                ->setHelp('Date et heure de dernière modification'),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function (Action $action) {
                return $action
                    ->setIcon('fa fa-eye')
                    ->setLabel('Voir')
                    ->setCssClass('btn btn-soft-info btn-sm');
            })
            ->update(Crud::PAGE_INDEX, Action::NEW, function (Action $action) {
                return $action
                    ->setIcon('fa fa-plus')
                    ->setLabel('Ajouter une image')
                    ->setCssClass('btn btn-success');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function (Action $action) {
                return $action
                    ->setIcon('fa fa-edit')
                    ->setLabel('Modifier')
                    ->setCssClass('btn btn-soft-success btn-sm');
            })
            ->update(Crud::PAGE_INDEX, Action::DELETE, function (Action $action) {
                return $action
                    ->setIcon('fa fa-trash')
                    ->setLabel('Supprimer')
                    ->setCssClass('action-delete btn btn-soft-danger btn-sm');
            })
            ->setPermission(Action::DELETE, 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('category', 'Catégorie')
                ->setChoices([
                    'Terrasse' => 'terrasse',
                    'Intérieur' => 'interieur',
                    'Plats' => 'plats',
                    'Ambiance' => 'ambiance',
                ]))
            ->add(BooleanFilter::new('isActive', 'Active'))
            ->add(DateTimeFilter::new('createdAt', 'Date de création'));
    }

    /**
     * Update the updatedAt timestamp when editing
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof GalleryImage) {
            $entityInstance->setUpdatedAt(new \DateTime());
        }
        parent::updateEntity($entityManager, $entityInstance);
    }
}

