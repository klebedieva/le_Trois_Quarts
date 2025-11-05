<?php

namespace App\Service;

use App\Repository\MenuItemRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Shopping Cart Service
 *
 * Manages the user's shopping cart state using Symfony session storage.
 * Provides methods to add, remove, update quantities, and retrieve cart contents.
 * All cart operations are session-based and do not persist to database
 * until an order is created.
 *
 * Cart structure:
 * - Stored in session under 'cart' key
 * - Format: [menuItemId => ['id', 'name', 'price', 'image', 'category', 'quantity']]
 * - Automatically calculates totals and item counts
 *
 * Features:
 * - Automatic quantity increment when adding existing items
 * - Image path resolution (handles relative/absolute paths)
 * - Cart total and item count calculations
 * - Session-based persistence (per-user cart)
 */
class CartService
{
    private const CART_SESSION_KEY = 'cart';

    public function __construct(
        private RequestStack $requestStack,
        private MenuItemRepository $menuItemRepository
    ) {}

    /**
     * Add item to cart or increase quantity if item already exists
     *
     * If the menu item ID already exists in the cart, its quantity is incremented.
     * Otherwise, a new cart entry is created with the specified quantity.
     * Fetches menu item details from database to populate cart entry.
     *
     * @param int $menuItemId Menu item ID from database
     * @param int $quantity Quantity to add (default: 1)
     * @return array Updated cart details with items, total, and itemCount
     * @throws \InvalidArgumentException If menu item not found
     */
    public function add(int $menuItemId, int $quantity = 1): array
    {
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);

        // If item already exists, increment quantity
        if (isset($cart[$menuItemId])) {
            $cart[$menuItemId]['quantity'] += $quantity;
        } else {
            // Fetch menu item details from database
            $menuItem = $this->menuItemRepository->find($menuItemId);
            
            if (!$menuItem) {
                throw new \InvalidArgumentException("Menu item not found: $menuItemId");
            }

            // Create new cart entry with item details
            $cart[$menuItemId] = [
                'id' => $menuItem->getId(),
                'name' => $menuItem->getName(),
                'price' => (float) $menuItem->getPrice(),
                'image' => $this->resolveImagePath($menuItem->getImage()),
                'category' => $menuItem->getCategory(),
                'quantity' => $quantity,
            ];
        }

        // Persist updated cart to session
        $session->set(self::CART_SESSION_KEY, $cart);
        
        return $this->getCartDetails($cart);
    }

    /**
     * Remove item completely from cart
     *
     * Removes the item with the given menu item ID from the cart entirely.
     * This is different from setting quantity to 0 (which also removes it).
     *
     * @param int $menuItemId Menu item ID to remove
     * @return array Updated cart details after removal
     * @throws \InvalidArgumentException If item not found in cart
     */
    public function remove(int $menuItemId): array
    {
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);

        if (!isset($cart[$menuItemId])) {
            throw new \InvalidArgumentException("Cart item not found: $menuItemId");
        }

        unset($cart[$menuItemId]);
        $session->set(self::CART_SESSION_KEY, $cart);

        return $this->getCartDetails($cart);
    }

    /**
     * Update item quantity in cart
     *
     * Updates the quantity of an existing cart item. If quantity is set to 0,
     * the item is removed from the cart (equivalent to remove operation).
     *
     * @param int $menuItemId Menu item ID to update
     * @param int $quantity New quantity (0 removes the item)
     * @return array Updated cart details after quantity change
     * @throws \InvalidArgumentException If item not found in cart
     */
    public function updateQuantity(int $menuItemId, int $quantity): array
    {
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);

        if (!isset($cart[$menuItemId])) {
            throw new \InvalidArgumentException("Cart item not found: $menuItemId");
        }

        if ($quantity <= 0) {
            unset($cart[$menuItemId]);
        } else {
            $cart[$menuItemId]['quantity'] = $quantity;
        }
        $session->set(self::CART_SESSION_KEY, $cart);

        return $this->getCartDetails($cart);
    }

    /**
     * Get current cart contents with calculated totals
     *
     * Returns complete cart state including all items, total price,
     * and total item count. Used by API endpoints to return cart data.
     *
     * @return array Cart details with items, total, and itemCount
     */
    public function getCart(): array
    {
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);
        
        return $this->getCartDetails($cart);
    }

    /**
     * Clear entire cart (remove all items)
     *
     * Removes all items from the cart, effectively emptying it.
     * Useful for order completion or user-initiated cart reset.
     *
     * @return array Empty cart structure with zero totals
     */
    public function clear(): array
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::CART_SESSION_KEY);
        
        return $this->getCartDetails([]);
    }

    /**
     * Get total item count in cart
     *
     * Returns the sum of all item quantities (not the number of unique items).
     * For example, if cart has 2x Item A and 3x Item B, returns 5.
     *
     * @return int Total quantity of all items in cart
     */
    public function getItemCount(): int
    {
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);
        
        $count = 0;
        foreach ($cart as $item) {
            $count += $item['quantity'];
        }
        
        return $count;
    }

    /**
     * Calculate cart total price
     *
     * Sums up all item prices multiplied by their quantities.
     * Result is rounded to 2 decimal places for currency precision.
     *
     * @return float Total cart price rounded to 2 decimals
     */
    public function getTotal(): float
    {
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);
        
        $total = 0;
        foreach ($cart as $item) {
            $total += $item['price'] * $item['quantity'];
        }
        
        return round($total, 2);
    }

    /**
     * Format cart details for API response
     *
     * Calculates totals and item count from cart array.
     * Converts cart associative array to indexed array of items.
     *
     * @param array $cart Cart array from session
     * @return array Formatted cart with items, total, and itemCount
     */
    private function getCartDetails(array $cart): array
    {
        $items = array_values($cart);
        $total = 0;
        $itemCount = 0;

        foreach ($items as $item) {
            $total += $item['price'] * $item['quantity'];
            $itemCount += $item['quantity'];
        }

        return [
            'items' => $items,
            'total' => round($total, 2),
            'itemCount' => $itemCount,
        ];
    }

    /**
     * Resolve image path to absolute URL or relative path
     *
     * Handles various image path formats:
     * - Absolute URLs (http/https) - returned as-is
     * - Absolute paths starting with /uploads/ or /assets/ - returned as-is
     * - Relative paths starting with assets/ - prepended with /
     * - Other paths - prepended with /uploads/menu/
     * - Null/empty - returns default placeholder image
     *
     * @param string|null $image Image path from database
     * @return string Resolved image path for frontend use
     */
    private function resolveImagePath(?string $image): string
    {
        if (!$image) {
            return '/assets/img/default-dish.png';
        }

        if (str_starts_with($image, 'http')) {
            return $image;
        }

        if (str_starts_with($image, '/uploads/') || str_starts_with($image, '/assets/')) {
            return $image;
        }

        if (str_starts_with($image, 'assets/')) {
            return '/' . $image;
        }

        return '/uploads/menu/' . ltrim($image, '/');
    }
}

