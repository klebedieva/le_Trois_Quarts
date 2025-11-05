<?php

namespace App\Controller;

use App\DTO\ApiResponseDTO;
use App\DTO\OrderItemDTO;
use App\DTO\OrderResponseDTO;
use App\Service\InputSanitizer;
use App\Service\OrderService;
use App\Service\SymfonyEmailService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use OpenApi\Attributes as OA;

/**
 * Order API and page controller.
 *
 * - index(): renders the multi-step checkout page.
 * - createOrder(): validates input, delegates order creation to service, returns DTO.
 * - getOrder(): returns order data by id for clients needing confirmation/details.
 */
class OrderController extends AbstractController
{
    public function __construct(
        private OrderService $orderService,
        private SymfonyEmailService $emailService
    ) {}

    #[Route('/order', name: 'app_order')]
    public function index(): Response
    {
        return $this->render('pages/order.html.twig');
    }

    /**
     * Create a new order
     */
    #[Route('/api/order', name: 'api_order_create', methods: ['POST'])]
    #[OA\Post(
        path: '/api/order',
        summary: 'Create order',
        description: 'Creates a new order from the current cart contents',
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(
                properties: [
                    new OA\Property(
                        property: 'deliveryMode', 
                        type: 'string', 
                        enum: ['delivery', 'pickup'],
                        example: 'delivery', 
                        description: 'Delivery mode'
                    ),
                    new OA\Property(
                        property: 'deliveryAddress', 
                        type: 'string', 
                        example: '123 Main St', 
                        description: 'Delivery address (required if deliveryMode=delivery)'
                    ),
                    new OA\Property(
                        property: 'deliveryZip', 
                        type: 'string', 
                        example: '75001', 
                        description: 'Delivery ZIP code'
                    ),
                    new OA\Property(
                        property: 'deliveryInstructions', 
                        type: 'string', 
                        example: 'Ring doorbell', 
                        description: 'Delivery instructions'
                    ),
                    new OA\Property(
                        property: 'deliveryFee', 
                        type: 'number',
                        format: 'float',
                        example: 5.0, 
                        description: 'Delivery fee (default: 5.00 for delivery, 0.00 for pickup)'
                    ),
                    new OA\Property(
                        property: 'paymentMode', 
                        type: 'string',
                        enum: ['card', 'cash', 'tickets'],
                        example: 'card', 
                        description: 'Payment mode'
                    ),
                    new OA\Property(
                        property: 'clientFirstName', 
                        type: 'string', 
                        example: 'Jean', 
                        description: 'Client first name'
                    ),
                    new OA\Property(
                        property: 'clientLastName', 
                        type: 'string', 
                        example: 'Dupont', 
                        description: 'Client last name'
                    ),
                    new OA\Property(
                        property: 'clientPhone', 
                        type: 'string', 
                        example: '+33123456789', 
                        description: 'Client phone number'
                    ),
                    new OA\Property(
                        property: 'clientEmail', 
                        type: 'string', 
                        example: 'jean.dupont@email.com', 
                        description: 'Client email address'
                    )
                ],
                type: 'object'
            )
        ),
        tags: ['Order']
    )]
    #[OA\Response(response: 201, description: 'Order created successfully', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 400, description: 'Invalid data or empty cart', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 500, description: 'Internal server error', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Tag(name: 'Order')]
    public function createOrder(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        try {
            // CSRF Protection
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$csrfToken || !$csrfTokenManager->isTokenValid(new CsrfToken('submit', $csrfToken))) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token CSRF invalide'
                ], 403);
            }
            
            $data = json_decode($request->getContent(), true);
            
            // Sanitize input data to prevent XSS
            if (isset($data['deliveryAddress'])) {
                $data['deliveryAddress'] = InputSanitizer::sanitize($data['deliveryAddress']);
            }
            if (isset($data['deliveryZip'])) {
                $data['deliveryZip'] = InputSanitizer::sanitize($data['deliveryZip']);
            }
            if (isset($data['deliveryInstructions'])) {
                $data['deliveryInstructions'] = InputSanitizer::sanitize($data['deliveryInstructions']);
            }
            if (isset($data['clientFirstName'])) {
                $data['clientFirstName'] = InputSanitizer::sanitize($data['clientFirstName']);
            }
            if (isset($data['clientLastName'])) {
                $data['clientLastName'] = InputSanitizer::sanitize($data['clientLastName']);
            }
            if (isset($data['clientPhone'])) {
                $data['clientPhone'] = InputSanitizer::sanitize($data['clientPhone']);
            }
            if (isset($data['clientEmail'])) {
                $data['clientEmail'] = InputSanitizer::sanitize($data['clientEmail']);
            }
            
            // Check for XSS attempts
            $xssErrors = [];
            if (isset($data['deliveryAddress']) && InputSanitizer::containsXssAttempt($data['deliveryAddress'])) {
                $xssErrors[] = 'L\'adresse contient des éléments non autorisés';
            }
            if (isset($data['deliveryInstructions']) && InputSanitizer::containsXssAttempt($data['deliveryInstructions'])) {
                $xssErrors[] = 'Les instructions de livraison contiennent des éléments non autorisés';
            }
            if (isset($data['clientFirstName']) && InputSanitizer::containsXssAttempt($data['clientFirstName'])) {
                $xssErrors[] = 'Le prénom contient des éléments non autorisés';
            }
            if (isset($data['clientLastName']) && InputSanitizer::containsXssAttempt($data['clientLastName'])) {
                $xssErrors[] = 'Le nom contient des éléments non autorisés';
            }
            if (isset($data['clientPhone']) && InputSanitizer::containsXssAttempt($data['clientPhone'])) {
                $xssErrors[] = 'Le numéro de téléphone contient des éléments non autorisés';
            }
            if (isset($data['clientEmail']) && InputSanitizer::containsXssAttempt($data['clientEmail'])) {
                $xssErrors[] = 'L\'email contient des éléments non autorisés';
            }
            
            if (!empty($xssErrors)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Données invalides détectées',
                    'errors' => $xssErrors
                ], 400);
            }
            
            // Créer la commande
            $order = $this->orderService->createOrder($data ?? []);

            // Notify admin about new order (non-blocking)
            try {
                $this->emailService->sendOrderNotificationToAdmin($order);
            } catch (\Exception $e) {
                // Log silently; do not break order creation
                error_log('Error sending order admin notification: ' . $e->getMessage());
            }

            // Convertir les items en DTOs
            $orderItems = [];
            foreach ($order->getItems() as $item) {
                $orderItems[] = new OrderItemDTO(
                    id: $item->getId(),
                    productId: $item->getProductId(),
                    productName: $item->getProductName(),
                    unitPrice: (float) $item->getUnitPrice(),
                    quantity: $item->getQuantity(),
                    total: (float) $item->getTotal()
                );
            }

            $orderResponse = new OrderResponseDTO(
                id: $order->getId(),
                no: $order->getNo(),
                status: $order->getStatus()->value,
                deliveryMode: $order->getDeliveryMode()->value,
                deliveryAddress: $order->getDeliveryAddress(),
                deliveryZip: $order->getDeliveryZip(),
                deliveryInstructions: $order->getDeliveryInstructions(),
                deliveryFee: (float) $order->getDeliveryFee(),
                paymentMode: $order->getPaymentMode()->value,
                clientFirstName: $order->getClientFirstName(),
                clientLastName: $order->getClientLastName(),
                clientPhone: $order->getClientPhone(),
                clientEmail: $order->getClientEmail(),
                subtotal: (float) $order->getSubtotal(),
                taxAmount: (float) $order->getTaxAmount(),
                total: (float) $order->getTotal(),
                createdAt: $order->getCreatedAt()->format(\DateTime::ATOM),
                items: $orderItems
            );

            $response = new ApiResponseDTO(
                success: true,
                message: 'Commande créée avec succès',
                order: $orderResponse
            );

            return $this->json($response->toArray(), 201);

        } catch (\InvalidArgumentException $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: $e->getMessage()
            );
            return $this->json($response->toArray(), 400);
        } catch (\Exception $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: 'Erreur lors de la création de la commande: ' . $e->getMessage()
            );
            return $this->json($response->toArray(), 500);
        }
    }

    /**
     * Get order by ID
     */
    #[Route('/api/order/{id}', name: 'api_order_get', methods: ['GET'])]
    #[OA\Get(
        path: '/api/order/{id}',
        summary: 'Get order',
        description: 'Returns order details by order ID',
        tags: ['Order']
    )]
    #[OA\Parameter(
        name: 'id',
        in: 'path',
        required: true,
        description: 'Order ID',
        schema: new OA\Schema(type: 'integer', example: 1)
    )]
    #[OA\Response(response: 200, description: 'Successful response', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 404, description: 'Order not found', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Response(response: 500, description: 'Internal server error', content: new OA\JsonContent(ref: '#/components/schemas/ApiResponse'))]
    #[OA\Tag(name: 'Order')]
    public function getOrder(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($id);
            
            if (!$order) {
                $response = new ApiResponseDTO(
                    success: false,
                    message: 'Commande introuvable'
                );
                return $this->json($response->toArray(), 404);
            }

            // Convertir les items en DTOs
            $orderItems = [];
            foreach ($order->getItems() as $item) {
                $orderItems[] = new OrderItemDTO(
                    id: $item->getId(),
                    productId: $item->getProductId(),
                    productName: $item->getProductName(),
                    unitPrice: (float) $item->getUnitPrice(),
                    quantity: $item->getQuantity(),
                    total: (float) $item->getTotal()
                );
            }

            $orderResponse = new OrderResponseDTO(
                id: $order->getId(),
                no: $order->getNo(),
                status: $order->getStatus()->value,
                deliveryMode: $order->getDeliveryMode()->value,
                deliveryAddress: $order->getDeliveryAddress(),
                deliveryZip: $order->getDeliveryZip(),
                deliveryInstructions: $order->getDeliveryInstructions(),
                deliveryFee: (float) $order->getDeliveryFee(),
                paymentMode: $order->getPaymentMode()->value,
                clientFirstName: $order->getClientFirstName(),
                clientLastName: $order->getClientLastName(),
                clientPhone: $order->getClientPhone(),
                clientEmail: $order->getClientEmail(),
                subtotal: (float) $order->getSubtotal(),
                taxAmount: (float) $order->getTaxAmount(),
                total: (float) $order->getTotal(),
                createdAt: $order->getCreatedAt()->format(\DateTime::ATOM),
                items: $orderItems
            );

            $response = new ApiResponseDTO(
                success: true,
                order: $orderResponse
            );

            return $this->json($response->toArray());

        } catch (\Exception $e) {
            $response = new ApiResponseDTO(
                success: false,
                message: 'Erreur lors de la récupération de la commande: ' . $e->getMessage()
            );
            return $this->json($response->toArray(), 500);
        }
    }
}


