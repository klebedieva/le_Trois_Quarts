<?php

namespace App\Service;

use App\Entity\Order;

class TaxCalculationService
{
    public function __construct(
        private RestaurantSettingsService $restaurantSettings
    ) {
    }

    /**
     * Calculates taxes for amount including taxes (TTC)
     */
    public function calculateTaxFromTTC(float $amountWithTax): array
    {
        $taxRate = $this->restaurantSettings->getVatRate();
        $amountWithoutTax = $amountWithTax / (1 + $taxRate);
        $taxAmount = $amountWithTax - $amountWithoutTax;

        return [
            'amountWithoutTax' => round($amountWithoutTax, 2),
            'taxAmount' => round($taxAmount, 2),
            'amountWithTax' => round($amountWithTax, 2),
            'taxRate' => $taxRate,
        ];
    }

    /**
     * Calculates taxes for amount without taxes (HT)
     */
    public function calculateTaxFromHT(float $amountWithoutTax): array
    {
        $taxRate = $this->restaurantSettings->getVatRate();
        $taxAmount = $amountWithoutTax * $taxRate;
        $amountWithTax = $amountWithoutTax + $taxAmount;

        return [
            'amountWithoutTax' => round($amountWithoutTax, 2),
            'taxAmount' => round($taxAmount, 2),
            'amountWithTax' => round($amountWithTax, 2),
            'taxRate' => $taxRate,
        ];
    }

    /**
     * Applies all monetary calculations (subtotal, taxes, delivery fee, discounts)
     * directly onto the provided order entity.
     *
     * Key steps:
     * 1. Recalculate each line item total to ensure products use the latest price/qty.
     * 2. Sum the cart amount including taxes (TTC) and break it down into HT/TVA.
     * 3. Re-apply coupon/discount amounts so the order always reflects the latest rules.
     * 4. Persist formatted monetary values (two decimals, stored as strings for DECIMAL columns).
     *
     * @param Order $order Order entity whose totals should be refreshed.
     */
    public function applyOrderTotals(Order $order): void
    {
        $subtotalWithTax = 0.0;

        foreach ($order->getItems() as $item) {
            $item->recalculateTotal();
            $subtotalWithTax += (float) $item->getTotal();
        }

        $deliveryFee = (float) ($order->getDeliveryFee() ?? 0);

        $taxBreakdown = $this->calculateTaxFromTTC($subtotalWithTax);
        $order->setSubtotal($this->formatAmount($taxBreakdown['amountWithoutTax']));
        $order->setTaxAmount($this->formatAmount($taxBreakdown['taxAmount']));

        $discountAmount = (float) ($order->getDiscountAmount() ?? 0);
        if ($order->getCoupon() !== null) {
            $orderAmountBeforeDiscount = $subtotalWithTax + $deliveryFee;
            $calculatedDiscount = $order->getCoupon()->calculateDiscount($orderAmountBeforeDiscount);
            $order->setDiscountAmount($this->formatAmount($calculatedDiscount));
            $discountAmount = $calculatedDiscount;
        }

        $total = max($subtotalWithTax + $deliveryFee - $discountAmount, 0);
        $order->setTotal($this->formatAmount($total));
    }

    /**
     * Gets current tax rate
     */
    public function getTaxRate(): float
    {
        return $this->restaurantSettings->getVatRate();
    }

    /**
     * Helper to ensure all persisted monetary values keep a consistent format.
     */
    private function formatAmount(float $amount): string
    {
        return number_format($amount, 2, '.', '');
    }
}
