<?php

namespace App\Strategy\Delivery\Strategy;

use App\Entity\Order;
use App\Enum\DeliveryMode;
use App\Service\RestaurantSettingsService;
use App\Service\AddressValidationService;
use App\Strategy\Delivery\DeliveryStrategy;

final class DeliveryModeDeliveryStrategy implements DeliveryStrategy
{
    public function __construct(
        private RestaurantSettingsService $settings,
        private AddressValidationService $addressValidator,
    ) {}

    public function supports(DeliveryMode $mode): bool
    {
        return $mode === DeliveryMode::DELIVERY;
    }

    public function validateAndPopulate(Order $order, array $orderData): void
    {
        $address = $orderData['deliveryAddress'] ?? null;
        $zip = $orderData['deliveryZip'] ?? null;

        if (!$address) {
            throw new \InvalidArgumentException("L'adresse de livraison est requise");
        }

        $validation = $this->addressValidator->validateAddressForDelivery($address, $zip);
        if (!$validation['valid']) {
            throw new \InvalidArgumentException($validation['error'] ?? 'Livraison non disponible pour cette adresse');
        }

        $order->setDeliveryAddress($address);
        $order->setDeliveryZip($zip);
        $order->setDeliveryInstructions($orderData['deliveryInstructions'] ?? null);
        $fee = $orderData['deliveryFee'] ?? $this->settings->getDeliveryFee();
        $order->setDeliveryFee(number_format((float) $fee, 2, '.', ''));
    }
}


