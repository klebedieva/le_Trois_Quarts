<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\OrderStatus;
use App\Enum\DeliveryMode;
use App\Enum\PaymentMode;
use App\Service\SymfonyEmailService;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\DateTimeFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

#[IsGranted('ROLE_ADMIN')]
class OrderCrudController extends AbstractCrudController
{
    private EntityManagerInterface $entityManager;
    private SymfonyEmailService $emailService;
    private AdminUrlGenerator $adminUrlGenerator;

    public function __construct(EntityManagerInterface $entityManager, SymfonyEmailService $emailService, AdminUrlGenerator $adminUrlGenerator)
    {
        $this->entityManager = $entityManager;
        $this->emailService = $emailService;
        $this->adminUrlGenerator = $adminUrlGenerator;
    }

    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commande')
            ->setEntityLabelInPlural('Commandes')
            ->setPageTitle('index', 'Gestion des commandes')
            ->setPageTitle('edit', 'Modifier la commande')
            ->setPageTitle('new', 'Nouvelle commande')
            ->setPageTitle('detail', 'Détails de la commande')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setSearchFields(['no', 'clientName', 'deliveryAddress', 'deliveryZip']);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            TextField::new('no', 'Numéro de commande')
                ->setRequired(true)
                ->setHelp('Numéro unique de la commande'),

            TextField::new('clientName', 'Nom complet du client')
                ->setRequired(false)
                ->setHelp('Prénom et nom de famille du client'),

            TextField::new('clientPhone', 'Téléphone du client')
                ->setRequired(false)
                ->setHelp('Numéro de téléphone du client'),

            TextField::new('clientEmail', 'Email du client')
                ->setRequired(false)
                ->setHelp('Email du client pour l\'envoi d\'emails'),
            
            ChoiceField::new('status', 'Statut')
                ->setChoices([
                    'Nouveau' => OrderStatus::PENDING,
                    'Confirmée' => OrderStatus::CONFIRMED,
                    'En préparation' => OrderStatus::PREPARING,
                    'Livrée' => OrderStatus::DELIVERED,
                    'Annulée' => OrderStatus::CANCELLED,
                ])
                ->renderExpanded(false)
                ->setRequired(true)
                ->setTemplatePath('admin/order/_status_badge.html.twig')
                ->renderAsBadges([
                    'pending' => 'primary',
                    'confirmed' => 'success',
                    'preparing' => 'info',
                    'delivered' => 'primary',
                    'cancelled' => 'danger',
                ]),

            ChoiceField::new('deliveryMode', 'Mode de livraison')
                ->setChoices([
                    'Livraison' => DeliveryMode::DELIVERY,
                    'À emporter' => DeliveryMode::PICKUP,
                ])
                ->renderExpanded(false)
                ->setRequired(true)
                ->formatValue(function ($value, $entity) {
                    return match($entity->getDeliveryMode()) {
                        DeliveryMode::DELIVERY => 'Livraison',
                        DeliveryMode::PICKUP => 'À emporter',
                    };
                }),

            TextField::new('deliveryAddress', 'Adresse de livraison')
                ->hideOnIndex()
                ->setRequired(false),

            TextField::new('deliveryZip', 'Code postal')
                ->hideOnIndex()
                ->setRequired(false),

            TextareaField::new('deliveryInstructions', 'Instructions de livraison')
                ->hideOnIndex()
                ->setNumOfRows(3)
                ->setRequired(false),

            MoneyField::new('deliveryFee', 'Frais de livraison')
                ->setCurrency('EUR')
                ->setStoredAsCents(false)
                ->setRequired(true)
                ->setFormTypeOptions([
                    'attr' => ['value' => '5.00']
                ]),

            ChoiceField::new('paymentMode', 'Mode de paiement')
                ->setChoices([
                    'Carte bancaire' => PaymentMode::CARD,
                    'Espèces' => PaymentMode::CASH,
                    'Tickets restaurant' => PaymentMode::TICKETS,
                ])
                ->renderExpanded(false)
                ->setRequired(true)
                ->formatValue(function ($value, $entity) {
                    return match($entity->getPaymentMode()) {
                        PaymentMode::CARD => 'Carte bancaire',
                        PaymentMode::CASH => 'Espèces',
                        PaymentMode::TICKETS => 'Tickets restaurant',
                    };
                }),

                   MoneyField::new('subtotal', 'Sous-total (HT)')
                       ->setCurrency('EUR')
                       ->setStoredAsCents(false)
                       ->hideOnForm()
                       ->setRequired(false)
                       ->setHelp('Montant hors taxes (calculé automatiquement)'),

