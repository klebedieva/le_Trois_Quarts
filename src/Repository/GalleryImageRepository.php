<?php

namespace App\Repository;

use App\Entity\GalleryImage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Gallery image repository.
 *
 * Provides helpers to fetch active images and filter by category for public API.
 *
 * @extends ServiceEntityRepository<GalleryImage>
 */
class GalleryImageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, GalleryImage::class);
    }

    /**
     * Find all active images ordered by display order
     *
     * @return GalleryImage[]
     */
    public function findAllActive(): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('g.displayOrder', 'ASC')
            ->addOrderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find active images by category
     *
     * @param string $category
     * @return GalleryImage[]
     */
    public function findByCategory(string $category): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.isActive = :active')
            ->andWhere('g.category = :category')
            ->setParameter('active', true)
            ->setParameter('category', $category)
            ->orderBy('g.displayOrder', 'ASC')
            ->addOrderBy('g.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get images grouped by category
     *
     * @return array<string, GalleryImage[]>
     */
    public function findGroupedByCategory(): array
    {
        $images = $this->findAllActive();
        $grouped = [];

        foreach ($images as $image) {
            $category = $image->getCategory();
            if (!isset($grouped[$category])) {
                $grouped[$category] = [];
            }
            $grouped[$category][] = $image;
        }

        return $grouped;
    }

    /**
     * Count images by category
     *
     * @return array<string, int>
     */
    public function countByCategory(): array
    {
        $qb = $this->createQueryBuilder('g')
            ->select('g.category, COUNT(g.id) as count')
            ->where('g.isActive = :active')
            ->setParameter('active', true)
            ->groupBy('g.category');

        $results = $qb->getQuery()->getResult();

        $counts = [
            'terrasse' => 0,
            'interieur' => 0,
            'plats' => 0,
            'ambiance' => 0,
        ];

        foreach ($results as $result) {
            $counts[$result['category']] = (int) $result['count'];
        }

        return $counts;
    }

    /**
     * Find latest N active images for homepage
     *
     * @param int $limit
     * @return GalleryImage[]
     */
    public function findLatestForHomepage(int $limit = 6): array
    {
        return $this->createQueryBuilder('g')
            ->where('g.isActive = :active')
            ->setParameter('active', true)
            ->orderBy('g.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}

