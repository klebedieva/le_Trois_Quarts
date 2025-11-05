<?php

namespace App\Strategy\Pricing;

use App\Entity\Order;

interface PricingStrategy
{
    /**
     * Compute and populate subtotal, tax and total on the order.
     * Implementations must not persist/flush, only mutate the entity.
     */
    public function computeAndSetTotals(Order $order, float $cartTotal): void;
}


