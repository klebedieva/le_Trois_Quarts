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
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[IsGranted('ROLE_ADMIN')]
class GalleryImageCrudController extends AbstractCrudController
{
    private EntityManagerInterface $entityManager;
    private ValidatorInterface $validator;

    public function __construct(EntityManagerInterface $entityManager, ValidatorInterface $validator)
    {
        $this->entityManager = $entityManager;
        $this->validator = $validator;
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
        $imageBasePath = '/assets/img/';
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
                ->setRequired($pageName === Crud::PAGE_NEW)
                ->setHelp('Nom du fichier (ex: terrasse_1.jpg). Le fichier doit être dans public/assets/img/')
                ->setUploadedFileNamePattern('[randomhash].[extension]')
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
            
            // If imagePath is empty, keep the existing value
            if (empty($entityInstance->getImagePath())) {
                $originalEntity = $entityManager->getRepository(GalleryImage::class)->find($entityInstance->getId());
                if ($originalEntity) {
                    $entityInstance->setImagePath($originalEntity->getImagePath());
                }
            }
            
            // Handle display order changes using original value from UnitOfWork
            $unitOfWork = $entityManager->getUnitOfWork();
            $originalData = $unitOfWork->getOriginalEntityData($entityInstance);
            $originalOrder = $originalData['displayOrder'] ?? null;
            if ($originalOrder !== null) {
                $this->handleDisplayOrderUpdate($entityManager, $entityInstance, (int) $originalOrder);
            }
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    /**
     * Persist entity with validation groups
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof GalleryImage) {
            //
            
            // Обработка загрузки файла
            $this->handleFileUpload($entityInstance);
            
            // Use 'create' validation group for new entities
            $violations = $this->validator->validate($entityInstance, null, ['create']);
            
            if (count($violations) > 0) {
                throw new \InvalidArgumentException('Validation failed: ' . (string) $violations);
            }
            
            // Убеждаемся, что изображение загружено
            if (empty($entityInstance->getImagePath())) {
                throw new \InvalidArgumentException('Image is required for new gallery items');
            }
            
            // Handle display order for new images
            $this->handleDisplayOrderForNewImage($entityManager, $entityInstance);
        }
        parent::persistEntity($entityManager, $entityInstance);
    }

    /**
     * Handle file upload for gallery images
     */
    private function handleFileUpload(GalleryImage $galleryImage): void
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        
        if ($request && $request->files->has('GalleryImage')) {
            $formData = $request->files->get('GalleryImage');
            
            if (isset($formData['imagePath']) && $formData['imagePath']) {
                $uploadedFile = $formData['imagePath'];
                
                if ($uploadedFile instanceof \Symfony\Component\HttpFoundation\File\UploadedFile) {
                    $uploadDir = 'public/assets/img/';
                    $extension = $uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension();
                    $fileName = uniqid() . '.' . $extension;
                    
                    try {
                        $uploadedFile->move($uploadDir, $fileName);
                        $galleryImage->setImagePath($fileName);
                    } catch (\Exception $e) {
                        throw new \InvalidArgumentException('Erreur lors de l\'upload du fichier: ' . $e->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Handle display order updates to maintain sequential ordering
     */
    private function handleDisplayOrderUpdate(EntityManagerInterface $entityManager, GalleryImage $updatedImage, ?int $originalOrder = null): void
    {
        $newOrder = $updatedImage->getDisplayOrder();
        $imageId = $updatedImage->getId();
        
        // Determine original order (prefer UnitOfWork value if provided)
        if ($originalOrder === null) {
            $originalImage = $entityManager->getRepository(GalleryImage::class)->find($imageId);
            if (!$originalImage) {
                return;
            }
            $originalOrder = $originalImage->getDisplayOrder();
        }
        
        // If order hasn't changed, no need to update others
        if ($newOrder === $originalOrder) {
            return;
        }
        
        $repository = $entityManager->getRepository(GalleryImage::class);
        
        if ($newOrder < $originalOrder) {
            // Moving up: increment orders of images between newOrder and originalOrder
            $qb = $repository->createQueryBuilder('g')
                ->where('g.id != :currentId')
                ->andWhere('g.displayOrder >= :newOrder')
                ->andWhere('g.displayOrder < :originalOrder')
                ->setParameter('currentId', $imageId)
                ->setParameter('newOrder', $newOrder)
                ->setParameter('originalOrder', $originalOrder);
                
            $imagesToUpdate = $qb->getQuery()->getResult();
            
            foreach ($imagesToUpdate as $image) {
                $image->setDisplayOrder($image->getDisplayOrder() + 1);
                $entityManager->persist($image);
            }
        } else {
            // Moving down: decrement orders of images between originalOrder and newOrder
            $qb = $repository->createQueryBuilder('g')
                ->where('g.id != :currentId')
                ->andWhere('g.displayOrder > :originalOrder')
                ->andWhere('g.displayOrder <= :newOrder')
                ->setParameter('currentId', $imageId)
                ->setParameter('originalOrder', $originalOrder)
                ->setParameter('newOrder', $newOrder);
                
            $imagesToUpdate = $qb->getQuery()->getResult();
            
            foreach ($imagesToUpdate as $image) {
                $image->setDisplayOrder($image->getDisplayOrder() - 1);
                $entityManager->persist($image);
            }
        }
    }

    /**
     * Handle display order for new images
     */
    private function handleDisplayOrderForNewImage(EntityManagerInterface $entityManager, GalleryImage $newImage): void
    {
        $newOrder = $newImage->getDisplayOrder();
        $repository = $entityManager->getRepository(GalleryImage::class);
        
        // Find all images with order >= newOrder
        $qb = $repository->createQueryBuilder('g')
            ->where('g.displayOrder >= :newOrder')
            ->setParameter('newOrder', $newOrder);
            
        $imagesToUpdate = $qb->getQuery()->getResult();
        
        // Increment their order by 1
        foreach ($imagesToUpdate as $image) {
            $image->setDisplayOrder($image->getDisplayOrder() + 1);
            $entityManager->persist($image);
        }
    }
}

