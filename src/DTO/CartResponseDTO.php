<?php

namespace App\DTO;

use OpenApi\Attributes as OA;

/**
 * Cart Response Data Transfer Object
 *
 * Represents the complete shopping cart state including all items,
 * calculated totals, and item count. Used in cart-related API responses
 * to provide a consistent structure for frontend consumption.
 *
 * Contains:
 * - items: Array of CartItemDTO objects representing all items in cart
 * - total: Calculated total price of all items (sum of item price × quantity)
 * - itemCount: Total quantity of all items (sum of all item quantities)
 */
#[OA\Schema(
    schema: 'CartResponse',
    description: 'Cart response representation',
    type: 'object'
)]
class CartResponseDTO
{
    public function __construct(
        #[OA\Property(property: 'items', type: 'array', items: new OA\Items(type: 'object'), description: 'Cart items')]
        public array $items,

        #[OA\Property(property: 'total', type: 'number', format: 'float', example: 29.0, description: 'Total price')]
        public float $total,

        #[OA\Property(property: 'itemCount', type: 'integer', example: 2, description: 'Total number of items')]
        public int $itemCount
    ) {}

    /**
     * Convert DTO to array for JSON serialization
     *
     * Recursively converts nested CartItemDTO objects to arrays.
     * Handles both DTO objects and plain arrays for flexibility.
     *
     * @return array Array representation of cart with all items and totals
     */
    public function toArray(): array
    {
        return [
            'items' => array_map(fn($item) => $item instanceof CartItemDTO ? $item->toArray() : $item, $this->items),
            'total' => $this->total,
            'itemCount' => $this->itemCount
        ];
    }
}

