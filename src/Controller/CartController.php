<?php

namespace App\Controller;

use App\DTO\CartAddRequest;
use App\DTO\CartItemDTO;
use App\DTO\CartResponseDTO;
use App\Service\CartService;
use App\Service\ValidationHelper;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use OpenApi\Attributes as OA;

/**
 * Shopping Cart API Controller
 * 
 * Handles all cart operations for the restaurant ordering system:
 * - Get cart contents and item count
 * - Add items to cart
 * - Remove items from cart
 * - Update item quantities
 * - Clear entire cart
 * 
 * Architecture:
 * - Extends AbstractApiController for common API functionality (JSON parsing, DTO validation, CSRF, responses)
 * - Uses CartService for business logic (cart operations)
 * - CSRF protection is enforced in production environment
 * 
 * All operations use CartService for business logic and return DTOs for consistent API responses.
 */
#[Route('/api/cart')]
#[OA\Tag(name: 'Cart')]
class CartController extends AbstractApiController
{
    /**
     * Constructor
     *
     * Injects dependencies required for cart operations:
     * - CartService: Handles cart business logic (add, remove, update, clear)
     * - ValidatorInterface and ValidationHelper: Passed to parent for DTO validation
     *
     * @param CartService $cartService Service for cart operations
     * @param ValidatorInterface $validator Symfony validator for DTO validation
     * @param ValidationHelper $validationHelper Helper for validation operations
     */
    public function __construct(
        private CartService $cartService,
        ValidatorInterface $validator,
        ValidationHelper $validationHelper
    ) {
        parent::__construct($validator, $validationHelper);
    }

