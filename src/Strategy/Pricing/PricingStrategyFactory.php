<?php

namespace App\Strategy\Pricing;

final class PricingStrategyFactory
{
    public function __construct(private PricingStrategy $defaultPricing)
    {
    }

    public function default(): PricingStrategy
    {
        return $this->defaultPricing;
    }
}


