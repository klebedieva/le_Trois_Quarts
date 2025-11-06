<?php

namespace App\Controller;

use App\DTO\ApiResponseDTO;
use App\DTO\OrderCreateRequest;
use App\DTO\OrderItemDTO;
use App\DTO\OrderResponseDTO;
use App\Service\InputSanitizer;
use App\Service\ValidationHelper;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Cache\CacheInterface;
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
        private SymfonyEmailService $emailService,
        private LoggerInterface $logger,
        private CacheInterface $cache,
        private ValidatorInterface $validator,
        private ValidationHelper $validationHelper
    ) {}

    #[Route('/order', name: 'app_order')]
    public function index(): Response
    {
        return $this->render('pages/order.html.twig');
    }

    /**
     * Create a new order
     *
     * Validates JSON payload via Symfony Validator and the OrderCreateRequest DTO.
     * Returns 422 with a list of errors for validation failures, 400 for invalid JSON.
     * Supports idempotency using the Idempotency-Key header (10 minutes TTL) to prevent
     * duplicate order creation on client retries.
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
    #[OA\Response(response: 201, description: 'Order created successfully', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string', nullable: true), new OA\Property(property: 'order', type: 'object', nullable: true)]))]
    #[OA\Response(response: 400, description: 'Invalid JSON or CSRF token', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
    #[OA\Response(response: 422, description: 'Validation error', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string'), new OA\Property(property: 'errors', type: 'array', items: new OA\Items(type: 'string'))]))]
    #[OA\Response(response: 500, description: 'Internal server error', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
    #[OA\Tag(name: 'Order')]
    public function createOrder(Request $request, CsrfTokenManagerInterface $csrfTokenManager): JsonResponse
    {
        try {
            // Optional request size guard (protect against excessively large payloads)
            $rawContent = $request->getContent();
            if (strlen($rawContent) > 65536) { // 64KB hard limit for this endpoint
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Requête trop volumineuse'
                ], 413);
            }

            // CSRF Protection
            $csrfToken = $request->headers->get('X-CSRF-Token');
            if (!$csrfToken || !$csrfTokenManager->isTokenValid(new CsrfToken('submit', $csrfToken))) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Token CSRF invalide'
                ], 403);
            }
            
            // Idempotency: ensure the same client action does not create duplicate orders
            $idempotencyKey = (string)($request->headers->get('Idempotency-Key') ?? '');

            if ($idempotencyKey !== '') {
                $cached = $this->cache->getItem('idem_order_' . hash('sha256', $idempotencyKey));
                if ($cached->isHit()) {
                    $cachedPayload = $cached->get();
                    return new JsonResponse($cachedPayload['body'] ?? [], $cachedPayload['status'] ?? 201);
                }
            }

            $data = json_decode($rawContent, true);
            
            if (!is_array($data)) {
                return new JsonResponse([
                    'success' => false,
                    'message' => 'JSON invalide'
                ], 400);
            }

            // Map payload to DTO using ValidationHelper and validate via Symfony Validator
            $dto = $this->validationHelper->mapArrayToDto($data, OrderCreateRequest::class);

            // Validate DTO
            $violations = $this->validator->validate($dto);
            if (count($violations) > 0) {
                $errors = $this->validationHelper->extractViolationMessages($violations);
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Erreur de validation',
                    'errors' => $errors
                ], 422);
            }

            // Check for XSS attempts after sanitization (defense in depth)
            // Even though InputSanitizer was used during mapping, we perform additional XSS validation
            // This ensures no malicious content passes through, providing multiple layers of security
            $xssErrors = $this->validationHelper->validateXssAttempts(
                $dto,
                ['deliveryAddress', 'deliveryInstructions', 'clientFirstName', 'clientLastName', 'clientPhone', 'clientEmail']
            );
            
            if (!empty($xssErrors)) {
                // Return 400 Bad Request for security violations (not 422, as this is a security issue)
                // XSS attempts are treated as security violations, not validation errors
                return new JsonResponse([
                    'success' => false,
                    'message' => 'Données invalides détectées',
                    'errors' => $xssErrors
                ], 400);
            }

            // Create the order using domain service with DTO
            $order = $this->orderService->createOrder($dto);

            // Notify admin about new order (non-blocking)
            try {
                $this->emailService->sendOrderNotificationToAdmin($order);
            } catch (\Exception $e) {
                // Log silently; do not break order creation
                $this->logger->warning('Order admin notification failed', [
                    'orderId' => $order->getId(),
                    'error' => $e->getMessage()
                ]);
            }

            // Convert items to response DTOs
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

            $responseArray = $response->toArray();

            // Store idempotent response if key provided
            if ($idempotencyKey !== '') {
                $cached = $this->cache->getItem('idem_order_' . hash('sha256', $idempotencyKey));
                $cached->set(['body' => $responseArray, 'status' => 201]);
                // Short TTL (e.g., 10 minutes) is enough to deduplicate user retries
                $cached->expiresAfter(600);
                $this->cache->save($cached);
            }

            return $this->json($responseArray, 201);

        } catch (\InvalidArgumentException $e) {
            // Handle validation/business logic errors (expected from OrderService)
            // OrderService throws InvalidArgumentException for business rule violations
            // (e.g., empty cart, invalid address, invalid coupon)
            $response = new ApiResponseDTO(
                success: false,
                message: $e->getMessage()
            );
            return $this->json($response->toArray(), 422);
        } catch (\TypeError | \ValueError $e) {
            // Handle type errors (should not happen after DTO validation, but defense in depth)
            // These errors indicate type mismatches that should have been caught by DTO validation
            // Log as warning since this indicates a potential bug in validation logic
            $this->logger->warning('Type error in order creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            $response = new ApiResponseDTO(
                success: false,
                message: 'Erreur de validation des données'
            );
            return $this->json($response->toArray(), 422);
        } catch (\Exception $e) {
            // Log unexpected errors for debugging and monitoring
            // This catches any unexpected exceptions that might occur during order processing
            // We log full error details for developers, but only return generic message to user
            $this->logger->error('Unexpected error in order creation', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return generic error message to user (don't expose internal errors for security)
            // Exposing internal error details could help attackers understand system internals
            $response = new ApiResponseDTO(
                success: false,
                message: 'Erreur lors de la création de la commande'
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
    #[OA\Response(response: 200, description: 'Successful response', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'order', type: 'object', nullable: true)]))]
    #[OA\Response(response: 404, description: 'Order not found', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
    #[OA\Response(response: 500, description: 'Internal server error', content: new OA\JsonContent(type: 'object', properties: [new OA\Property(property: 'success', type: 'boolean'), new OA\Property(property: 'message', type: 'string')]))]
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

            // Convert items to response DTOs
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

        } catch (\InvalidArgumentException $e) {
            // Handle validation errors (e.g., invalid order ID format)
            // InvalidArgumentException is thrown when order ID is invalid or order not found
            $response = new ApiResponseDTO(
                success: false,
                message: $e->getMessage()
            );
            return $this->json($response->toArray(), 422);
        } catch (\Exception $e) {
            // Log unexpected errors for debugging and monitoring
            // This catches any unexpected exceptions that might occur during order retrieval
            // We log full error details including order ID for developers
            $this->logger->error('Unexpected error retrieving order', [
                'orderId' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // Return generic error message to user (don't expose internal errors for security)
            // Exposing internal error details could help attackers understand system internals
            $response = new ApiResponseDTO(
                success: false,
                message: 'Erreur lors de la récupération de la commande'
            );
            return $this->json($response->toArray(), 500);
        }
    }
}


