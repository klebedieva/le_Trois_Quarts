# Strategy + Factory Adoption (Delivery & Pricing)

This document describes why and how the Strategy + Factory pattern was introduced for delivery and pricing, the exact code changes, and how to extend or revert them safely.

## Goals

- Decouple domain algorithms (delivery validation/fee and totals computation) from orchestration code.
- Keep current behavior intact (no DB changes, no API changes, no functional changes).
- Make it easy to add new delivery modes or pricing rules without touching existing code paths.

## Scope (Minimal Viable Refactor)

- Delivery strategies (validation and fee population):
  - DeliveryModeDeliveryStrategy (home delivery)
  - PickupDeliveryStrategy (in‑store pickup)
- Pricing strategy:
  - DefaultPricingStrategy (same math as before)
- Factories:
  - DeliveryStrategyFactory (selects delivery strategy by DeliveryMode)
  - PricingStrategyFactory (provides default pricing strategy)

## Files Added

- `src/Strategy/Delivery/DeliveryStrategy.php`
- `src/Strategy/Delivery/DeliveryStrategyFactory.php`
- `src/Strategy/Delivery/Strategy/DeliveryModeDeliveryStrategy.php`
- `src/Strategy/Delivery/Strategy/PickupDeliveryStrategy.php`
- `src/Strategy/Pricing/PricingStrategy.php`
- `src/Strategy/Pricing/PricingStrategyFactory.php`
- `src/Strategy/Pricing/Strategy/DefaultPricingStrategy.php`

## Existing Files Updated

- `src/Service/OrderService.php` — uses factories to delegate delivery validation/population and pricing computation; coupon application kept identical.
- `config/services.yaml` — registers strategies and wires factories with tagged services.

## Dependency Injection Wiring

```yaml
# config/services.yaml (excerpt)
App\Strategy\Delivery\Strategy\:
  resource: '../src/Strategy/Delivery/Strategy/*'
  tags: ['app.delivery_strategy']

App\Strategy\Delivery\DeliveryStrategyFactory:
  arguments:
    - !tagged_iterator 'app.delivery_strategy'

App\Strategy\Pricing\Strategy\DefaultPricingStrategy: ~
App\Strategy\Pricing\PricingStrategyFactory:
  arguments:
    - '@App\Strategy\Pricing\Strategy\DefaultPricingStrategy'
```

## OrderService Integration (before → after)

Previous (inline logic): validate address/zip; set fee; compute tax/subtotal/total; apply coupon.

Now:

1) Delivery
```php
$deliveryStrategy = $this->deliveryStrategies->forMode($deliveryMode);
$deliveryStrategy->validateAndPopulate($order, $orderData);
```

2) Pricing
```php
$subtotalWithTax = $cart['total'];
$this->pricingStrategies->default()->computeAndSetTotals($order, (float) $subtotalWithTax);
```

3) Coupon application unchanged (applies on top of strategy totals).

## Behavior Guarantees

- No persistence schema changes (no migrations required).
- No public API changes.
- Same totals and validation rules as before.
- Safe to roll back by removing the strategy calls and restoring the previous inline code.

## How to Extend

### Add a new delivery mode
1. Create a class implementing `DeliveryStrategy` in `src/Strategy/Delivery/Strategy/`.
2. Implement `supports(DeliveryMode $mode): bool` and `validateAndPopulate(Order $order, array $orderData): void`.
3. Service will auto‑register via resource glob and tag `app.delivery_strategy`.

### Customize pricing rules
1. Create a class implementing `PricingStrategy`.
2. Register it and inject it into `PricingStrategyFactory` (or add a selector method if multiple pricing strategies are needed).

## Testing Notes

- Unit: assert computed totals equal pre‑refactor results for representative carts (with/without delivery, with/without coupons).
- Functional: create order via `/api/order` for both Delivery and Pickup; ensure identical responses.

## Future Work (optional)

- Add Payment strategies (Card/Cash/Tickets) for post‑order processing flows.
- Cache delivery validation results (e.g., based on ZIP) to reduce API calls if any.
- Move more pricing concerns from `Order` entity to strategy for full domain separation.

## Rollback Plan

- Replace the two strategy calls in `OrderService` with the original inline logic (kept in VCS history).
- Remove DI registrations for strategies.


