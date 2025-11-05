<?php

namespace App\Strategy\Pricing\Strategy;

use App\Entity\Order;
use App\Service\RestaurantSettingsService;
use App\Strategy\Pricing\PricingStrategy;

final class DefaultPricingStrategy implements PricingStrategy
{
    public function __construct(private RestaurantSettingsService $settings)
    {
    }

    public function computeAndSetTotals(Order $order, float $cartTotal): void
    {
        // Cart prices already include taxes (TTC)
        $taxRate = $this->settings->getVatRate();
        $subtotalWithoutTax = $cartTotal / (1 + $taxRate);
        $taxAmount = $cartTotal - $subtotalWithoutTax;

        $deliveryFee = (float) ($order->getDeliveryFee() ?? 0);
        $total = $cartTotal + $deliveryFee;

        $order->setSubtotal(number_format($subtotalWithoutTax, 2, '.', ''));
        $order->setTaxAmount(number_format($taxAmount, 2, '.', ''));
        $order->setTotal(number_format($total, 2, '.', ''));
    }
}


