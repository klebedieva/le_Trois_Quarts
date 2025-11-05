<?php

namespace App\DTO;

use OpenApi\Attributes as OA;

/**
 * Cart Item Data Transfer Object
 *
 * Represents a single item in the shopping cart with all necessary information
 * for display and calculations. This DTO is used when returning cart contents
 * to the frontend API clients.
 *
 * Contains:
 * - id: Menu item ID from database
 * - name: Display name of the menu item
 * - price: Unit price (float, e.g., 14.50)
 * - quantity: Number of this item in cart
 * - image: Path or URL to item image
 * - category: Item category (e.g., 'plats', 'desserts', 'boissons')
 */
#[OA\Schema(
    schema: 'CartItem',
    description: 'Cart item representation',
    type: 'object'
)]
class CartItemDTO
{
    public function __construct(
        #[OA\Property(property: 'id', type: 'integer', example: 1, description: 'Item ID')]
        public int $id,

        #[OA\Property(property: 'name', type: 'string', example: 'Risotto aux champignons', description: 'Item name')]
        public string $name,

        #[OA\Property(property: 'price', type: 'number', format: 'float', example: 14.5, description: 'Item price')]
        public float $price,

        #[OA\Property(property: 'quantity', type: 'integer', example: 2, description: 'Item quantity')]
        public int $quantity,

        #[OA\Property(property: 'image', type: 'string', example: '/uploads/menu/plat_1.png', description: 'Item image path')]
        public string $image,

        #[OA\Property(property: 'category', type: 'string', example: 'plats', description: 'Item category')]
        public string $category
    ) {}

    /**
     * Convert DTO to array for JSON serialization
     *
     * @return array Array representation of cart item
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'price' => $this->price,
            'quantity' => $this->quantity,
            'image' => $this->image,
            'category' => $this->category
        ];
    }
}

