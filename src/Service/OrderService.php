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
    // TAX_RATE moved to RestaurantSettingsService

    public function __construct(
        private EntityManagerInterface $entityManager,
        private OrderRepository $orderRepository,
        private CartService $cartService,
        private RequestStack $requestStack,
        private RestaurantSettingsService $restaurantSettings,
        private AddressValidationService $addressValidationService
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
            
            // Validation de l'adresse complète pour la livraison
            $deliveryZip = $orderData['deliveryZip'] ?? null;
            $addressValidation = $this->addressValidationService->validateAddressForDelivery($orderData['deliveryAddress'], $deliveryZip);
            if (!$addressValidation['valid']) {
                throw new \InvalidArgumentException($addressValidation['error'] ?? 'Livraison non disponible pour cette adresse');
            }
            
            $order->setDeliveryAddress($orderData['deliveryAddress']);
            $order->setDeliveryZip($deliveryZip);
            $order->setDeliveryInstructions($orderData['deliveryInstructions'] ?? null);
            $order->setDeliveryFee($orderData['deliveryFee'] ?? number_format($this->restaurantSettings->getDeliveryFee(), 2, '.', ''));
        } else {
            $order->setDeliveryFee('0.00');
        }

        // Définir le mode de paiement
        $paymentMode = isset($orderData['paymentMode']) 
            ? PaymentMode::from($orderData['paymentMode'])
            : PaymentMode::CARD;
        $order->setPaymentMode($paymentMode);

        // Définir les informations client
        $order->setClientFirstName($orderData['clientFirstName'] ?? null);
        $order->setClientLastName($orderData['clientLastName'] ?? null);
        
        // Validation du numéro de téléphone français
        $clientPhone = $orderData['clientPhone'] ?? null;
        if ($clientPhone && !$this->validateFrenchPhoneNumber($clientPhone)) {
            throw new \InvalidArgumentException("Numéro de téléphone invalide");
        }
        $order->setClientPhone($clientPhone);
        
        $order->setClientEmail($orderData['clientEmail'] ?? null);
        
        // Générer le nom complet automatiquement si possible
        if ($order->getClientFirstName() && $order->getClientLastName()) {
            $order->setClientName($order->getClientFirstName() . ' ' . $order->getClientLastName());
        }

        // Calculer les montants
        // Цены в корзине уже включают налоги (TTC)
        $subtotalWithTax = $cart['total'];
        $taxRate = $this->restaurantSettings->getVatRate();
        $subtotalWithoutTax = $subtotalWithTax / (1 + $taxRate);
        $taxAmount = $subtotalWithTax - $subtotalWithoutTax;
        $deliveryFee = (float) $order->getDeliveryFee();
        $total = $subtotalWithTax + $deliveryFee;

        $order->setSubtotal(number_format($subtotalWithoutTax, 2, '.', ''));
        $order->setTaxAmount(number_format($taxAmount, 2, '.', ''));
        $order->setTotal(number_format($total, 2, '.', ''));

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

    /**
     * Valider un numéro de téléphone français
     */
    private function validateFrenchPhoneNumber(string $phone): bool
    {
        if (empty($phone)) {
            return false;
        }

        // Nettoyer le numéro (supprimer espaces, tirets, points)
        $cleanPhone = preg_replace('/[\s\-\.]/', '', $phone);

        // Vérifier d'abord la longueur et le format général
        // Format national: 0X XXXX XXXX (10 chiffres au total, commence par 0)
        // Format international: +33 X XX XX XX XX (12 caractères, commence par +33)

        if (strlen($cleanPhone) === 10 && str_starts_with($cleanPhone, '0')) {
            // Format national français: 0X XXXX XXXX
            if (!preg_match('/^0[1-9]\d{8}$/', $cleanPhone)) {
                return false;
            }
            
            // Vérifier les premiers chiffres pour les mobiles (06, 07) et fixes (01-05)
            $firstTwoDigits = substr($cleanPhone, 0, 2);
            $validPrefixes = ['06', '07', '01', '02', '03', '04', '05'];
            return in_array($firstTwoDigits, $validPrefixes, true);
            
        } elseif (strlen($cleanPhone) === 12 && str_starts_with($cleanPhone, '+33')) {
            // Format international: +33 X XX XX XX XX
            if (!preg_match('/^\+33[1-9]\d{8}$/', $cleanPhone)) {
                return false;
            }
            
            // Extraire le numéro sans l'indicatif pays (+33)
            $withoutCountryCode = substr($cleanPhone, 3); // Supprimer '+33'
            
            // Vérifier les premiers chiffres pour les mobiles (06, 07) et fixes (01-05)
            $firstTwoDigits = substr($withoutCountryCode, 0, 2);
            $validPrefixes = ['06', '07', '01', '02', '03', '04', '05'];
            return in_array($firstTwoDigits, $validPrefixes, true);
        }

        // Si ni 10 chiffres avec 0, ni 12 caractères avec +33, alors invalide
        return false;
    }
}

