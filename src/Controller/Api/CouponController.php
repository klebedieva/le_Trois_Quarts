<?php

namespace App\Controller\Api;

use App\DTO\CouponValidateRequest;
use App\Entity\Coupon;
use App\Repository\CouponRepository;
use App\Service\ValidationHelper;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
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
 * All coupon validation includes checks for:
 * - Active status
 * - Expiration dates
 * - Usage limits
 * - Minimum order amounts
 */
#[Route('/api/coupon', name: 'api_coupon_')]
class CouponController extends AbstractController
{
    public function __construct(
        private CouponRepository $couponRepository,
        private EntityManagerInterface $entityManager,
        private ValidatorInterface $validator,
        private ValidationHelper $validationHelper
    ) {
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
            // Parse JSON request body
            $data = json_decode($request->getContent(), true);
            
            if (!is_array($data)) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'JSON invalide');
                return $this->json($response->toArray(), 400);
            }

            // Map JSON payload to DTO using helper service
            // The ValidationHelper automatically handles type conversion (e.g., string '10.5' -> float 10.5)
            // This eliminates repetitive manual mapping code like: isset($data['code']) ? trim((string)$data['code']) : null
            $dto = $this->validationHelper->mapArrayToDto($data, CouponValidateRequest::class);
            
            // Post-processing: Trim whitespace from coupon code
            // Symfony Serializer handles type conversion but doesn't trim strings automatically.
            // We trim here to handle cases where users might include spaces when copying/pasting coupon codes.
            // This ensures codes like " SAVE10 " are normalized to "SAVE10" before validation.
            if ($dto->code !== null) {
                $dto->code = trim($dto->code);
            }

            // Validate DTO
            $violations = $this->validator->validate($dto);
            if (count($violations) > 0) {
                $errors = $this->validationHelper->extractViolationMessages($violations);
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur de validation', errors: $errors);
                return $this->json($response->toArray(), 422);
            }

            // Normalize coupon code (uppercase, trimmed)
            $code = strtoupper(trim($dto->code));
            $orderAmount = (float) $dto->orderAmount;

            // Find coupon by code in database
            $coupon = $this->couponRepository->findOneBy(['code' => $code]);

            if (!$coupon) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Code promo invalide');
                return $this->json($response->toArray(), 404);
            }

            // Check if coupon is currently active (not disabled by admin)
            if (!$coupon->isActive()) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Ce code promo n\'est plus actif');
                return $this->json($response->toArray(), 400);
            }

            // Check if coupon can be used (valid dates, not expired, usage limit not reached)
            if (!$coupon->canBeUsed()) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Ce code promo n\'est plus disponible');
                return $this->json($response->toArray(), 400);
            }

            // Check if order amount meets minimum requirement
            if (!$coupon->canBeAppliedToAmount($orderAmount)) {
                $minAmount = $coupon->getMinOrderAmount();
                $response = new \App\DTO\ApiResponseDTO(
                    success: false,
                    message: sprintf('Montant minimum de commande non atteint (minimum: %.2f€)', (float) $minAmount)
                );
                return $this->json($response->toArray(), 400);
            }

            // Calculate discount amount based on coupon type (percentage or fixed)
            $discountAmount = $coupon->calculateDiscount($orderAmount);

            $response = new \App\DTO\ApiResponseDTO(
                success: true,
                message: 'Code promo appliqué avec succès',
                data: [
                    'couponId' => $coupon->getId(),
                    'code' => $coupon->getCode(),
                    'discountType' => $coupon->getDiscountType(),
                    'discountValue' => $coupon->getDiscountValue(),
                    'discountAmount' => number_format($discountAmount, 2, '.', ''),
                    'newTotal' => number_format($orderAmount - $discountAmount, 2, '.', '')
                ]
            );
            return $this->json($response->toArray());

        } catch (\Exception $e) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur lors de la validation du code promo: ' . $e->getMessage());
            return $this->json($response->toArray(), 500);
        }
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
    public function apply(int $couponId): JsonResponse
    {
        try {
            // Find coupon by ID
            $coupon = $this->couponRepository->find($couponId);

            if (!$coupon) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Code promo non trouvé');
                return $this->json($response->toArray(), 404);
            }

            // Final check before applying (in case coupon became invalid between validation and application)
            if (!$coupon->canBeUsed()) {
                $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Ce code promo n\'est plus disponible');
                return $this->json($response->toArray(), 400);
            }

            // Increment usage count to track coupon usage
            $coupon->incrementUsage();
            $this->entityManager->flush();

            $response = new \App\DTO\ApiResponseDTO(success: true, message: 'Code promo appliqué');
            return $this->json($response->toArray());

        } catch (\Exception $e) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur lors de l\'application du code promo: ' . $e->getMessage());
            return $this->json($response->toArray(), 500);
        }
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
    public function list(): JsonResponse
    {
        try {
            $coupons = $this->couponRepository->findBy(
                ['isActive' => true],
                ['createdAt' => 'DESC']
            );

            $data = array_map(function (Coupon $coupon) {
                return [
                    'id' => $coupon->getId(),
                    'code' => $coupon->getCode(),
                    'description' => $coupon->getDescription(),
                    'discountType' => $coupon->getDiscountType(),
                    'discountValue' => $coupon->getDiscountValue(),
                    'minOrderAmount' => $coupon->getMinOrderAmount(),
                    'maxDiscount' => $coupon->getMaxDiscount(),
                    'usageLimit' => $coupon->getUsageLimit(),
                    'usageCount' => $coupon->getUsageCount(),
                    'validFrom' => $coupon->getValidFrom()?->format('Y-m-d H:i:s'),
                    'validUntil' => $coupon->getValidUntil()?->format('Y-m-d H:i:s'),
                    'isValid' => $coupon->isValid(),
                    'canBeUsed' => $coupon->canBeUsed()
                ];
            }, $coupons);

            $response = new \App\DTO\ApiResponseDTO(success: true, data: $data);
            return $this->json($response->toArray());

        } catch (\Exception $e) {
            $response = new \App\DTO\ApiResponseDTO(success: false, message: 'Erreur lors de la récupération des codes promo: ' . $e->getMessage());
            return $this->json($response->toArray(), 500);
        }
    }
}

