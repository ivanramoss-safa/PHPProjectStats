<?php

namespace App\Repository;

use App\Entity\News;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;


class NewsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, News::class);
    }
    
    public function findNewsByDateOrLatest(string $dateStr): array
    {
        $dateStart = new \DateTime($dateStr . ' 00:00:00');
        $dateEnd = new \DateTime($dateStr . ' 23:59:59');

        $news = $this->createQueryBuilder('n')
            ->andWhere('n.createdAt >= :start')
            ->andWhere('n.createdAt <= :end')
            ->setParameter('start', $dateStart)
            ->setParameter('end', $dateEnd)
            ->orderBy('n.featured', 'DESC')
            ->addOrderBy('n.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
        if (empty($news)) {
            return $this->createQueryBuilder('n')
                ->orderBy('n.featured', 'DESC')
                ->addOrderBy('n.createdAt', 'DESC')
                ->setMaxResults(10)
                ->getQuery()
                ->getResult();
        }

        return $news;
    }
    
    public function findByRelatedEntity(string $type, int $id): array
    {
        $fieldMap = [
            'player' => 'playerIds',
            'team' => 'teamIds',
            'league' => 'leagueIds',
            'coach' => 'coachIds',
            'venue' => 'venueIds',
            'fixture' => 'fixtureIds',
        ];

        if (!isset($fieldMap[$type])) {
            return [];
        }

        $field = $fieldMap[$type];

        return $this->createQueryBuilder('n')
            ->andWhere("n.$field LIKE :val")
            ->setParameter('val', '%"' . $id . '"%') 
            ->orWhere("n.$field LIKE :val2")
            ->setParameter('val2', '%[' . $id . ']%') 
            ->orWhere("n.$field LIKE :val3")
            ->setParameter('val3', '%' . $id . '%') 
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(20)
            ->getQuery()
            ->getResult();
    }
}