    /**
     * Get current cart contents
     * 
     * Returns all items in the cart along with totals and item count.
     * Cart data is stored in session and managed by CartService.
     * 
     * @return JsonResponse Cart contents with items, total, and item count
     */
    #[Route('', name: 'api_cart_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/cart',
        summary: 'Get cart',
        description: 'Returns the contents of the current session cart',
        tags: ['Cart']
    )]
    #[OA\Response(response: 200, description: 'Successful response', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'cart', type: 'object', nullable: true), new OA\Property(property: 'count', type: 'integer', nullable: true)]))]
    public function getCart(): JsonResponse
    {
        try {
            // Retrieve cart data from session via CartService
            $cart = $this->cartService->getCart();
            
            // Convert cart items array to DTOs for consistent API response format
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

            // Build structured cart response DTO
            $cartResponse = new CartResponseDTO(
                items: $cartItems,
                total: $cart['total'],
                itemCount: $cart['itemCount']
            );

            // Uses base class method from AbstractApiController
            return $this->successResponse(['cart' => $cartResponse], null, 200);
        } catch (\Exception $e) {
            // Return error response if cart retrieval fails
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Erreur lors de la récupération du panier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Add item to cart or increase quantity if item already exists
     * 
     * If the item (by ID) already exists in the cart, its quantity is increased.
     * Otherwise, a new cart item is added.
     * 
     * @param Request $request HTTP request containing itemId and optional quantity
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF token manager
     * @return JsonResponse Updated cart contents
     */
    #[Route('/add', name: 'api_cart_add', methods: ['POST'])]
    /**
     * Add item to cart using validated DTO input
     *
     * Uses Symfony Validator with CartAddRequest DTO to validate payload.
     * Returns 422 and a list of validation errors when input is invalid.
     */
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
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string', nullable: true),
                new OA\Property(property: 'cart', type: 'object', nullable: true)
            ],
            example: ['success' => true, 'message' => 'Article ajouté au panier']
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid JSON',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string')
            ],
            example: ['success' => false, 'message' => 'JSON invalide']
        )
    )]
    #[OA\Response(
        response: 422,
        description: 'Validation error',
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'success', type: 'boolean'),
                new OA\Property(property: 'message', type: 'string'),
                new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))
            ],
            example: [
                'success' => false,
                'message' => 'Erreur de validation',
                'errors' => ['itemId est requis', 'quantity doit être >= 1']
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Item not found',
        content: new OA\JsonContent(
            type: 'object',
            properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')],
            example: ['success' => false, 'message' => 'Article introuvable']
        )
    )]
    public function addToCart(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        // CSRF protection is enforced in production environment only
        // (disabled in dev for easier testing via security.yaml configuration)
        if ($this->getParameter('kernel.environment') === 'prod') {
            // Uses base class method from AbstractApiController
            $csrfError = $this->validateCsrfToken($request, $csrfTokenManager);
            if ($csrfError) {
                return $csrfError;
            }
        }

        try {
            // Get JSON data from request
            // Uses base class method from AbstractApiController
            // Returns array or JsonResponse (error if JSON invalid)
            $jsonResult = $this->getJsonDataFromRequest($request);
            if ($jsonResult instanceof JsonResponse) {
                // JSON parsing failed, return error response
                return $jsonResult;
            }
            $data = $jsonResult;

            // Map JSON payload to DTO and validate
            // Uses base class method from AbstractApiController
            // Returns DTO or JsonResponse (error if validation fails)
            $validationResult = $this->validateDto($data, CartAddRequest::class);
            if ($validationResult instanceof JsonResponse) {
                // Validation failed, return error response
                return $validationResult;
            }
            $dto = $validationResult;
            
            // Post-processing: Set default quantity if not provided
            // If the client doesn't send a quantity, we default to 1 (add one item to cart).
            // This provides a better user experience - users can add items without specifying quantity.
            if ($dto->quantity === null) {
                $dto->quantity = 1;
            }

            // Extract validated values
            $itemId = $dto->itemId;
            $quantity = $dto->quantity ?? 1;

            // Add item to cart via service (handles existence check and quantity increment)
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

            // Uses base class method from AbstractApiController
            return $this->successResponse(['cart' => $cartResponse], 'Article ajouté au panier', 200);

        } catch (\InvalidArgumentException $e) {
            // Uses base class method from AbstractApiController
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Erreur lors de l\'ajout au panier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Remove item completely from cart
     * 
     * Removes the item with the given ID from the cart entirely.
     * This is different from setting quantity to 0 (which also removes it).
     * 
     * @param int $id Cart item ID to remove
     * @param Request $request HTTP request
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF token manager
     * @return JsonResponse Updated cart contents
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
    #[OA\Response(response: 200, description: 'Item removed successfully', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string', nullable: true), new OA\Property(property: 'cart', type: 'object', nullable: true)]))]
    #[OA\Response(response: 404, description: 'Item not found', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
    #[OA\Response(response: 500, description: 'Internal server error', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
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

            // Uses base class method from AbstractApiController
            return $this->successResponse(['cart' => $cartResponse], 'Article retiré du panier', 200);
        } catch (\InvalidArgumentException $e) {
            // Uses base class method from AbstractApiController
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Erreur lors de la suppression: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Update item quantity in cart
     * 
     * Updates the quantity of an existing cart item. If quantity is set to 0,
     * the item is removed from the cart (equivalent to remove operation).
     * 
     * @param int $id Cart item ID to update
     * @param Request $request HTTP request containing new quantity
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF token manager
     * @return JsonResponse Updated cart contents
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
    #[OA\Response(response: 200, description: 'Quantity updated successfully', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string', nullable: true), new OA\Property(property: 'cart', type: 'object', nullable: true)]))]
    #[OA\Response(response: 400, description: 'Invalid JSON', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
    #[OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))]))]
    #[OA\Response(response: 404, description: 'Item not found', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
    #[OA\Response(response: 500, description: 'Internal server error', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
    public function updateQuantity(int $id, Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        // CSRF Protection (disabled in dev environment via security.yaml)
        if ($this->getParameter('kernel.environment') === 'prod') {
            // Uses base class method from AbstractApiController
            $csrfError = $this->validateCsrfToken($request, $csrfTokenManager);
            if ($csrfError) {
                return $csrfError;
            }
        }

        try {
            // Get JSON data from request
            // Uses base class method from AbstractApiController
            // Returns array or JsonResponse (error if JSON invalid)
            $jsonResult = $this->getJsonDataFromRequest($request);
            if ($jsonResult instanceof JsonResponse) {
                // JSON parsing failed, return error response
                return $jsonResult;
            }
            $data = $jsonResult;

            // Map JSON payload to DTO and validate
            // Uses base class method from AbstractApiController
            // Returns DTO or JsonResponse (error if validation fails)
            $validationResult = $this->validateDto($data, \App\DTO\CartUpdateQuantityRequest::class);
            if ($validationResult instanceof JsonResponse) {
                // Validation failed, return error response
                return $validationResult;
            }
            $dto = $validationResult;

            $quantity = (int) $dto->quantity;
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

            // Uses base class method from AbstractApiController
            return $this->successResponse(['cart' => $cartResponse], 'Quantité mise à jour', 200);
        } catch (\InvalidArgumentException $e) {
            // Uses base class method from AbstractApiController
            return $this->errorResponse($e->getMessage(), 404);
        } catch (\Exception $e) {
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Erreur lors de la mise à jour: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Clear entire cart
     * 
     * Removes all items from the cart, effectively emptying it.
     * Useful for order completion or user-initiated cart reset.
     * 
     * @param Request $request HTTP request
     * @param CsrfTokenManagerInterface $csrfTokenManager CSRF token manager
     * @return JsonResponse Empty cart response
     */
    #[Route('/clear', name: 'api_cart_clear', methods: ['POST'])]
    #[OA\Post(
        path: '/api/cart/clear',
        summary: 'Clear cart',
        description: 'Removes all items from the cart',
        tags: ['Cart']
    )]
    #[OA\Response(response: 200, description: 'Cart cleared successfully', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string', nullable: true), new OA\Property(property: 'cart', type: 'object', nullable: true)]))]
    #[OA\Response(response: 500, description: 'Internal server error', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
    public function clearCart(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        // CSRF Protection (disabled in dev environment via security.yaml)
        if ($this->getParameter('kernel.environment') === 'prod') {
            // Uses base class method from AbstractApiController
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

            // Uses base class method from AbstractApiController
            return $this->successResponse(['cart' => $cartResponse], 'Panier vidé', 200);
        } catch (\Exception $e) {
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Erreur lors du vidage du panier: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get total item count in cart
     * 
     * Returns the total number of items in the cart (sum of all quantities).
     * Useful for updating cart badge counters in the UI without fetching full cart data.
     * 
     * @return JsonResponse Item count
     */
    #[Route('/count', name: 'api_cart_count', methods: ['GET'])]
    #[OA\Get(
        path: '/api/cart/count',
        summary: 'Get item count',
        description: 'Returns the total number of items in the cart (useful for badge updates)',
        tags: ['Cart']
    )]
    #[OA\Response(response: 200, description: 'Successful response', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'count', type: 'integer')]))]
    public function getCartCount(): JsonResponse
    {
        try {
            $count = $this->cartService->getItemCount();

            // Uses base class method from AbstractApiController
            return $this->successResponse(['count' => $count], null, 200);
        } catch (\Exception $e) {
            // Uses base class method from AbstractApiController
            return $this->errorResponse('Erreur: ' . $e->getMessage(), 500);
        }
    }
}

