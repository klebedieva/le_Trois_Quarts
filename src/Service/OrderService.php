<?php

namespace App\Service;

use App\Entity\Order;
use App\Entity\OrderItem;
use App\Enum\DeliveryMode;
use App\Enum\OrderStatus;
use App\Enum\PaymentMode;
use App\Repository\OrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * Service pour gérer les commandes.
 */
class OrderService
{
    private const TAX_RATE = 0.10; // 10% tax

    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private CartService $cartService,
        private RequestStack $requestStack
    ) {}

    /**
     * Créer une nouvelle commande à partir du panier
     */
    public function createOrder(array $orderData): Order
    {
        // Récupérer le panier
        $cart = $this->cartService->getCart();
        
        if (empty($cart['items'])) {
            throw new \InvalidArgumentException("Le panier est vide");
        }

        // Créer l'entité Order
        $order = new Order();
        $order->setNo($this->generateOrderNumber());
        $order->setStatus(OrderStatus::PENDING);
        $order->setCreatedAt(new \DateTimeImmutable());

        // Définir le mode de livraison
        $deliveryMode = isset($orderData['deliveryMode']) 
            ? DeliveryMode::from($orderData['deliveryMode'])
            : DeliveryMode::DELIVERY;
        $order->setDeliveryMode($deliveryMode);

        // Définir l'adresse de livraison si le mode est delivery
        if ($deliveryMode === DeliveryMode::DELIVERY) {
            if (empty($orderData['deliveryAddress'])) {
                throw new \InvalidArgumentException("L'adresse de livraison est requise");
            }
            $order->setDeliveryAddress($orderData['deliveryAddress']);
            $order->setDeliveryZip($orderData['deliveryZip'] ?? null);
            $order->setDeliveryInstructions($orderData['deliveryInstructions'] ?? null);
            $order->setDeliveryFee($orderData['deliveryFee'] ?? '5.00');
        } else {
            $order->setDeliveryFee('0.00');
        }

        // Définir le mode de paiement
        $paymentMode = isset($orderData['paymentMode']) 
            ? PaymentMode::from($orderData['paymentMode'])
            : PaymentMode::CARD;
        $order->setPaymentMode($paymentMode);

        // Calculer les montants
        $subtotal = $cart['total'];
        $taxAmount = round($subtotal * self::TAX_RATE, 2);
        $deliveryFee = (float) $order->getDeliveryFee();
        $total = $subtotal + $taxAmount + $deliveryFee;

        $order->setSubtotal((string) $subtotal);
        $order->setTaxAmount((string) $taxAmount);
        $order->setTotal((string) $total);

        // Ajouter les items de commande
        foreach ($cart['items'] as $cartItem) {
            $orderItem = new OrderItem();
            $orderItem->setProductId($cartItem['id']);
            $orderItem->setProductName($cartItem['name']);
            $orderItem->setUnitPrice((string) $cartItem['price']);
            $orderItem->setQuantity($cartItem['quantity']);
            $orderItem->setTotal((string) ($cartItem['price'] * $cartItem['quantity']));
            $orderItem->setOrderRef($order);
            
            $order->addItem($orderItem);
        }

        // Persister la commande
        $this->entityManager->persist($order);
        $this->entityManager->flush();

        // Vider le panier après la création de la commande
        $this->cartService->clear();

        return $order;
    }

    /**
     * Récupérer une commande par ID
     */
    public function getOrder(int $orderId): ?Order
    {
        return $this->orderRepository->find($orderId);
    }

    /**
     * Générer un numéro de commande unique
     */
    private function generateOrderNumber(): string
    {
        $date = (new \DateTime())->format('Ymd');
        $random = str_pad((string) random_int(1, 9999), 4, '0', STR_PAD_LEFT);
        return "ORD-{$date}-{$random}";
    }

    /**
     * Mettre à jour le statut d'une commande
     */
    public function updateOrderStatus(int $orderId, string $status): Order
    {
        $order = $this->getOrder($orderId);
        
        if (!$order) {
            throw new \InvalidArgumentException("Commande introuvable: $orderId");
        }

        $orderStatus = OrderStatus::from($status);
        $order->setStatus($orderStatus);
        
        $this->entityManager->flush();
        
        return $order;
    }
}

