<?php

namespace App\DTO;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'ApiResponse',
    description: 'Standard API response',
    type: 'object',
    example: [
        'success' => true,
        'message' => 'Opération réussie',
        'data' => ['items' => ['sample' => 'value']],
        'count' => 1,
    ]
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
        public ?int $count = null,

        #[OA\Property(property: 'data', type: 'object', description: 'Generic payload container for non-cart/order responses', example: ['key' => 'value'])]
        public ?array $data = null,

        #[OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'), description: 'Validation or processing errors', example: ['Le nom est requis'])]
        public ?array $errors = null
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

        if ($this->data !== null) {
            $data['data'] = $this->data;
        }

        if ($this->errors !== null) {
            $data['errors'] = $this->errors;
        }

        return $data;
    }
}

