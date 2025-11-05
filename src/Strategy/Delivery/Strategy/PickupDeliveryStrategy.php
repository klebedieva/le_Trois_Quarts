<?php

namespace App\Strategy\Delivery\Strategy;

use App\Entity\Order;
use App\Enum\DeliveryMode;
use App\Strategy\Delivery\DeliveryStrategy;

final class PickupDeliveryStrategy implements DeliveryStrategy
{
    public function supports(DeliveryMode $mode): bool
    {
        return $mode === DeliveryMode::PICKUP;
    }

    public function validateAndPopulate(Order $order, array $orderData): void
    {
        // Pickup: no address required; fee is always 0.00
        $order->setDeliveryFee('0.00');
        $order->setDeliveryAddress(null);
        $order->setDeliveryZip(null);
        $order->setDeliveryInstructions($orderData['deliveryInstructions'] ?? null);
    }
}


