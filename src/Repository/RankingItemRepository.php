<?php

namespace App\Repository;

use App\Entity\RankingItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class RankingItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RankingItem::class);
    }

    public function getGlobalRankingPoints(string $categoryType, int $limit = 5): array
    {
        $conn = $this->getEntityManager()->getConnection();
        $sql = "
            SELECT ri.external_id as externalId, 
                   SUM(
                       CASE ri.position 
                           WHEN 1 THEN 10 
                           WHEN 2 THEN 8 
                           WHEN 3 THEN 5 
                           WHEN 4 THEN 3 
                           WHEN 5 THEN 2 
                           ELSE 1 
                       END
                   ) as total_points
            FROM ranking_item ri
            JOIN ranking r ON ri.ranking_id = r.id
            JOIN category c ON r.category_id = c.id
            WHERE c.type = ?
            GROUP BY ri.external_id
            ORDER BY total_points DESC
            LIMIT " . (int)$limit . "
        ";

        $result = $conn->executeQuery($sql, [$categoryType]);
        return $result->fetchAllAssociative();
    }
}
