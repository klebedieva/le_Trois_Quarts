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
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use OpenApi\Attributes as OA;

/**
 * Order API and page controller.
 *
 * This controller handles order-related operations:
 * - index(): renders the multi-step checkout page (non-API route)
 * - createOrder(): validates input, delegates order creation to service, returns DTO
 * - getOrder(): returns order data by id for clients needing confirmation/details
 *
 * Architecture:
 * - Extends AbstractApiController for common API functionality (JSON parsing, DTO validation, CSRF, responses)
 * - Uses OrderService for business logic (order creation, retrieval)
 * - Implements idempotency to prevent duplicate orders
 * - Includes XSS validation for security
 */
class OrderController extends AbstractApiController
{
    /**
     * Constructor
     *
     * Injects dependencies required for order operations:
     * - OrderService: Handles order creation and retrieval (business logic)
     * - SymfonyEmailService: Sends email notifications to admin
     * - LoggerInterface: Logs errors and warnings
     * - CacheInterface: Stores idempotent responses
     * - ValidatorInterface and ValidationHelper: Passed to parent for DTO validation
     *
     * @param OrderService $orderService Service for order operations
     * @param SymfonyEmailService $emailService Email service for notifications
     * @param LoggerInterface $logger Logger for error tracking
     * @param CacheInterface $cache Cache for idempotency
     * @param ValidatorInterface $validator Symfony validator for DTO validation
     * @param ValidationHelper $validationHelper Helper for validation operations
     */
    public function __construct(
        private OrderService $orderService,
        private SymfonyEmailService $emailService,
        private LoggerInterface $logger,
        private CacheInterface $cache,
        ValidatorInterface $validator,
        ValidationHelper $validationHelper
    ) {
        parent::__construct($validator, $validationHelper);
    }

