<?php

namespace App\Repository;

use App\Entity\UploadedDependencyFile;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UploadedDependencyFile>
 *
 * @method UploadedDependencyFile|null find($id, $lockMode = null, $lockVersion = null)
 * @method UploadedDependencyFile|null findOneBy(array $criteria, array $orderBy = null)
 * @method UploadedDependencyFile[]    findAll()
 * @method UploadedDependencyFile[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class UploadedDependencyFileRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UploadedDependencyFile::class);
    }

//    /**
//     * @return UploadedDependencyFile[] Returns an array of UploadedDependencyFile objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('u.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?UploadedDependencyFile
//    {
//        return $this->createQueryBuilder('u')
//            ->andWhere('u.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
