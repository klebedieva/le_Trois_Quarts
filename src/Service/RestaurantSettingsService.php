<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class RestaurantSettingsService
{
    public function __construct(
        private ParameterBagInterface $parameterBag
    ) {
    }

    /**
     * Get standard delivery fee
     *
     * @return float Delivery fee in euros
     */
    public function getDeliveryFee(): float
    {
        $restaurant = $this->parameterBag->get('restaurant');
        return (float) $restaurant['delivery']['fee'];
    }

    /**
     * Get minimum order amount for free delivery
     *
     * Orders above this amount qualify for free delivery.
     *
     * @return float Minimum order amount in euros
     */
    public function getFreeDeliveryThreshold(): float
    {
        $restaurant = $this->parameterBag->get('restaurant');
        return (float) $restaurant['delivery']['free_delivery_threshold'];
    }

    /**
     * Get delivery radius in kilometers
     *
     * Maximum distance from restaurant for delivery eligibility.
     *
     * @return int Delivery radius in kilometers
     */
    public function getDeliveryRadius(): int
    {
        $restaurant = $this->parameterBag->get('restaurant');
        return (int) $restaurant['delivery']['radius_km'];
    }

    /**
     * Get VAT (Value Added Tax) rate
     *
     * @return float VAT rate as decimal (e.g., 0.1 for 10%)
     */
    public function getVatRate(): float
    {
        $restaurant = $this->parameterBag->get('restaurant');
        return (float) $restaurant['tax']['vat_rate'];
    }

    /**
     * Get restaurant contact phone number
     *
     * @return string Phone number
     */
    public function getContactPhone(): string
    {
        $restaurant = $this->parameterBag->get('restaurant');
        return $restaurant['contact']['phone'];
    }

    /**
     * Get restaurant contact email address
     *
     * @return string Email address
     */
    public function getContactEmail(): string
    {
        $restaurant = $this->parameterBag->get('restaurant');
        return $restaurant['contact']['email'];
    }

    /**
     * Get restaurant physical address
     *
     * @return string Full address string
     */
    public function getContactAddress(): string
    {
        $restaurant = $this->parameterBag->get('restaurant');
        return $restaurant['contact']['address'];
    }

    /**
     * Get business hours configuration
     *
     * Returns opening and closing times for daily operations.
     *
     * @return array Business hours with daily start/end times
     */
    public function getBusinessHours(): array
    {
        $restaurant = $this->parameterBag->get('restaurant');
        return [
            'daily' => [
                'start' => $restaurant['business_hours']['daily']['start'],
                'end' => $restaurant['business_hours']['daily']['end'],
            ],
        ];
    }
}