    #[Route('/order', name: 'app_order')]
    public function index(): Response
    {
        return $this->render('pages/order.html.twig', [
            'seo_title' => 'Commander en ligne | Le Trois Quarts Marseille',
            'seo_description' => 'Finalisez votre commande au Trois Quarts : choisissez livraison ou retrait, renseignez vos coordonnées et confirmez votre panier.',
            'seo_og_description' => 'Passez commande au Trois Quarts en quelques étapes simples : panier, livraison, paiement et confirmation.',
        ]);
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
            // Step 1: Validate request size (protect against excessively large payloads)
            $payloadSizeError = $this->validatePayloadSize($request);
            if ($payloadSizeError !== null) {
                return $payloadSizeError;
            }

            // Step 2: Validate CSRF token (protect against CSRF attacks)
            // Uses base class method from AbstractApiController
            $csrfError = $this->validateCsrfToken($request, $csrfTokenManager);
            if ($csrfError !== null) {
                return $csrfError;
            }

            // Step 3: Check idempotency (return cached response if request was already processed)
            $idempotencyKey = (string)($request->headers->get('Idempotency-Key') ?? '');
            $idempotencyResponse = $this->checkIdempotency($idempotencyKey);
            if ($idempotencyResponse !== null) {
                return $idempotencyResponse;
            }

            // Step 4: Get and validate JSON data
            // Uses base class method from AbstractApiController
            // Returns array or JsonResponse (error if JSON invalid)
            $jsonResult = $this->getJsonDataFromRequest($request);
            if ($jsonResult instanceof JsonResponse) {
                // JSON parsing failed, return error response
                return $jsonResult;
            }
            $data = $jsonResult;

            // Step 5: Map to DTO and validate (DTO validation)
            // Uses base class method from AbstractApiController
            // Returns DTO or JsonResponse (error if validation fails)
            $validationResult = $this->validateDto($data, OrderCreateRequest::class);
            if ($validationResult instanceof JsonResponse) {
                // Validation failed, return error response
                return $validationResult;
            }
            
            // Validation passed, get the validated DTO
            $dto = $validationResult;
            
            // Step 5b: Additional XSS check (defense in depth)
            // Even though DTO validation passed, we perform additional XSS validation
            // This ensures no malicious content passes through, providing multiple layers of security
            // Uses base class method from AbstractApiController
            $xssError = $this->validateXss(
                $dto,
                ['deliveryAddress', 'deliveryInstructions', 'clientFirstName', 'clientLastName', 'clientPhone', 'clientEmail']
            );
            
            if ($xssError !== null) {
                // XSS detected, return error response
                return $xssError;
            }

            // Step 6: Create the order using domain service
            $order = $this->orderService->createOrder($dto);

            // Step 7: Notify admin about new order (non-blocking, doesn't break order creation if fails)
            $this->notifyAdminAboutNewOrder($order);

            // Step 8: Build success response
            $responseArray = $this->buildOrderResponse($order);

            // Step 9: Store idempotent response if key provided (for duplicate request prevention)
            $this->storeIdempotentResponse($idempotencyKey, $responseArray);

            // Return success response using base class method
            // Note: We return the array directly here because buildOrderResponse() already creates ApiResponseDTO
            // and converts it to array. We could refactor this to use successResponse() in the future.
            return $this->json($responseArray, 201);

        } catch (\InvalidArgumentException $e) {
            // Handle validation/business logic errors (expected from OrderService)
            // OrderService throws InvalidArgumentException for business rule violations
            // (e.g., empty cart, invalid address, invalid coupon)
            // 
            // WHY WE CATCH THIS HERE:
            // We catch InvalidArgumentException specifically because we want to use the exact
            // error message from the service (it's user-friendly and specific).
            // 
            // WHAT HAPPENS TO OTHER EXCEPTIONS:
            // All other exceptions (TypeError, ValueError, Exception, etc.) are NOT caught here.
            // They automatically go to ApiExceptionSubscriber, which:
            // 1. Logs full error details for developers
            // 2. Returns safe generic message to client
            // 3. Ensures consistent error format across all API endpoints
            //
            // This is called "hybrid approach": specific handling here, general handling in subscriber
            // Uses base class method from AbstractApiController
            return $this->errorResponse($e->getMessage(), 422);
        }
        // 
        // IMPORTANT FOR BEGINNERS:
        // Notice there's NO catch (\Exception) block here. This is intentional!
        // 
        // If any other exception occurs (database error, network error, etc.),
        // it will be automatically caught by ApiExceptionSubscriber.
        // 
        // You don't need to write try-catch for every possible error - the subscriber
        // handles it automatically. This makes code cleaner and ensures consistent
        // error handling across all API endpoints.
        //
        // See: src/EventSubscriber/ApiExceptionSubscriber.php for details
    }

    /**
     * Validate request payload size
     *
     * This method checks if the request payload exceeds the maximum allowed size.
     * This protects against DoS attacks via excessively large payloads.
     *
     * @param Request $request HTTP request to validate
     * @return JsonResponse|null Error response if payload is too large, null if valid
     */
    private function validatePayloadSize(Request $request): ?JsonResponse
    {
        $rawContent = $request->getContent();
        
        // Get max payload size from parameters (with fallback if parameter not found)
        // Note: This catch is for graceful fallback, not error handling
        // Main exceptions are handled by ApiExceptionSubscriber
        try {
            $maxPayloadBytes = $this->getParameter('order.max_payload_bytes');
        } catch (\Exception $e) {
            // Fallback to default if parameter not found (should not happen in production)
            // This is a graceful degradation, not an error condition
            $maxPayloadBytes = 65536; // 64KB default
            $this->logger->warning('Parameter order.max_payload_bytes not found, using default', [
                'default' => $maxPayloadBytes
            ]);
        }
        
        if (strlen($rawContent) > $maxPayloadBytes) {
            return new JsonResponse([
                'success' => false,
                'message' => 'Requête trop volumineuse'
            ], 413);
        }

        return null;
    }

    /**
     * Check idempotency and return cached response if request was already processed
     *
     * Idempotency ensures that the same client action does not create duplicate orders.
     * If a request with the same Idempotency-Key was already processed, we return
     * the cached response instead of processing the request again.
     *
     * Note: CSRF validation is handled by base class method validateCsrfToken().
     * This method is specific to OrderController and handles order-specific idempotency.
     *
     * @param string $idempotencyKey Idempotency key from request header (empty string if not provided)
     * @return JsonResponse|null Cached response if request was already processed, null if new request
     */
    private function checkIdempotency(string $idempotencyKey): ?JsonResponse
    {
        if ($idempotencyKey === '') {
            // No idempotency key provided, proceed with new request
            return null;
        }

        // Check if we already processed this request
        $cached = $this->cache->getItem('idem_order_' . hash('sha256', $idempotencyKey));
        if ($cached->isHit()) {
            // Request was already processed, return cached response
            $cachedPayload = $cached->get();
            return new JsonResponse($cachedPayload['body'] ?? [], $cachedPayload['status'] ?? 201);
        }

        // New request, proceed with processing
        return null;
    }


    /**
     * Notify admin about new order (non-blocking)
     *
     * This method sends an email notification to the admin about a new order.
     * It's non-blocking: if email sending fails, it logs the error but doesn't
     * break the order creation process.
     *
     * @param \App\Entity\Order $order Created order entity
     */
    private function notifyAdminAboutNewOrder(\App\Entity\Order $order): void
    {
        // This is a non-blocking operation (email sending)
        // We catch exceptions here because email failure should not break order creation
        // This is different from main business logic exceptions, which are handled by ApiExceptionSubscriber
        try {
            $this->emailService->sendOrderNotificationToAdmin($order);
        } catch (\Exception $e) {
            // Log silently; do not break order creation
            // Email notification is a nice-to-have feature, not critical for order creation
            // Note: This catch is intentional - we want to handle email failures gracefully
            // without affecting the main order creation flow
            $this->logger->warning('Order admin notification failed', [
                'orderId' => $order->getId(),
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Build order response DTO
     *
     * This method converts an Order entity to an OrderResponseDTO for API response.
     * It includes all order details and converts order items to DTOs.
     *
     * @param \App\Entity\Order $order Order entity to convert
     * @return array Response data as array (ready for JSON serialization)
     */
    private function buildOrderResponse(\App\Entity\Order $order): array
    {
        // Convert order items to response DTOs
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

        // Build order response DTO
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

        // Build API response DTO
        $response = new ApiResponseDTO(
            success: true,
            message: 'Commande créée avec succès',
            order: $orderResponse
        );

        return $response->toArray();
    }

    /**
     * Store idempotent response in cache
     *
     * This method stores the order creation response in cache using the idempotency key.
     * If the same request is made again with the same idempotency key, the cached
     * response will be returned instead of creating a duplicate order.
     *
     * @param string $idempotencyKey Idempotency key from request header (empty string if not provided)
     * @param array $responseArray Response data to cache
     */
    private function storeIdempotentResponse(string $idempotencyKey, array $responseArray): void
    {
        if ($idempotencyKey === '') {
            // No idempotency key provided, nothing to cache
            return;
        }

        // Store response in cache for idempotency
        $cached = $this->cache->getItem('idem_order_' . hash('sha256', $idempotencyKey));
        $cached->set(['body' => $responseArray, 'status' => 201]);
        
        // TTL is configurable via order.idempotency_ttl parameter
        // Note: This catch is for graceful fallback, not error handling
        // Main exceptions are handled by ApiExceptionSubscriber
        try {
            $idempotencyTtl = $this->getParameter('order.idempotency_ttl');
        } catch (\Exception $e) {
            // Fallback to default if parameter not found (should not happen in production)
            // This is a graceful degradation, not an error condition
            $idempotencyTtl = 600; // 10 minutes default
            $this->logger->warning('Parameter order.idempotency_ttl not found, using default', [
                'default' => $idempotencyTtl
            ]);
        }
        
        $cached->expiresAfter($idempotencyTtl);
        $this->cache->save($cached);
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
    #[\Symfony\Component\Security\Http\Attribute\IsGranted('ROLE_ADMIN')]
    public function getOrder(int $id): JsonResponse
    {
        try {
            $order = $this->orderService->getOrder($id);
            
            if (!$order) {
                // Uses base class method from AbstractApiController
                return $this->errorResponse('Commande introuvable', 404);
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

            // Uses base class method from AbstractApiController
            return $this->successResponse(['order' => $orderResponse], null, 200);

        } catch (\InvalidArgumentException $e) {
            // Handle validation errors (e.g., invalid order ID format)
            // InvalidArgumentException is thrown when order ID is invalid or order not found
            // We catch this specifically to provide custom error message from service
            // Other exceptions are handled by ApiExceptionSubscriber
            // Uses base class method from AbstractApiController
            return $this->errorResponse($e->getMessage(), 422);
        }
        // Note: All other exceptions are automatically handled by ApiExceptionSubscriber,
        // which provides centralized error handling and consistent error response format
    }
}


