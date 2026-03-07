<?php

namespace App\Repository;

use App\Entity\Supporter;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Supporter>
 */
class SupporterRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Supporter::class);
    }

    public function countConfirmed(): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.isConfirmed = :confirmed')
            ->setParameter('confirmed', true)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneByEmailInsensitive(string $email): ?Supporter
    {
        return $this->createQueryBuilder('s')
            ->andWhere('LOWER(s.email) = :email')
            ->setParameter('email', mb_strtolower(trim($email)))
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
