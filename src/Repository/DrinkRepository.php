<?php

namespace App\Repository;

use App\Entity\Drink;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Drink repository.
 *
 * Extend with helpers for grouping/filtering drinks by type for the menu page.
 *
 * @extends ServiceEntityRepository<Drink>
 */
class DrinkRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Drink::class);
    }
}
