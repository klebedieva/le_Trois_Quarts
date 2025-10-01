<?php

namespace App\Controller;

use App\Service\CartService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use OpenApi\Attributes as OA;

#[Route('/api/cart')]
#[OA\Tag(name: 'Cart')]
class CartController extends AbstractController
{
    public function __construct(
        private CartService $cartService
    ) {}

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
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(
                    property: 'cart',
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items(
                            properties: [
                                new OA\Property(property: 'id', type: 'integer', example: 1),
                                new OA\Property(property: 'name', type: 'string', example: 'Risotto aux champignons'),
                                new OA\Property(property: 'price', type: 'number', format: 'float', example: 14.5),
                                new OA\Property(property: 'quantity', type: 'integer', example: 2),
                                new OA\Property(property: 'image', type: 'string', example: '/uploads/menu/plat_1.png'),
                                new OA\Property(property: 'category', type: 'string', example: 'plats')
                            ],
                            type: 'object'
                        )),
                        new OA\Property(property: 'total', type: 'number', format: 'float', example: 29.0),
                        new OA\Property(property: 'itemCount', type: 'integer', example: 2)
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    public function getCart(): JsonResponse
    {
        try {
            $cart = $this->cartService->getCart();
            
            return $this->json([
                'success' => true,
                'cart' => $cart
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la récupération du panier: ' . $e->getMessage()
            ], 500);
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
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Article ajouté au panier'),
                new OA\Property(
                    property: 'cart',
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items()),
                        new OA\Property(property: 'total', type: 'number', example: 29.0),
                        new OA\Property(property: 'itemCount', type: 'integer', example: 2)
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    #[OA\Response(
        response: 400,
        description: 'Invalid data',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'itemId est requis')
            ]
        )
    )]
    #[OA\Response(
        response: 404,
        description: 'Item not found',
        content: new OA\JsonContent(
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: false),
                new OA\Property(property: 'message', type: 'string', example: 'Menu item not found: 999')
            ]
        )
    )]
    public function addToCart(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['itemId'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'itemId est requis'
                ], 400);
            }

            $itemId = (int) $data['itemId'];
            $quantity = isset($data['quantity']) ? (int) $data['quantity'] : 1;

            if ($quantity < 1) {
                return $this->json([
                    'success' => false,
                    'message' => 'La quantité doit être au moins 1'
                ], 400);
            }

            $cart = $this->cartService->add($itemId, $quantity);

            return $this->json([
                'success' => true,
                'message' => 'Article ajouté au panier',
                'cart' => $cart
            ]);

        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de l\'ajout au panier: ' . $e->getMessage()
            ], 500);
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
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Article retiré du panier'),
                new OA\Property(
                    property: 'cart',
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items()),
                        new OA\Property(property: 'total', type: 'number', example: 0),
                        new OA\Property(property: 'itemCount', type: 'integer', example: 0)
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    public function removeFromCart(int $id): JsonResponse
    {
        try {
            $cart = $this->cartService->remove($id);

            return $this->json([
                'success' => true,
                'message' => 'Article retiré du panier',
                'cart' => $cart
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la suppression: ' . $e->getMessage()
            ], 500);
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
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Quantité mise à jour'),
                new OA\Property(
                    property: 'cart',
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items()),
                        new OA\Property(property: 'total', type: 'number', example: 43.5),
                        new OA\Property(property: 'itemCount', type: 'integer', example: 3)
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    public function updateQuantity(int $id, Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            
            if (!isset($data['quantity'])) {
                return $this->json([
                    'success' => false,
                    'message' => 'quantity est requis'
                ], 400);
            }

            $quantity = (int) $data['quantity'];
            $cart = $this->cartService->updateQuantity($id, $quantity);

            return $this->json([
                'success' => true,
                'message' => 'Quantité mise à jour',
                'cart' => $cart
            ]);
        } catch (\InvalidArgumentException $e) {
            return $this->json([
                'success' => false,
                'message' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors de la mise à jour: ' . $e->getMessage()
            ], 500);
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
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'message', type: 'string', example: 'Panier vidé'),
                new OA\Property(
                    property: 'cart',
                    properties: [
                        new OA\Property(property: 'items', type: 'array', items: new OA\Items()),
                        new OA\Property(property: 'total', type: 'number', example: 0),
                        new OA\Property(property: 'itemCount', type: 'integer', example: 0)
                    ],
                    type: 'object'
                )
            ]
        )
    )]
    public function clearCart(): JsonResponse
    {
        try {
            $cart = $this->cartService->clear();

            return $this->json([
                'success' => true,
                'message' => 'Panier vidé',
                'cart' => $cart
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur lors du vidage du panier: ' . $e->getMessage()
            ], 500);
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
            properties: [
                new OA\Property(property: 'success', type: 'boolean', example: true),
                new OA\Property(property: 'count', type: 'integer', example: 3, description: 'Total number of items')
            ]
        )
    )]
    public function getCartCount(): JsonResponse
    {
        try {
            $count = $this->cartService->getItemCount();

            return $this->json([
                'success' => true,
                'count' => $count
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Erreur: ' . $e->getMessage()
            ], 500);
        }
    }
}

