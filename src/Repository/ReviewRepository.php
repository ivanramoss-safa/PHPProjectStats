<?php

namespace App\Repository;

use App\Entity\Review;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ReviewRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Review::class);
    }
    public function getAverageRating(string $type, string $externalId): float
    {
        $qb = $this->createQueryBuilder('r')
            ->select('AVG(r.stars)')
            ->where('r.type = :type')
            ->andWhere('r.externalId = :externalId')
            ->setParameter('type', $type)
            ->setParameter('externalId', $externalId);

        try {
            return (float) $qb->getQuery()->getSingleScalarResult();
        } catch (\Exception $e) {
            return 0.0;
        }
    }

    
    public function findTopRated(string $type, int $limit = 8): array
    {
        $qb = $this->createQueryBuilder('r')
            ->select('r.externalId, AVG(r.stars) as avg_rating')
            ->where('r.type = :type')
            ->groupBy('r.externalId')
            ->orderBy('avg_rating', 'DESC')
            ->setMaxResults($limit)
            ->setParameter('type', $type);

        return $qb->getQuery()->getResult();
    }
}
