<?php

namespace App\Service;

use App\Repository\MenuItemRepository;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service pour gérer le panier de commande.
 * Stocke les données dans la session utilisateur.
 */
class CartService
{
    private const CART_SESSION_KEY = 'cart';

    public function __construct(
        private RequestStack $requestStack,
        private MenuItemRepository $menuItemRepository
    ) {}

    /**
     * Ajouter un article au panier
     */
    public function add(int $menuItemId, int $quantity = 1): array
    {
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);

        // Si l'article existe déjà, augmenter la quantité
        if (isset($cart[$menuItemId])) {
            $cart[$menuItemId]['quantity'] += $quantity;
        } else {
            // Récupérer les détails du produit
            $menuItem = $this->menuItemRepository->find($menuItemId);
            
            if (!$menuItem) {
                throw new \InvalidArgumentException("Menu item not found: $menuItemId");
            }

            $cart[$menuItemId] = [
                'id' => $menuItem->getId(),
                'name' => $menuItem->getName(),
                'price' => (float) $menuItem->getPrice(),
                'image' => $this->resolveImagePath($menuItem->getImage()),
                'category' => $menuItem->getCategory(),
                'quantity' => $quantity,
            ];
        }

        $session->set(self::CART_SESSION_KEY, $cart);
        
        return $this->getCartDetails($cart);
    }

    /**
     * Retirer un article du panier
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
     * Mettre à jour la quantité d'un article
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
     * Obtenir le contenu du panier avec les détails
     */
    public function getCart(): array
    {
        $session = $this->requestStack->getSession();
        $cart = $session->get(self::CART_SESSION_KEY, []);
        
        return $this->getCartDetails($cart);
    }

    /**
     * Vider le panier
     */
    public function clear(): array
    {
        $session = $this->requestStack->getSession();
        $session->remove(self::CART_SESSION_KEY);
        
        return $this->getCartDetails([]);
    }

    /**
     * Obtenir le nombre total d'articles dans le panier
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
     * Calculer le total du panier
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
     * Formater les détails du panier pour l'API
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
     * Résoudre le chemin de l'image
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

