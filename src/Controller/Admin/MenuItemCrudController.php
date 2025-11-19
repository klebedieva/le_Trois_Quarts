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
use App\Service\FileUploadValidator;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[IsGranted('ROLE_MODERATOR')]
class MenuItemCrudController extends AbstractCrudController
{
    public function __construct(
        private FileUploadValidator $fileValidator
    ) {
    }
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
        // Configure ImageField for display only - file upload is handled manually in handleFileUpload()
        // We disable EasyAdmin's automatic upload handling to avoid conflicts
        $imageField = ImageField::new('image', 'Image')
            ->setBasePath('/uploads/menu')
            ->setUploadDir('public/uploads/menu')
            ->setUploadedFileNamePattern('[slug]-[timestamp].[extension]')
            ->setHelp('Téléversez une image; le fichier sera copié dans /public/uploads/menu')
            // Disable EasyAdmin's automatic file handling - we handle it manually
            ->setFormTypeOptions([
                'required' => $pageName === Crud::PAGE_NEW,
                'allow_delete' => false, // Prevent EasyAdmin from deleting files
            ]);

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
                    
                    // If it's JSON, show as line-by-line list for easy editing
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
                        // show code or name if available
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
            DateTimeField::new('updatedAt', 'Modifiée le')->hideOnForm(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $actions = $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->update(Crud::PAGE_INDEX, Action::EDIT, function(Action $action){
                return $action->setCssClass('btn btn-soft-success btn-sm');
            })
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function(Action $action){
                return $action->setCssClass('btn btn-soft-info btn-sm');
            });

        // Only admins can see delete action
        if ($this->isGranted('ROLE_ADMIN')) {
            $actions = $actions->update(Crud::PAGE_INDEX, Action::DELETE, function(Action $action){
                return $action->setCssClass('action-delete btn btn-soft-danger btn-sm');
            });
        } else {
            // Remove delete action completely for moderators
            $actions = $actions->remove(Crud::PAGE_INDEX, Action::DELETE);
        }

