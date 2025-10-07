<?php

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApiResponse',
    description: 'Standard API response',
    type: 'object'
)]
class ApiResponseDTO
{
    public function __construct(
        #[OA\Property(property: 'success', type: 'boolean', example: true, description: 'Request success status')]
        public bool $success,

        #[OA\Property(property: 'message', type: 'string', example: 'Operation successful', description: 'Response message')]
        public ?string $message = null,

        #[OA\Property(property: 'cart', type: 'object', description: 'Cart data (when applicable)')]
        public ?CartResponseDTO $cart = null,

        #[OA\Property(property: 'order', type: 'object', description: 'Order data (when applicable)')]
        public ?OrderResponseDTO $order = null,

        #[OA\Property(property: 'count', type: 'integer', example: 3, description: 'Item count (when applicable)')]
        public ?int $count = null
    ) {}

    public function toArray(): array
    {
        $data = [
            'success' => $this->success
        ];

        if ($this->message !== null) {
            $data['message'] = $this->message;
        }

        if ($this->cart !== null) {
            $data['cart'] = $this->cart->toArray();
        }

        if ($this->order !== null) {
            $data['order'] = $this->order->toArray();
        }

        if ($this->count !== null) {
            $data['count'] = $this->count;
        }

        return $data;
    }
}