                   MoneyField::new('taxAmount', 'TVA (10%)')
                       ->setCurrency('EUR')
                       ->setStoredAsCents(false)
                       ->hideOnForm()
                       ->setRequired(false)
                       ->setHelp('Taxe sur la valeur ajoutée (calculée automatiquement)'),

                   MoneyField::new('total', 'Total TTC')
                       ->setCurrency('EUR')
                       ->setStoredAsCents(false)
                       ->hideOnForm()
                       ->setRequired(false)
                       ->setHelp('Total toutes taxes comprises (calculé automatiquement)'),

            DateTimeField::new('createdAt', 'Date de création')
                ->hideOnForm()
                ->setFormat('dd/MM/yyyy HH:mm')
                ->setTimezone('Europe/Paris'),

            CollectionField::new('items', 'Articles commandés')
                ->useEntryCrudForm()
                ->hideOnIndex()
                ->setFormTypeOptions([
                    'by_reference' => false,
                    'allow_add' => true,
                    'allow_delete' => true,
                ])
                ->formatValue(function ($value, $entity) {
                    if (!$entity || !$entity->getItems()) {
                        return 'Aucun article';
                    }
                    
                    $items = [];
                    foreach ($entity->getItems() as $item) {
                        $items[] = sprintf(
                            '%d x %s - %s€ (Total: %s€)',
                            $item->getQuantity(),
                            $item->getProductName(),
                            $item->getUnitPrice(),
                            $item->getTotal()
                        );
                    }
                    
                    return implode('<br>', $items);
                }),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        // Action pour confirmer une commande
        $confirmOrder = Action::new('confirmOrder', 'Confirmer')
            ->setIcon('fa fa-check')
            ->linkToCrudAction('confirmOrder')
            ->setCssClass('btn btn-soft-success btn-sm')
            ->displayIf(function ($entity) {
                return $entity->getStatus() === OrderStatus::PENDING;
            });

        // Action pour marquer en préparation
        $prepareOrder = Action::new('prepareOrder', 'En préparation')
            ->setIcon('fa fa-clock')
            ->linkToCrudAction('prepareOrder')
            ->setCssClass('btn btn-soft-warning btn-sm')
            ->displayIf(function ($entity) {
                return $entity->getStatus() === OrderStatus::CONFIRMED;
            });

        // Action pour marquer comme livrée
        $deliverOrder = Action::new('deliverOrder', 'Livrée')
            ->setIcon('fa fa-truck')
            ->linkToCrudAction('deliverOrder')
            ->setCssClass('btn btn-soft-info btn-sm')
            ->displayIf(function ($entity) {
                return $entity->getStatus() === OrderStatus::PREPARING;
            });

