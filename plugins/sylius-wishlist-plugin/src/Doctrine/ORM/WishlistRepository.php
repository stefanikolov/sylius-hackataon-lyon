<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Doctrine\ORM;

use MarkApp\SyliusWishlistPlugin\Entity\WishlistInterface;
use MarkApp\SyliusWishlistPlugin\Repository\WishlistRepositoryInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\CustomerInterface;

class WishlistRepository extends EntityRepository implements WishlistRepositoryInterface
{
    public function findByGuestToken(string $accessToken): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.accessToken = :accessToken')
            ->andWhere('o.customer IS NULL')
            ->setParameter('accessToken', $accessToken)
            ->orderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneByIdAndGuestToken(int $id, string $accessToken): ?WishlistInterface
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.id = :id')
            ->andWhere('o.accessToken = :accessToken')
            ->andWhere('o.customer IS NULL')
            ->setParameter('id', $id)
            ->setParameter('accessToken', $accessToken)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function countByGuestToken(string $accessToken): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.accessToken = :accessToken')
            ->andWhere('o.customer IS NULL')
            ->setParameter('accessToken', $accessToken)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function hasNameForGuestToken(string $accessToken, string $name, ?int $excludedId = null): bool
    {
        $queryBuilder = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.accessToken = :accessToken')
            ->andWhere('o.customer IS NULL')
            ->andWhere('LOWER(o.name) = :name')
            ->setParameter('accessToken', $accessToken)
            ->setParameter('name', mb_strtolower($name))
        ;

        if (null !== $excludedId) {
            $queryBuilder->andWhere('o.id != :excludedId')->setParameter('excludedId', $excludedId);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult() > 0;
    }

    public function findOnePublicByShareToken(string $shareToken): ?WishlistInterface
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.shareToken = :shareToken')
            ->andWhere('o.public = true')
            ->setParameter('shareToken', $shareToken)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function findByCustomer(CustomerInterface $customer): array
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->orderBy('o.id', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    public function findOneByIdAndCustomer(int $id, CustomerInterface $customer): ?WishlistInterface
    {
        return $this->createQueryBuilder('o')
            ->andWhere('o.id = :id')
            ->andWhere('o.customer = :customer')
            ->setParameter('id', $id)
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    public function countByCustomer(CustomerInterface $customer): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countItemsForCustomer(CustomerInterface $customer): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(item.id)')
            ->innerJoin('o.items', 'item')
            ->andWhere('o.customer = :customer')
            ->setParameter('customer', $customer)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function countItemsForGuest(string $accessToken): int
    {
        return (int) $this->createQueryBuilder('o')
            ->select('COUNT(item.id)')
            ->innerJoin('o.items', 'item')
            ->andWhere('o.accessToken = :accessToken')
            ->andWhere('o.customer IS NULL')
            ->setParameter('accessToken', $accessToken)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    public function hasNameForCustomer(CustomerInterface $customer, string $name, ?int $excludedId = null): bool
    {
        $queryBuilder = $this->createQueryBuilder('o')
            ->select('COUNT(o.id)')
            ->andWhere('o.customer = :customer')
            ->andWhere('LOWER(o.name) = :name')
            ->setParameter('customer', $customer)
            ->setParameter('name', mb_strtolower($name))
        ;

        if (null !== $excludedId) {
            $queryBuilder->andWhere('o.id != :excludedId')->setParameter('excludedId', $excludedId);
        }

        return (int) $queryBuilder->getQuery()->getSingleScalarResult() > 0;
    }

    public function findStaleGuestWishlists(\DateTimeImmutable $emptyBefore, \DateTimeImmutable $inactiveBefore): array
    {
        $inactive = $this->createQueryBuilder('o')
            ->select('o.accessToken')
            ->andWhere('o.customer IS NULL')
            ->andWhere('o.accessToken IS NOT NULL')
            ->groupBy('o.accessToken')
            ->having('MAX(o.updatedAt) < :inactiveBefore')
            ->setParameter('inactiveBefore', $inactiveBefore)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        $empty = $this->createQueryBuilder('o')
            ->select('o.accessToken')
            ->leftJoin('o.items', 'item')
            ->andWhere('o.customer IS NULL')
            ->andWhere('o.accessToken IS NOT NULL')
            ->groupBy('o.accessToken')
            ->having('COUNT(item.id) = 0 AND MAX(o.createdAt) < :emptyBefore')
            ->setParameter('emptyBefore', $emptyBefore)
            ->getQuery()
            ->getSingleColumnResult()
        ;

        $tokens = array_values(array_unique([...$inactive, ...$empty]));

        if ([] === $tokens) {
            return [];
        }

        return $this->createQueryBuilder('o')
            ->andWhere('o.customer IS NULL')
            ->andWhere('o.accessToken IN (:tokens)')
            ->setParameter('tokens', $tokens)
            ->getQuery()
            ->getResult()
        ;
    }
}
