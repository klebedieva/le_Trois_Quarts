<?php

namespace App\Controller;

use App\DTO\ApiResponseDTO;
use App\DTO\OrderCreateRequest;
use App\DTO\OrderItemDTO;
use App\DTO\OrderResponseDTO;
use App\Service\ValidationHelper;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Psr\Log\LoggerInterface;
use Psr\Cache\CacheItemPoolInterface;
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
     * @param CacheItemPoolInterface $cache Cache pool for idempotency (PSR-6)
     * @param ValidatorInterface $validator Symfony validator for DTO validation
     * @param ValidationHelper $validationHelper Helper for validation operations
     */
    public function __construct(
        private OrderService $orderService,
        private SymfonyEmailService $emailService,
        private LoggerInterface $logger,
        private CacheItemPoolInterface $cache,
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
        // Step 1: Validate CSRF token (protect against CSRF attacks)
        // Note: Payload size is validated globally by ApiRateLimitSubscriber (priority 9)
        $csrfError = $this->validateCsrfToken($request, $csrfTokenManager);
        if ($csrfError !== null) {
            return $csrfError;
        }

        // Step 2: Get and validate JSON data (must be done before idempotency check)
        $jsonResult = $this->getJsonDataFromRequest($request);
        if ($jsonResult instanceof JsonResponse) {
            return $jsonResult;
        }
        $data = $jsonResult;

        // Step 3: Check idempotency (return cached response if request was already processed)
        // Note: Since client generates a unique UUID for each order attempt, checking only
        // the idempotency key is sufficient. The key uniquely identifies the request.
        $idempotencyKey = (string)($request->headers->get('Idempotency-Key') ?? '');
        $idempotencyResponse = $this->checkIdempotency($idempotencyKey);
        if ($idempotencyResponse !== null) {
            return $idempotencyResponse;
        }

        // Step 4: Map to DTO and validate
        $validationResult = $this->validateDto($data, OrderCreateRequest::class);
        if ($validationResult instanceof JsonResponse) {
            return $validationResult;
        }
        $dto = $validationResult;
        
        // Step 4b: Additional XSS check
        $xssError = $this->validateXss(
            $dto,
            ['deliveryAddress', 'deliveryInstructions', 'clientFirstName', 'clientLastName', 'clientPhone', 'clientEmail']
        );
        if ($xssError !== null) {
            return $xssError;
        }

        // Step 5: Create the order
        $order = $this->orderService->createOrder($dto);

        // Step 6: Notify admin (non-blocking)
        $this->safeNotifyAdmin(
            fn() => $this->emailService->sendOrderNotificationToAdmin($order),
            $this->logger,
            'Order admin notification failed',
            ['orderId' => $order->getId()]
        );

        // Step 7: Build success response
        $responseArray = $this->buildOrderResponse($order);

        // Step 8: Store idempotent response if key provided
        $this->storeIdempotentResponse($idempotencyKey, $responseArray);

        return $this->json($responseArray, 201);
    }

    /**
     * Check idempotency and return cached response if request was already processed
     *
     * Idempotency ensures that the same client action does not create duplicate orders.
     * If a request with the same Idempotency-Key was already processed, we return
     * the cached response instead of processing the request again.
     *
     * The client generates a unique UUID for each order attempt (see order-api.js),
     * so checking only the key is sufficient. The key uniquely identifies the request.
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
        // The client generates a unique UUID for each order attempt, so the key uniquely identifies the request
        $cacheKey = 'idem_order_' . hash('sha256', $idempotencyKey);
        $cached = $this->cache->getItem($cacheKey);
        
        if ($cached->isHit()) {
            // Request was already processed, return cached response
            $cachedPayload = $cached->get();
            return new JsonResponse($cachedPayload['body'] ?? [], $cachedPayload['status'] ?? 201);
        }

        // New request, proceed with processing
        return null;
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
     * If the same request is made again with the same idempotency key, the cached response
     * will be returned instead of creating a duplicate order.
     *
     * The client generates a unique UUID for each order attempt, so the key uniquely
     * identifies the request and is sufficient for idempotency.
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
        // The client generates a unique UUID for each order attempt, so the key uniquely identifies the request
        $cacheKey = 'idem_order_' . hash('sha256', $idempotencyKey);
        $cached = $this->cache->getItem($cacheKey);
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
        $order = $this->orderService->getOrder($id);
        
        if (!$order) {
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

        return $this->successResponse(['order' => $orderResponse], null, 200);
    }
}


