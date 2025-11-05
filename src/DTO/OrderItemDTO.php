<?php

namespace App\DTO;

use OpenApi\Attributes as OA;

/**
 * Order Item Data Transfer Object
 *
 * Represents a single item within an order. This is the persisted state
 * of what was ordered, including prices at the time of order creation.
 * Used when returning order details to clients.
 *
 * Contains:
 * - id: Order item entity ID (database primary key)
 * - productId: Original menu item ID that was ordered
 * - productName: Name of the menu item at time of order
 * - unitPrice: Price per unit at time of order
 * - quantity: Number of items ordered
 * - total: Line total (unitPrice × quantity)
 */
#[OA\Schema(
    schema: 'OrderItem',
    description: 'Order item representation',
    type: 'object'
)]
class OrderItemDTO
{
    public function __construct(
        #[OA\Property(property: 'id', type: 'integer', example: 1, description: 'Order item ID')]
        public int $id,

        #[OA\Property(property: 'productId', type: 'integer', example: 5, description: 'Product ID')]
        public int $productId,

        #[OA\Property(property: 'productName', type: 'string', example: 'Risotto aux champignons', description: 'Product name')]
        public string $productName,

        #[OA\Property(property: 'unitPrice', type: 'number', format: 'float', example: 14.5, description: 'Unit price')]
        public float $unitPrice,

        #[OA\Property(property: 'quantity', type: 'integer', example: 2, description: 'Quantity')]
        public int $quantity,

        #[OA\Property(property: 'total', type: 'number', format: 'float', example: 29.0, description: 'Line total')]
        public float $total
    ) {}

    /**
     * Convert DTO to array for JSON serialization
     *
     * @return array Array representation of order item
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'productId' => $this->productId,
            'productName' => $this->productName,
            'unitPrice' => $this->unitPrice,
            'quantity' => $this->quantity,
            'total' => $this->total
        ];
    }
}

