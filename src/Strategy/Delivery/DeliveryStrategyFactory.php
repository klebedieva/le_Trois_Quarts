<?php

namespace App\Strategy\Delivery;

use App\Enum\DeliveryMode;

final class DeliveryStrategyFactory
{
    /** @var iterable<DeliveryStrategy> */
    private iterable $strategies;

    /**
     * @param iterable<DeliveryStrategy> $strategies
     */
    public function __construct(iterable $strategies)
    {
        $this->strategies = $strategies;
    }

    public function forMode(DeliveryMode $mode): DeliveryStrategy
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->supports($mode)) {
                return $strategy;
            }
        }

        throw new \RuntimeException('No delivery strategy registered for mode: ' . $mode->value);
    }
}