        // Action pour annuler
        $cancelOrder = Action::new('cancelOrder', 'Annuler')
            ->setIcon('fa fa-times')
            ->linkToCrudAction('cancelOrder')
            ->setCssClass('btn btn-soft-warning btn-sm')
            ->displayIf(function ($entity) {
                return in_array($entity->getStatus(), [OrderStatus::PENDING, OrderStatus::CONFIRMED, OrderStatus::PREPARING]);
            });

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $confirmOrder)
            ->add(Crud::PAGE_INDEX, $prepareOrder)
            ->add(Crud::PAGE_INDEX, $deliverOrder)
            ->add(Crud::PAGE_INDEX, $cancelOrder)
            ->add(Crud::PAGE_DETAIL, $confirmOrder)
            ->add(Crud::PAGE_DETAIL, $prepareOrder)
            ->add(Crud::PAGE_DETAIL, $deliverOrder)
            ->add(Crud::PAGE_DETAIL, $cancelOrder)
            ->update(Crud::PAGE_INDEX, Action::DELETE, function(Action $action){
                return $action->setIcon('fa fa-trash')
                    ->setLabel('Supprimer')
                    ->setCssClass('action-delete btn btn-soft-danger btn-sm');
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, function(Action $action){
                return $action->setIcon('fa fa-edit')
                    ->setLabel('Modifier')
                    ->setCssClass('btn btn-soft-secondary btn-sm');
            })
            ->update(Crud::PAGE_INDEX, Action::DETAIL, function(Action $action){
                return $action->setIcon('fa fa-eye')
                    ->setLabel('Voir')
                    ->setCssClass('btn btn-soft-info btn-sm');
            })
            ->setPermission(Action::DELETE, 'ROLE_ADMIN');
    }

    /**
     * Prefill defaults when opening the "new" form so the first field (order number) is auto-filled.
     */
    public function createEntity(string $entityFqcn)
    {
        $order = new Order();
        $order->setCreatedAt(new \DateTimeImmutable());
        $order->setNo($this->generateOrderNo());
        // Set default values for required financial fields
        $order->setSubtotal('0.00');
        $order->setTaxAmount('0.00');
        $order->setTotal('0.00');
        return $order;
    }

    /**
     * Safety net to ensure required fields are set on persist.
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entity): void
    {
        if ($entity instanceof Order) {
            if (!$entity->getCreatedAt()) {
                $entity->setCreatedAt(new \DateTimeImmutable());
            }
            if (!$entity->getNo()) {
                $entity->setNo($this->generateOrderNo());
            }
            // Recalculate totals based on order items
            $entity->recalculateTotals();
        }
        parent::persistEntity($entityManager, $entity);
    }

    /**
     * Update entity and recalculate totals
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        if ($entityInstance instanceof Order) {
            // Recalculate totals when order is updated
            $entityInstance->recalculateTotals();
        }
        parent::updateEntity($entityManager, $entityInstance);
    }

    private function generateOrderNo(): string
    {
        $datePart = (new \DateTimeImmutable())->format('Ymd');
        $timePart = (new \DateTimeImmutable())->format('His');
        $randPart = (string) random_int(1000, 9999);
        return 'ORD-A-' . $datePart . '-' . $randPart;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(TextFilter::new('no', 'Numéro de commande'))
            ->add(TextFilter::new('clientName', 'Nom du client'))
            ->add(ChoiceFilter::new('status', 'Statut')
                ->setChoices([
                    'Nouveau' => 'pending',
                    'Confirmée' => 'confirmed',
                    'En préparation' => 'preparing',
                    'Livrée' => 'delivered',
                    'Annulée' => 'cancelled',
                ]))
            ->add(ChoiceFilter::new('deliveryMode', 'Mode de livraison')
                ->setChoices([
                    'Livraison' => DeliveryMode::DELIVERY,
                    'À emporter' => DeliveryMode::PICKUP,
                ]))
            ->add(ChoiceFilter::new('paymentMode', 'Mode de paiement')
                ->setChoices([
                    'Carte bancaire' => PaymentMode::CARD,
                    'Espèces' => PaymentMode::CASH,
                    'Tickets restaurant' => PaymentMode::TICKETS,
                ]))
            ->add(DateTimeFilter::new('createdAt', 'Date de création'))
            ->add(NumericFilter::new('total', 'Total'));
    }

    /**
     * Confirmer une commande avec envoi d'email
     */
    public function confirmOrder(Request $request): Response
    {
        $entityId = $request->query->get('entityId');
        if (!$entityId) {
            $this->addFlash('error', 'ID de la commande non trouvé.');
            return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
        }

        /** @var Order|null $order */
        $order = $this->entityManager->getRepository(Order::class)->find($entityId);
        if (!$order) {
            $this->addFlash('error', 'Commande non trouvée.');
            return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
        }

        if ($request->isMethod('POST')) {
            $confirmationMessage = $request->request->get('confirmationMessage', 'Votre commande est confirmée et sera préparée rapidement.');

            try {
                // Update order status
                $order->setStatus(OrderStatus::CONFIRMED);
                $this->entityManager->flush();

                // Try to send confirmation email if client email is provided
                if ($order->getClientEmail() && $order->getClientName()) {
                    $clientEmail = $order->getClientEmail();
                    $clientName = $order->getClientName();
                    $emailSubject = 'Confirmation de votre commande - Le Trois Quarts';
                    
                    $emailSent = $this->emailService->sendOrderConfirmation(
                        $clientEmail,
                        $clientName,
                        $emailSubject,
                        $confirmationMessage,
                        $order
                    );
                } else {
                    $emailSent = false;
                }
                
                if ($emailSent) {
                    $this->addFlash('success', 'Commande confirmée et email envoyé avec succès !');
                } else {
                    if ($order->getClientEmail() && $order->getClientName()) {
                        $this->addFlash('warning', 'Commande confirmée mais erreur lors de l\'envoi de l\'email.');
                    } else {
                        $this->addFlash('warning', 'Commande confirmée mais email non envoyé (email ou nom du client manquant).');
                    }
                }

            } catch (\Throwable $e) {
                $this->addFlash('error', 'Erreur lors de la confirmation: ' . $e->getMessage());
            }

            return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
        }

        // GET: render confirmation page
        return $this->render('admin/order/confirm.html.twig', [
            'order' => $order,
        ]);
    }

    /**
     * Marquer une commande en préparation
     */
    public function prepareOrder(Request $request): Response
    {
        $entityId = $request->query->get('entityId');
        if (!$entityId) {
            $this->addFlash('error', 'ID de la commande non trouvé.');
            return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
        }

        $order = $this->entityManager->getRepository(Order::class)->find($entityId);
        if (!$order) {
            $this->addFlash('error', 'Commande non trouvée.');
            return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
        }

        $order->setStatus(OrderStatus::PREPARING);
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Commande marquée en préparation !');
        
        return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
    }

    /**
     * Marquer une commande comme livrée
     */
    public function deliverOrder(Request $request): Response
    {
        $entityId = $request->query->get('entityId');
        if (!$entityId) {
            $this->addFlash('error', 'ID de la commande non trouvé.');
            return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
        }

        $order = $this->entityManager->getRepository(Order::class)->find($entityId);
        if (!$order) {
            $this->addFlash('error', 'Commande non trouvée.');
            return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
        }

        $order->setStatus(OrderStatus::DELIVERED);
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Commande marquée comme livrée !');
        
        return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
    }

    /**
     * Annuler une commande
     */
    public function cancelOrder(Request $request): Response
    {
        $entityId = $request->query->get('entityId');
        if (!$entityId) {
            $this->addFlash('error', 'ID de la commande non trouvé.');
            return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
        }

        $order = $this->entityManager->getRepository(Order::class)->find($entityId);
        if (!$order) {
            $this->addFlash('error', 'Commande non trouvée.');
            return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
        }

        $order->setStatus(OrderStatus::CANCELLED);
        $this->entityManager->flush();
        
        $this->addFlash('success', 'Commande annulée !');
        
        return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
    }

    /**
     * Change order status by clicking on status badge
     */
    #[Route('/admin/orders/change-status', name: 'admin_order_change_status')]
    public function changeStatus(Request $request): Response
    {
        $entityId = $request->query->get('entityId');
        if (!$entityId) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'message' => 'ID de la commande non trouvé.'], 400);
            }
            $this->addFlash('error', 'ID de la commande non trouvé.');
            return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
        }

        /** @var Order|null $order */
        $order = $this->entityManager->getRepository(Order::class)->find($entityId);
        if (!$order) {
            if ($request->isXmlHttpRequest()) {
                return $this->json(['success' => false, 'message' => 'Commande non trouvée.'], 404);
            }
            $this->addFlash('error', 'Commande non trouvée.');
            return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
        }

        // Status cycle for click: pending -> confirmed -> preparing -> delivered -> cancelled -> pending
        // Note: We could add an intermediate "en attente" status like reservations, but keeping it simple for now
        $cycle = [OrderStatus::PENDING, OrderStatus::CONFIRMED, OrderStatus::PREPARING, OrderStatus::DELIVERED, OrderStatus::CANCELLED];
        $current = $order->getStatus();
        $currentIndex = array_search($current, $cycle, true);
        
        if ($currentIndex === false) {
            // If status is outside cycle, start with 'pending'
            $next = OrderStatus::PENDING;
        } else {
            $next = $cycle[($currentIndex + 1) % count($cycle)];
        }

        $order->setStatus($next);
        $this->entityManager->flush();

        // Status labels
        $statusLabels = [
            OrderStatus::PENDING->value => 'Nouveau',
            OrderStatus::CONFIRMED->value => 'Confirmée',
            OrderStatus::PREPARING->value => 'En préparation',
            OrderStatus::DELIVERED->value => 'Livrée',
            OrderStatus::CANCELLED->value => 'Annulée',
        ];

        $statusColors = [
            OrderStatus::PENDING->value => 'badge-primary',
            OrderStatus::CONFIRMED->value => 'badge-success',
            OrderStatus::PREPARING->value => 'badge-secondary',
            OrderStatus::DELIVERED->value => 'badge-warning',
            OrderStatus::CANCELLED->value => 'badge-danger',
        ];

        // Return JSON for AJAX requests
        if ($request->isXmlHttpRequest()) {
            return $this->json([
                'success' => true,
                'message' => sprintf('Statut de la commande #%s changé en "%s"', $order->getNo(), $statusLabels[$next->value]),
                'newStatus' => $next->value,
                'newLabel' => $statusLabels[$next->value],
                'newClass' => $statusColors[$next->value]
            ]);
        }

        // Fallback for non-AJAX requests
        $this->addFlash('success', sprintf(
            'Statut de la commande #%s changé en "%s"',
            $order->getNo(),
            $statusLabels[$next->value]
        ));

        return $this->redirect($this->adminUrlGenerator->setController(OrderCrudController::class)->setAction('index')->generateUrl());
    }
}
