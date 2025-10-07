<?php

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'OrderResponse',
    description: 'Order response representation',
    type: 'object'
)]
class OrderResponseDTO
{
    public function __construct(
        #[OA\Property(property: 'id', type: 'integer', example: 1, description: 'Order ID')]
        public int $id,

        #[OA\Property(property: 'no', type: 'string', example: 'ORD-20250107-001', description: 'Order number')]
        public string $no,

        #[OA\Property(property: 'status', type: 'string', example: 'pending', description: 'Order status')]
        public string $status,

        #[OA\Property(property: 'deliveryMode', type: 'string', example: 'delivery', description: 'Delivery mode')]
        public string $deliveryMode,

        #[OA\Property(property: 'deliveryAddress', type: 'string', example: '123 Main St', description: 'Delivery address', nullable: true)]
        public ?string $deliveryAddress,

        #[OA\Property(property: 'deliveryZip', type: 'string', example: '75001', description: 'Delivery ZIP code', nullable: true)]
        public ?string $deliveryZip,

        #[OA\Property(property: 'deliveryInstructions', type: 'string', example: 'Ring doorbell', description: 'Delivery instructions', nullable: true)]
        public ?string $deliveryInstructions,

        #[OA\Property(property: 'deliveryFee', type: 'number', format: 'float', example: 5.0, description: 'Delivery fee')]
        public float $deliveryFee,

        #[OA\Property(property: 'paymentMode', type: 'string', example: 'card', description: 'Payment mode')]
        public string $paymentMode,

        #[OA\Property(property: 'subtotal', type: 'number', format: 'float', example: 29.0, description: 'Subtotal')]
        public float $subtotal,

        #[OA\Property(property: 'taxAmount', type: 'number', format: 'float', example: 2.9, description: 'Tax amount')]
        public float $taxAmount,

        #[OA\Property(property: 'total', type: 'number', format: 'float', example: 36.9, description: 'Total amount')]
        public float $total,

        #[OA\Property(property: 'createdAt', type: 'string', format: 'date-time', example: '2025-01-07T10:30:00+00:00', description: 'Creation date')]
        public string $createdAt,

        #[OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object'), description: 'Order items')]
        public array $items
    ) {}

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'no' => $this->no,
            'status' => $this->status,
            'deliveryMode' => $this->deliveryMode,
            'deliveryAddress' => $this->deliveryAddress,
            'deliveryZip' => $this->deliveryZip,
            'deliveryInstructions' => $this->deliveryInstructions,
            'deliveryFee' => $this->deliveryFee,
            'paymentMode' => $this->paymentMode,
            'subtotal' => $this->subtotal,
            'taxAmount' => $this->taxAmount,
            'total' => $this->total,
            'createdAt' => $this->createdAt,
            'items' => array_map(fn($item) => $item instanceof OrderItemDTO ? $item->toArray() : $item, $this->items)
        ];
    }
}

