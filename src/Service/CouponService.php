<?php

namespace App\Service;

use App\DTO\CouponValidateRequest;
use App\Entity\Coupon;
use App\Repository\CouponRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Coupon Service
 *
 * Encapsulates coupon validation, application (usage increment), and listing.
 * Keeps controllers thin and focused on HTTP concerns.
 */
class CouponService
{
    public function __construct(
        private CouponRepository $couponRepository,
        private EntityManagerInterface $entityManager
    ) {}

    /**
     * Validate a coupon and compute discount details for a given order amount.
     *
     * @param CouponValidateRequest $dto
     * @return array{couponId:int,code:string,discountType:mixed,discountValue:mixed,discountAmount:string,newTotal:string}
     */
    public function validateCoupon(CouponValidateRequest $dto): array
    {
        $code = strtoupper(trim((string)$dto->code));
        $orderAmount = (float) $dto->orderAmount;

        $coupon = $this->couponRepository->findOneBy(['code' => $code]);
        if (!$coupon) {
            throw new \InvalidArgumentException('Code promo invalide');
        }

        if (!$coupon->isActive()) {
            throw new \InvalidArgumentException('Ce code promo n\'est plus actif');
        }

        if (!$coupon->canBeUsed()) {
            throw new \InvalidArgumentException('Ce code promo n\'est plus disponible');
        }

        if (!$coupon->canBeAppliedToAmount($orderAmount)) {
            $minAmount = (float) $coupon->getMinOrderAmount();
            throw new \InvalidArgumentException(sprintf('Montant minimum de commande non atteint (minimum: %.2f€)', $minAmount));
        }

        $discountAmount = $coupon->calculateDiscount($orderAmount);

        return [
            'couponId' => $coupon->getId(),
            'code' => $coupon->getCode(),
            'discountType' => $coupon->getDiscountType(),
            'discountValue' => $coupon->getDiscountValue(),
            'discountAmount' => number_format($discountAmount, 2, '.', ''),
            'newTotal' => number_format($orderAmount - $discountAmount, 2, '.', ''),
        ];
    }

    /**
     * Apply coupon usage increment after successful order placement.
     */
    public function applyCoupon(int $couponId): void
    {
        $coupon = $this->couponRepository->find($couponId);
        if (!$coupon) {
            throw new \InvalidArgumentException('Code promo non trouvé');
        }

        if (!$coupon->canBeUsed()) {
            throw new \InvalidArgumentException('Ce code promo n\'est plus disponible');
        }

        $coupon->incrementUsage();
        $this->entityManager->flush();
    }

    /**
     * Return list of active coupons formatted for API output.
     *
     * @return array<int, array<string, mixed>>
     */
    public function listActiveCoupons(): array
    {
        $coupons = $this->couponRepository->findBy(
            ['isActive' => true],
            ['createdAt' => 'DESC']
        );

        return array_map(function (Coupon $coupon) {
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
                'canBeUsed' => $coupon->canBeUsed(),
            ];
        }, $coupons);
    }
}