        return $actions
            ->setPermission(Action::NEW, 'ROLE_ADMIN');
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('name', 'Nom'))
            ->add(TextFilter::new('category', 'Catégorie'))
            ->add(NumericFilter::new('price', 'Prix'))
            ->add(BooleanFilter::new('active', 'Actif'));
    }

    /**
     * Validate uploaded file before persisting new entity
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MenuItem) {
            // Store the image value before EasyAdmin processes it
            $savedImageValue = $entityInstance->getImage();
            
            // Handle file upload FIRST - this ensures file is saved before entity validation
            try {
                $this->handleFileUpload($entityInstance);
                // After our manual upload, save the image value to prevent EasyAdmin from overwriting it
                $savedImageValue = $entityInstance->getImage();
            } catch (\Symfony\Component\HttpKernel\Exception\BadRequestHttpException $e) {
                // Re-throw to show validation error in form
                throw $e;
            } catch (\Exception $e) {
                // Convert to BadRequestHttpException for proper form error display
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException($e->getMessage(), $e);
            }
            
            // Call parent to let EasyAdmin process the entity
            parent::persistEntity($entityManager, $entityInstance);
            
            // Ensure our manually set image value is preserved after EasyAdmin processing
            if ($savedImageValue && $entityInstance->getImage() !== $savedImageValue) {
                $entityInstance->setImage($savedImageValue);
                $entityManager->persist($entityInstance);
                $entityManager->flush();
            }
        } else {
            parent::persistEntity($entityManager, $entityInstance);
        }
    }

    /**
     * Validate uploaded file before updating entity
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof MenuItem) {
            // Get original image value before processing
            $originalEntity = $entityManager->getRepository(MenuItem::class)->find($entityInstance->getId());
            $originalImageValue = $originalEntity ? $originalEntity->getImage() : null;
            
            // Store the image value before EasyAdmin processes it
            $savedImageValue = $entityInstance->getImage();
            
            // Handle file upload if new file is being uploaded
            try {
                $this->handleFileUpload($entityInstance);
                // After our manual upload, save the image value
                if ($entityInstance->getImage()) {
                    $savedImageValue = $entityInstance->getImage();
                }
            } catch (\Symfony\Component\HttpKernel\Exception\BadRequestHttpException $e) {
                // Re-throw to show validation error in form
                throw $e;
            } catch (\Exception $e) {
                // Convert to BadRequestHttpException for proper form error display
                throw new \Symfony\Component\HttpKernel\Exception\BadRequestHttpException($e->getMessage(), $e);
            }
            
            // If image is empty after upload attempt, keep the existing value
            if (empty($savedImageValue) && $originalImageValue) {
                $savedImageValue = $originalImageValue;
                $entityInstance->setImage($originalImageValue);
            }
            
            // Call parent to let EasyAdmin process the entity
            parent::updateEntity($entityManager, $entityInstance);
            
            // Ensure our manually set image value is preserved after EasyAdmin processing
            if ($savedImageValue && $entityInstance->getImage() !== $savedImageValue) {
                $entityInstance->setImage($savedImageValue);
                $entityManager->persist($entityInstance);
                $entityManager->flush();
            }
        } else {
            parent::updateEntity($entityManager, $entityInstance);
        }
    }

    /**
     * Handle file upload for menu item images
     * This ensures files are saved to the correct directory and path is stored correctly in database
     * 
     * Note: This method handles the file upload manually to ensure files are saved to the correct
     * location on the hosting server. EasyAdmin's automatic file handling can sometimes fail on
     * hosting environments due to path resolution issues.
     */
    private function handleFileUpload(MenuItem $menuItem): void
    {
        $request = $this->container->get('request_stack')->getCurrentRequest();
        
        if (!$request || !$request->files->has('MenuItem')) {
            return;
        }
        
        $formData = $request->files->get('MenuItem');
        
        if (!isset($formData['image']) || !$formData['image']) {
            return;
        }
        
        $uploadedFile = $formData['image'];
        
        if (!$uploadedFile instanceof UploadedFile) {
            return;
        }
        
        // Validate file: MIME type, extension, and size
        try {
            $this->fileValidator->validate($uploadedFile);
        } catch (FileException $e) {
            // Add flash message and throw exception to show validation error
            $this->addFlash('error', $e->getMessage());
            throw new BadRequestHttpException($e->getMessage());
        }
        
        // Get project directory to build absolute path
        // This ensures the path works correctly on hosting servers
        $projectDir = $this->getParameter('kernel.project_dir');
        $uploadDir = $projectDir . '/public/uploads/menu';
        
        // Ensure upload directory exists with proper permissions
        if (!is_dir($uploadDir)) {
            if (!mkdir($uploadDir, 0755, true)) {
                throw new \RuntimeException('Failed to create upload directory: ' . $uploadDir);
            }
        }
        
        // Check if directory is writable
        if (!is_writable($uploadDir)) {
            throw new \RuntimeException('Upload directory is not writable: ' . $uploadDir);
        }
        
        // Generate unique filename using slug and timestamp pattern (as configured in ImageField)
        $slug = $this->getSlug($menuItem);
        $extension = $uploadedFile->guessExtension() ?: $uploadedFile->getClientOriginalExtension();
        $timestamp = time();
        $fileName = $slug . '-' . $timestamp . '.' . $extension;
        
        // Ensure filename is unique (in case of rapid uploads)
        $fullPath = $uploadDir . '/' . $fileName;
        $counter = 1;
        while (file_exists($fullPath)) {
            $fileName = $slug . '-' . $timestamp . '-' . $counter . '.' . $extension;
            $fullPath = $uploadDir . '/' . $fileName;
            $counter++;
        }
        
        try {
            // Move file to upload directory
            // This ensures the file is saved to the correct location
            $uploadedFile->move($uploadDir, $fileName);
            
            // Verify file was actually moved
            if (!file_exists($fullPath)) {
                throw new \RuntimeException('File was not saved after move operation');
            }
            
            // Store only the filename in database (not full path)
            // The basePath in ImageField will handle the path resolution for display
            $menuItem->setImage($fileName);
            
            // Log successful upload for debugging (can be removed in production)
            if ($this->container->has('logger')) {
                $logger = $this->container->get('logger');
                $logger->info('Menu item image uploaded', [
                    'menu_item_id' => $menuItem->getId(),
                    'menu_item_name' => $menuItem->getName(),
                    'filename' => $fileName,
                    'full_path' => $fullPath,
                    'upload_dir' => $uploadDir,
                ]);
            }
        } catch (\Exception $e) {
            // Clean up if file was partially moved
            if (file_exists($fullPath)) {
                @unlink($fullPath);
            }
            
            // Log error for debugging
            if ($this->container->has('logger')) {
                $logger = $this->container->get('logger');
                $logger->error('Menu item image upload failed', [
                    'menu_item_id' => $menuItem->getId(),
                    'menu_item_name' => $menuItem->getName(),
                    'error' => $e->getMessage(),
                    'upload_dir' => $uploadDir,
                ]);
            }
            
            throw new \InvalidArgumentException('Erreur lors de l\'upload du fichier: ' . $e->getMessage());
        }
    }

    /**
     * Get slug from menu item name for filename generation
     */
    private function getSlug(MenuItem $menuItem): string
    {
        $name = $menuItem->getName() ?? 'menu-item';
        // Simple slug generation: lowercase, replace spaces with hyphens, remove special chars
        $slug = strtolower(trim($name));
        $slug = preg_replace('/[^a-z0-9-]/', '-', $slug);
        $slug = preg_replace('/-+/', '-', $slug);
        $slug = trim($slug, '-');
        return $slug ?: 'menu-item';
    }
}


