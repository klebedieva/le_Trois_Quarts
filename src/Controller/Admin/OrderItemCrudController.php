<?php

namespace App\Controller\Admin;

use App\Entity\OrderItem;
use App\Entity\MenuItem;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Filter\TextFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\NumericFilter;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

#[IsGranted('ROLE_ADMIN')]
class OrderItemCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return OrderItem::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Article de commande')
            ->setEntityLabelInPlural('Articles de commande')
            ->setPageTitle('index', 'Gestion des articles de commande')
            ->setPageTitle('edit', 'Modifier l\'article')
            ->setPageTitle('new', 'Nouvel article')
            ->setPageTitle('detail', 'Détails de l\'article')
            ->setDefaultSort(['id' => 'DESC'])
            ->setPaginatorPageSize(20)
            ->setSearchFields(['productName', 'productId']);
    }

    public function getPageTitle(string $pageName): string
    {
        if ($pageName === Crud::PAGE_EDIT) {
            $entity = $this->getContext()->getEntity()->getInstance();
            if ($entity && $entity->getProductName()) {
                return 'Modifier: ' . $entity->getProductName();
            }
        }
        
        return parent::getPageTitle($pageName);
    }

    // Removed dynamic menu item info endpoint; manual input restored

    /**
     * Обновляет заказ после изменения статьи
     */
    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // Manual mode: keep values as entered
        // Пересчитываем суммы статьи
        if ($entityInstance->getUnitPrice() && $entityInstance->getQuantity()) {
            $total = (float) $entityInstance->getUnitPrice() * $entityInstance->getQuantity();
            $entityInstance->setTotal(number_format($total, 2, '.', ''));
        }
        
        parent::updateEntity($entityManager, $entityInstance);
        
        // Пересчитываем общие суммы заказа
        if ($entityInstance->getOrderRef()) {
            $order = $entityInstance->getOrderRef();
            $order->recalculateTotals();
            
            // Принудительно обновляем заказ в EntityManager
            $entityManager->persist($order);
            $entityManager->flush();
            
            // Добавляем сообщение для отладки
            $this->addFlash('success', 'Суммы заказа пересчитаны автоматически');
        }
    }

    /**
     * Создает новую статью заказа
     */
    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        // Manual mode: keep values as entered
        // Пересчитываем суммы статьи
        if ($entityInstance->getUnitPrice() && $entityInstance->getQuantity()) {
            $total = (float) $entityInstance->getUnitPrice() * $entityInstance->getQuantity();
            $entityInstance->setTotal(number_format($total, 2, '.', ''));
        }
        
        parent::persistEntity($entityManager, $entityInstance);
        
        // Пересчитываем общие суммы заказа
        if ($entityInstance->getOrderRef()) {
            $order = $entityInstance->getOrderRef();
            $order->recalculateTotals();
            $entityManager->persist($order);
            $entityManager->flush();
        }
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->hideOnForm(),
            // Menu selection removed; values are entered manually
            
            IntegerField::new('productId', 'ID du produit')
                ->setRequired(true)
                ->setHelp('ID du produit dans le menu'),

            TextField::new('productName', 'Nom du produit')
                ->setRequired(true)
                ->setHelp('Nom du produit tel qu\'affiché au client'),

                   MoneyField::new('unitPrice', 'Prix unitaire TTC')
                       ->setCurrency('EUR')
                       ->setStoredAsCents(false)
                       ->setRequired(true)
                       ->setHelp('Prix unitaire du produit (toutes taxes comprises)'),

            IntegerField::new('quantity', 'Quantité')
                ->setRequired(true)
                ->setHelp('Quantité commandée')
                ->setFormTypeOptions([
                    'attr' => ['style' => 'width: 80px; min-width: 80px;']
                ]),

            MoneyField::new('total', 'Total TTC')
                ->setCurrency('EUR')
                ->setStoredAsCents(false)
                ->setRequired(true)
                ->hideOnForm()
                ->setHelp('Total calculé automatiquement (prix × quantité, toutes taxes comprises)'),

            AssociationField::new('orderRef', 'Commande')
                ->setRequired(true)
                ->setFormTypeOptions([
                    'choice_label' => 'no',
                    'placeholder' => 'Sélectionnez une commande',
                ])
                ->formatValue(function ($value, $entity) {
                    return $entity && $entity->getOrderRef() ? $entity->getOrderRef()->getNo() : 'Aucune commande';
                }),
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
            ->add(TextFilter::new('productName', 'Nom du produit'))
            ->add(NumericFilter::new('productId', 'ID du produit'))
            ->add(NumericFilter::new('quantity', 'Quantité'))
            ->add(NumericFilter::new('unitPrice', 'Prix unitaire'))
            ->add(NumericFilter::new('total', 'Total'));
    }

    /**
     * Calculer automatiquement le total avant la persistance
     */

}
