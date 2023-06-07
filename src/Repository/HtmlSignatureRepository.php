<?php

namespace App\Repository;

use App\Entity\HtmlSignature;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<HtmlSignature>
 *
 * @method HtmlSignature|null find($id, $lockMode = null, $lockVersion = null)
 * @method HtmlSignature|null findOneBy(array $criteria, array $orderBy = null)
 * @method HtmlSignature[]    findAll()
 * @method HtmlSignature[]    findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
 */
class HtmlSignatureRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, HtmlSignature::class);
    }

    public function save(HtmlSignature $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(HtmlSignature $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

//    /**
//     * @return HtmlSignature[] Returns an array of HtmlSignature objects
//     */
//    public function findByExampleField($value): array
//    {
//        return $this->createQueryBuilder('h')
//            ->andWhere('h.exampleField = :val')
//            ->setParameter('val', $value)
//            ->orderBy('h.id', 'ASC')
//            ->setMaxResults(10)
//            ->getQuery()
//            ->getResult()
//        ;
//    }

//    public function findOneBySomeField($value): ?HtmlSignature
//    {
//        return $this->createQueryBuilder('h')
//            ->andWhere('h.exampleField = :val')
//            ->setParameter('val', $value)
//            ->getQuery()
//            ->getOneOrNullResult()
//        ;
//    }
}
