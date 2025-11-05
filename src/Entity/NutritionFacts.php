<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Nutrition facts embedded value-object for menu items.
 *
 * Optional numeric fields; stored directly in the owning entity table.
 */
#[ORM\Embeddable]
class NutritionFacts
{
    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $caloriesKcal = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 1, nullable: true)]
    public ?string $proteinsG = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 1, nullable: true)]
    public ?string $carbsG = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 1, nullable: true)]
    public ?string $fatsG = null;

    #[ORM\Column(type: 'decimal', precision: 6, scale: 1, nullable: true)]
    public ?string $fiberG = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    public ?int $sodiumMg = null;
}


