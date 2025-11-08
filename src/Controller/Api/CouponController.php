<?php

namespace App\Controller\Api;

use App\Controller\AbstractApiController;
use App\DTO\CouponValidateRequest;
use App\Entity\Coupon;
use App\Service\CouponService;
use App\Service\ValidationHelper;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Coupon API Controller
 * 
 * Handles coupon code operations for order discounts:
 * - Validate coupon codes and calculate discounts
 * - Apply coupons to orders (increment usage count)
 * - List active coupons (admin only)
 * 
 * Architecture:
 * - Extends AbstractApiController for common API functionality (JSON parsing, DTO validation, responses)
 * - Uses CouponService for business logic (coupon validation, application, listing)
 * 
 * All coupon validation includes checks for:
 * - Active status
 * - Expiration dates
 * - Usage limits
 * - Minimum order amounts
 */
#[Route('/api/coupon', name: 'api_coupon_')]
class CouponController extends AbstractApiController
{
    /**
     * Constructor
     *
     * Injects dependencies required for coupon operations:
     * - CouponService: Handles coupon business logic (validation, application, listing)
     * - ValidatorInterface and ValidationHelper: Passed to parent for DTO validation
     *
     * @param CouponService $couponService Service for coupon operations
     * @param ValidatorInterface $validator Symfony validator for DTO validation
     * @param ValidationHelper $validationHelper Helper for validation operations
     */
    public function __construct(
        private CouponService $couponService,
        ValidatorInterface $validator,
        ValidationHelper $validationHelper
    ) {
        parent::__construct($validator, $validationHelper);
    }

