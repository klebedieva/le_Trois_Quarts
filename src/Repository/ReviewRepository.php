<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Review>
 */
class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }

    /**
     * @return Review[] Returns an array of approved reviews (limited to 3)
     */
    public function findApprovedReviews(): array
    {
		return $this->qbApprovedBase()
			->andWhere('r.menuItem IS NULL')
			->orderBy('r.createdAt', 'DESC')
			->setMaxResults(3)
			->getQuery()
			->getResult();
    }

    /**
     * @return Review[] Returns an array of all reviews
     */
    public function findAllOrderedByDate(): array
    {
		return $this->createQueryBuilder('r')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Review[] Returns an array of approved reviews ordered by date
     */
    public function findApprovedOrderedByDate(): array
    {
		return $this->qbApprovedBase()
			->andWhere('r.menuItem IS NULL')
            ->orderBy('r.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compute count and average rating of approved reviews for a given dish.
     *
	 * @return array{cnt:int, avg:float} Scalar stats: number of reviews and average rating
     */
    public function getApprovedStatsForMenuItem(int $menuItemId): array
    {
        $row = $this->createQueryBuilder('r')
            ->select('COUNT(r.id) AS cnt, COALESCE(AVG(r.rating), 0) AS avg')
            ->andWhere('r.menuItem = :id')
			->andWhere('r.isApproved = 1')
            ->setParameter('id', $menuItemId)
            ->getQuery()
            ->getSingleResult();

        return [
            'cnt' => (int)($row['cnt'] ?? 0),
            'avg' => (float)($row['avg'] ?? 0.0),
        ];
    }

    /**
     * Returns approved general reviews (not linked to dish) paginated.
     *
     * @return Review[]
     */
    public function findApprovedGeneralPaginated(int $page, int $limit): array
    {
        $offset = ($page - 1) * $limit;
        return $this->qbApprovedBase()
            ->andWhere('r.menuItem IS NULL')
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function countApprovedGeneral(): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->andWhere('r.menuItem IS NULL')
            ->andWhere('r.isApproved = 1')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Returns approved reviews for a specific dish with optional pagination.
     *
     * @return Review[]
     */
    public function findApprovedForDish(int $menuItemId, int $limit = 100, int $offset = 0): array
    {
        return $this->qbApprovedBase()
            ->andWhere('r.menuItem = :id')
            ->setParameter('id', $menuItemId)
            ->orderBy('r.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

	/**
	 * Return latest approved reviews (site-wide), ordered by date DESC.
	 *
	 * @param int $limit Max number of reviews to return
	 * @return Review[]
	 */
	public function findLatestApproved(int $limit = 6): array
	{
		return $this->qbApprovedBase()
			->orderBy('r.createdAt', 'DESC')
			->setMaxResults($limit)
			->getQuery()
			->getResult();
	}

	/**
	 * Base QueryBuilder for approved reviews.
	 */
	private function qbApprovedBase()
	{
		return $this->createQueryBuilder('r')
			->andWhere('r.isApproved = :approved')
			->setParameter('approved', true);
	}
}