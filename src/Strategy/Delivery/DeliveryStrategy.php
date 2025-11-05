<?php

namespace App\Strategy\Delivery;

use App\Entity\Order;
use App\Enum\DeliveryMode;

interface DeliveryStrategy
{
    public function supports(DeliveryMode $mode): bool;

    /**
     * Validate delivery-related data and populate delivery fields on the order.
     * MUST NOT persist or flush; pure domain mutation only.
     * Implementations must not modify unrelated fields.
     */
    public function validateAndPopulate(Order $order, array $orderData): void;
}


