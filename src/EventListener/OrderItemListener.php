<?php

namespace App\EventListener;

use App\Entity\OrderItem;
use Doctrine\ORM\Event\PreUpdateEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PrePersistEventArgs;
use Doctrine\ORM\Event\PostPersistEventArgs;
use App\Service\TaxCalculationService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class OrderItemListener
{
    public function __construct(
        private LoggerInterface $logger,
        private TaxCalculationService $taxCalculationService
    ) {}

    public function preUpdate(PreUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof OrderItem) {
            // Recalculate item total when quantity or unitPrice changes
            if ($args->hasChangedField('quantity') || $args->hasChangedField('unitPrice')) {
                $this->recalculateItemTotal($entity);
            }
        }
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof OrderItem) {
            $this->recalculateOrderTotals($entity, $args->getObjectManager());
        }
    }

    public function prePersist(PrePersistEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof OrderItem) {
            $this->recalculateItemTotal($entity);
        }
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $entity = $args->getObject();
        
        if ($entity instanceof OrderItem) {
            $this->recalculateOrderTotals($entity, $args->getObjectManager());
        }
    }

    private function recalculateItemTotal(OrderItem $item): void
    {
        if ($item->getUnitPrice() && $item->getQuantity()) {
            $total = (float) $item->getUnitPrice() * $item->getQuantity();
            $item->setTotal(number_format($total, 2, '.', ''));
        }
    }

    private function recalculateOrderTotals(OrderItem $item, EntityManagerInterface $em): void
    {
        if ($item->getOrderRef()) {
            $order = $item->getOrderRef();
            $this->taxCalculationService->applyOrderTotals($order);
            
            $em->persist($order);
            $em->flush();
            
            $this->logger->info('Order totals recalculated via listener', [
                'orderId' => $order->getId(),
            ]);
        }
    }
}