    /**
     * Validate coupon code and calculate discount
     * 
     * Checks if a coupon code is valid and can be applied to the given order amount.
     * Validates coupon status, expiration, usage limits, and minimum order amount.
     * Returns discount amount and new total after discount.
     * 
     * @param Request $request HTTP request containing coupon code and order amount
     * @return JsonResponse Validation result with discount details or error message
     */
    #[Route('/validate', name: 'validate', methods: ['POST'])]
    /**
     * Validate coupon using DTO + Validator
     *
     * Payload is validated via CouponValidateRequest. On validation failure,
     * responds with 422 and array of errors. Normalizes code to uppercase.
     */
    #[\OpenApi\Attributes\Post(
        path: '/api/coupon/validate',
        summary: 'Validate coupon code and compute discount',
        tags: ['Coupon'],
        requestBody: new \OpenApi\Attributes\RequestBody(required: true, content: new \OpenApi\Attributes\JsonContent(
            type: 'object',
            properties: [
                new \OpenApi\Attributes\Property(property: 'code', type: 'string', example: 'WELCOME10'),
                new \OpenApi\Attributes\Property(property: 'orderAmount', type: 'number', format: 'float', example: 42.50)
            ]
        )),
        responses: [
            new \OpenApi\Attributes\Response(
                response: 200,
                description: 'OK',
                content: new \OpenApi\Attributes\JsonContent(
                    type: 'object',
                    properties: [
                        new \OpenApi\Attributes\Property(property: 'success', type: 'boolean', example: true),
                        new \OpenApi\Attributes\Property(property: 'message', type: 'string', nullable: true),
                        new \OpenApi\Attributes\Property(property: 'data', type: 'object')
                    ]
                )
            ),
            new \OpenApi\Attributes\Response(
                response: 400,
                description: 'Bad request',
                content: new \OpenApi\Attributes\JsonContent(
                    type: 'object',
                    properties: [
                        new \OpenApi\Attributes\Property(property: 'success', type: 'boolean', example: false),
                        new \OpenApi\Attributes\Property(property: 'message', type: 'string', example: 'Requête invalide')
                    ],
                    example: ['success' => false, 'message' => 'JSON invalide']
                )
            ),
            new \OpenApi\Attributes\Response(
                response: 422,
                description: 'Validation error',
                content: new \OpenApi\Attributes\JsonContent(
                    type: 'object',
                    properties: [
                        new \OpenApi\Attributes\Property(property: 'success', type: 'boolean', example: false),
                        new \OpenApi\Attributes\Property(property: 'message', type: 'string', example: 'Erreur de validation'),
                        new \OpenApi\Attributes\Property(property: 'errors', type: 'array', items: new \OpenApi\Attributes\Items(type: 'string'))
                    ],
                    example: ['success' => false, 'message' => 'Erreur de validation', 'errors' => ['Le code est requis']]
                )
            ),
            new \OpenApi\Attributes\Response(
                response: 500,
                description: 'Server error',
                content: new \OpenApi\Attributes\JsonContent(
                    type: 'object',
                    properties: [
                        new \OpenApi\Attributes\Property(property: 'success', type: 'boolean', example: false),
                        new \OpenApi\Attributes\Property(property: 'message', type: 'string', example: 'Erreur serveur')
                    ]
                )
            )
        ]
    )]
    public function validate(Request $request): JsonResponse
    {
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
            $validationResult = $this->validateDto($data, CouponValidateRequest::class);
            if ($validationResult instanceof JsonResponse) {
                // Validation failed, return error response
                return $validationResult;
            }
            $dto = $validationResult;
            
            // Post-processing: Trim whitespace from coupon code
            // Symfony Serializer handles type conversion but doesn't trim strings automatically.
            // We trim here to handle cases where users might include spaces when copying/pasting coupon codes.
            // This ensures codes like " SAVE10 " are normalized to "SAVE10" before validation.
            if ($dto->code !== null) {
                $dto->code = trim($dto->code);
            }

            // Delegate to service
            $data = $this->couponService->validateCoupon($dto);

            // Uses base class method from AbstractApiController
            return $this->successResponse($data, 'Code promo appliqué avec succès', 200);

        } catch (\InvalidArgumentException $e) {
            // Handle business logic errors (e.g., invalid coupon, expired coupon)
            // We catch this specifically to provide custom error message
            // Other exceptions are handled by ApiExceptionSubscriber
            // Uses base class method from AbstractApiController
            return $this->errorResponse($e->getMessage(), 400);
        }
        // Note: All other exceptions are automatically handled by ApiExceptionSubscriber
    }

    /**
     * Apply coupon to order (increment usage count)
     * 
     * Called when an order is successfully placed with a coupon.
     * Increments the coupon's usage count to track how many times it has been used.
     * Should only be called after order creation is confirmed.
     * 
     * @param int $couponId Coupon ID from route parameter
     * @return JsonResponse Success/error response
     */
    #[Route('/apply/{couponId}', name: 'apply', methods: ['POST'])]
    #[\Symfony\Component\Security\Http\Attribute\IsGranted('ROLE_ADMIN')]
    public function apply(int $couponId): JsonResponse
    {
        try {
            // Delegate to service
            $this->couponService->applyCoupon($couponId);

            // Uses base class method from AbstractApiController
            return $this->successResponse(null, 'Code promo appliqué', 200);

        } catch (\InvalidArgumentException $e) {
            // Handle business logic errors (e.g., coupon not found, already used)
            // We catch this specifically to provide custom error message
            // Other exceptions are handled by ApiExceptionSubscriber
            // Uses base class method from AbstractApiController
            return $this->errorResponse($e->getMessage(), 400);
        }
        // Note: All other exceptions are automatically handled by ApiExceptionSubscriber
    }

    /**
     * List all active coupons
     * 
     * Returns all active coupons with their details including:
     * - Discount type and value
     * - Usage limits and current usage count
     * - Validity dates
     * - Minimum order amounts
     * 
     * Note: This endpoint may be restricted to admin users in production.
     * 
     * @return JsonResponse List of active coupons with full details
     */
    #[Route('/list', name: 'list', methods: ['GET'])]
    #[\Symfony\Component\Security\Http\Attribute\IsGranted('ROLE_ADMIN')]
    public function list(): JsonResponse
    {
        // Get active coupons from the service
        $data = $this->couponService->listActiveCoupons();

        // Uses base class method from AbstractApiController
        // Note: All exceptions are automatically handled by ApiExceptionSubscriber,
        // which provides centralized error handling and consistent error response format
        return $this->successResponse($data, null, 200);
    }
}

