<?php

namespace App\Controller;

use App\DTO\ApiResponseDTO;
use App\DTO\CartItemDTO;
use App\DTO\CartResponseDTO;
use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use OpenApi\Attributes as OA;

#[Route('/api/cart')]
#[OA\Tag(name: 'Cart')]
class CartController extends AbstractController
{
    public function __construct(
        private CartService $cartService
    ) {}

    private function validateCsrfToken(Request $request, CsrfTokenManagerInterface $csrfTokenManager): ?JsonResponse
    {
        $csrfToken = $request->headers->get('X-CSRF-Token');
        if (!$csrfToken || !$csrfTokenManager->isTokenValid(new CsrfToken('submit', $csrfToken))) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Token CSRF invalide'
            ], 403);
        }
        return null;
    }

    /**
     * Get cart contents
     */
    #[Route('', name: 'api_cart_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/cart',
        summary: 'Get cart',
        description: 'Returns the contents of the current session cart',
        tags: ['Cart']
    )]
    #[OA\Response(
        response: 200,
        description: 'Successful response',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Operation successful'),
                new OA\Property(property: 'cart', type: 'object', description: 'Cart data'),
                new OA\Property(property: 'count', type: 'integer', example: 3)
            ]
        )
    )]
    public function getCart(): JsonResponse
    {
        try {
            $cart = $this->cartService->getCart();
            
            // Convert cart items to DTOs
            $cartItems = array_map(function($item) {
                return new CartItemDTO(
                    id: $item['id'],
                    name: $item['name'],
                    price: $item['price'],
                    quantity: $item['quantity'],
                    image: $item['image'],
                    category: $item['category']
                );
            }, $cart['items']);

            $cartResponse = new CartResponseDTO(
                items: $cartItems,
                total: $cart['total'],
                itemCount: $cart['itemCount']
            );

            $response = new ApiResponseDTO(
                success: true,
                cart: $cartResponse
            );
            
            return $this->json($response->toArray());
        } catch (\Exception $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: 'Erreur lors de la récupération du panier: ' . $e->getMessage()
            );
            return $this->json($response->toArray(), 500);
        }
    }

    /**
     * Add item to cart
     */
    #[Route('/add', name: 'api_cart_add', methods: ['POST'])]
    #[OA\Post(
        path: '/api/cart/add',
        summary: 'Add item to cart',
        description: 'Adds an item to the cart or increases quantity if item already exists',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'itemId', type: 'integer', example: 1, description: 'Menu item ID from database'),
                    new OA\Property(property: 'quantity', type: 'integer', example: 1, description: 'Quantity (default: 1)')
                ],
                type: 'object'
            )
        ),
        tags: ['Cart']
    )]
    #[OA\Response(
        response: 200,
        description: 'Item added successfully',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Operation successful'),
                new OA\Property(property: 'cart', type: 'object', description: 'Cart data'),
                new OA\Property(property: 'count', type: 'integer', example: 3)
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid data',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Operation successful'),
                new OA\Property(property: 'cart', type: 'object', description: 'Cart data'),
                new OA\Property(property: 'count', type: 'integer', example: 3)
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Item not found',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Operation successful'),
                new OA\Property(property: 'cart', type: 'object', description: 'Cart data'),
                new OA\Property(property: 'count', type: 'integer', example: 3)
            ]
        )
    )]
    public function addToCart(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        // CSRF Protection (disabled in dev environment via security.yaml)
        if ($this->getParameter('kernel.environment') === 'prod') {
            $csrfError = $this->validateCsrfToken($request, $csrfTokenManager);
            if ($csrfError) {
                return $csrfError;
            }
        }

        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['itemId'])) {
                $response = new ApiResponseDTO(
                    success: false,
                    message: 'itemId est requis'
                );
                return $this->json($response->toArray(), 400);
            }

            $itemId = (int) $data['itemId'];
            $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 1;

            if ($quantity < 1) {
                $response = new ApiResponseDTO(
                    success: false,
                    message: 'La quantité doit être au moins 1'
                );
                return $this->json($response->toArray(), 400);
            }

            $cart = $this->cartService->add($itemId, $quantity);

            // Convert cart items to DTOs
            $cartItems = array_map(function($item) {
                return new CartItemDTO(
                    id: $item['id'],
                    name: $item['name'],
                    price: $item['price'],
                    quantity: $item['quantity'],
                    image: $item['image'],
                    category: $item['category']
                );
            }, $cart['items']);

            $cartResponse = new CartResponseDTO(
                items: $cartItems,
                total: $cart['total'],
                itemCount: $cart['itemCount']
            );

            $response = new ApiResponseDTO(
                success: true,
                message: 'Article ajouté au panier',
                cart: $cartResponse
            );

            return $this->json($response->toArray());

        } catch (\InvalidArgumentException $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: $e->getMessage()
            );
            return $this->json($response->toArray(), 404);
        } catch (\Exception $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: 'Erreur lors de l\'ajout au panier: ' . $e->getMessage()
            );
            return $this->json($response->toArray(), 500);
        }
    }

    /**
     * Remove item from cart
     */
    #[Route('/remove/{id}', name: 'api_cart_remove', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/cart/remove/{id}',
        summary: 'Remove item',
        description: 'Completely removes an item from the cart',
        tags: ['Cart']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Item ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Item removed successfully',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Operation successful'),
                new OA\Property(property: 'cart', type: 'object', description: 'Cart data'),
                new OA\Property(property: 'count', type: 'integer', example: 3)
            ]
        )
    )]
    public function removeFromCart(int $id, Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        // CSRF Protection (disabled in dev environment via security.yaml)
        if ($this->getParameter('kernel.environment') === 'prod') {
            $csrfError = $this->validateCsrfToken($request, $csrfTokenManager);
            if ($csrfError) {
                return $csrfError;
            }
        }

        try {
            $cart = $this->cartService->remove($id);

            // Convert cart items to DTOs
            $cartItems = array_map(function($item) {
                return new CartItemDTO(
                    id: $item['id'],
                    name: $item['name'],
                    price: $item['price'],
                    quantity: $item['quantity'],
                    image: $item['image'],
                    category: $item['category']
                );
            }, $cart['items']);

            $cartResponse = new CartResponseDTO(
                items: $cartItems,
                total: $cart['total'],
                itemCount: $cart['itemCount']
            );

            $response = new ApiResponseDTO(
                success: true,
                message: 'Article retiré du panier',
                cart: $cartResponse
            );

            return $this->json($response->toArray());
        } catch (\InvalidArgumentException $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: $e->getMessage()
            );
            return $this->json($response->toArray(), 404);
        } catch (\Exception $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: 'Erreur lors de la suppression: ' . $e->getMessage()
            );
            return $this->json($response->toArray(), 500);
        }
    }

    /**
     * Update item quantity
     */
    #[Route('/update/{id}', name: 'api_cart_update', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/cart/update/{id}',
        summary: 'Update quantity',
        description: 'Updates item quantity. If quantity = 0, item is removed',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(property: 'quantity', type: 'integer', example: 3, description: 'New quantity')
                ],
                type: 'object'
            )
        ),
        tags: ['Cart']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Item ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(
        response: 200,
        description: 'Quantity updated successfully',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Operation successful'),
                new OA\Property(property: 'cart', type: 'object', description: 'Cart data'),
                new OA\Property(property: 'count', type: 'integer', example: 3)
            ]
        )
    )]
    public function updateQuantity(int $id, Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        // CSRF Protection (disabled in dev environment via security.yaml)
        if ($this->getParameter('kernel.environment') === 'prod') {
            $csrfError = $this->validateCsrfToken($request, $csrfTokenManager);
            if ($csrfError) {
                return $csrfError;
            }
        }

        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['quantity'])) {
                $response = new ApiResponseDTO(
                    success: false,
                    message: 'quantity est requis'
                );
                return $this->json($response->toArray(), 400);
            }

            $quantity = (int) $data['quantity'];
            $cart = $this->cartService->updateQuantity($id, $quantity);

            // Convert cart items to DTOs
            $cartItems = array_map(function($item) {
                return new CartItemDTO(
                    id: $item['id'],
                    name: $item['name'],
                    price: $item['price'],
                    quantity: $item['quantity'],
                    image: $item['image'],
                    category: $item['category']
                );
            }, $cart['items']);

            $cartResponse = new CartResponseDTO(
                items: $cartItems,
                total: $cart['total'],
                itemCount: $cart['itemCount']
            );

            $response = new ApiResponseDTO(
                success: true,
                message: 'Quantité mise à jour',
                cart: $cartResponse
            );

            return $this->json($response->toArray());
        } catch (\InvalidArgumentException $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: $e->getMessage()
            );
            return $this->json($response->toArray(), 404);
        } catch (\Exception $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: 'Erreur lors de la mise à jour: ' . $e->getMessage()
            );
            return $this->json($response->toArray(), 500);
        }
    }

    /**
     * Clear cart
     */
    #[Route('/clear', name: 'api_cart_clear', methods: ['POST'])]
    #[OA\Post(
        path: '/api/cart/clear',
        summary: 'Clear cart',
        description: 'Removes all items from the cart',
        tags: ['Cart']
    )]
    #[OA\Response(
        response: 200,
        description: 'Cart cleared successfully',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Operation successful'),
                new OA\Property(property: 'cart', type: 'object', description: 'Cart data'),
                new OA\Property(property: 'count', type: 'integer', example: 3)
            ]
        )
    )]
    public function clearCart(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        // CSRF Protection (disabled in dev environment via security.yaml)
        if ($this->getParameter('kernel.environment') === 'prod') {
            $csrfError = $this->validateCsrfToken($request, $csrfTokenManager);
            if ($csrfError) {
                return $csrfError;
            }
        }

        try {
            $cart = $this->cartService->clear();

            // Convert cart items to DTOs
            $cartItems = array_map(function($item) {
                return new CartItemDTO(
                    id: $item['id'],
                    name: $item['name'],
                    price: $item['price'],
                    quantity: $item['quantity'],
                    image: $item['image'],
                    category: $item['category']
                );
            }, $cart['items']);

            $cartResponse = new CartResponseDTO(
                items: $cartItems,
                total: $cart['total'],
                itemCount: $cart['itemCount']
            );

            $response = new ApiResponseDTO(
                success: true,
                message: 'Panier vidé',
                cart: $cartResponse
            );

            return $this->json($response->toArray());
        } catch (\Exception $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: 'Erreur lors du vidage du panier: ' . $e->getMessage()
            );
            return $this->json($response->toArray(), 500);
        }
    }

    /**
     * Get item count
     */
    #[Route('/count', name: 'api_cart_count', methods: ['GET'])]
    #[OA\Get(
        path: '/api/cart/count',
        summary: 'Get item count',
        description: 'Returns the total number of items in the cart (useful for badge updates)',
        tags: ['Cart']
    )]
    #[OA\Response(
        response: 200,
        description: 'Successful response',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Operation successful'),
                new OA\Property(property: 'cart', type: 'object', description: 'Cart data'),
                new OA\Property(property: 'count', type: 'integer', example: 3)
            ]
        )
    )]
    public function getCartCount(): JsonResponse
    {
        try {
            $count = $this->cartService->getItemCount();

            $response = new ApiResponseDTO(
                success: true,
                count: $count
            );

            return $this->json($response->toArray());
        } catch (\Exception $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: 'Erreur: ' . $e->getMessage()
            );
            return $this->json($response->toArray(), 500);
        }
    }
}

