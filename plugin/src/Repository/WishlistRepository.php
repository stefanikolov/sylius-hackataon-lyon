<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Hackathon\SyliusWishlistPlugin\Entity\Wishlist;
use Sylius\Component\Core\Model\CustomerInterface;

/** @extends ServiceEntityRepository<Wishlist> */
class WishlistRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Wishlist::class);
    }

    /** @return list<Wishlist> oldest first, so the first one is the default list */
    public function findByCustomer(CustomerInterface $customer): array
    {
        return $this->findBy(['customer' => $customer], ['id' => 'ASC']);
    }

    /**
     * Only anonymous lists are returned (a token that already belongs to a customer is ignored).
     *
     * @return list<Wishlist>
     */
    public function findByGuestToken(string $token): array
    {
        return $this->findBy(['token' => $token, 'customer' => null], ['id' => 'ASC']);
    }

    /**
     * Rows for the administration listing: one entry per wishlist with its owner and the number of products.
     * The optional search matches the owner's name or e-mail, and the word "guest" finds anonymous lists.
     *
     * @return list<array{id: int, name: string, createdAt: \DateTimeImmutable, customerId: int|null, firstName: string|null, lastName: string|null, email: string|null, itemCount: int|string}>
     */
    public function findForAdmin(?string $search, int $page, int $perPage): array
    {
        $qb = $this->createQueryBuilder('w')
            ->select('w.id AS id', 'w.name AS name', 'w.createdAt AS createdAt', 'c.id AS customerId', 'c.firstName AS firstName', 'c.lastName AS lastName', 'c.email AS email', 'COUNT(i.id) AS itemCount')
            ->leftJoin('w.customer', 'c')
            ->leftJoin('w.items', 'i')
            ->groupBy('w.id', 'c.id')
            ->orderBy('w.id', 'DESC')
            ->setFirstResult(max(0, $page - 1) * $perPage)
            ->setMaxResults($perPage);
        $this->applySearch($qb, $search);

        return $qb->getQuery()->getArrayResult();
    }

    public function countForAdmin(?string $search): int
    {
        $qb = $this->createQueryBuilder('w')
            ->select('COUNT(w.id)')
            ->leftJoin('w.customer', 'c');
        $this->applySearch($qb, $search);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applySearch(QueryBuilder $qb, ?string $search): void
    {
        $search = null === $search ? '' : trim($search);
        if ('' === $search) {
            return;
        }

        $like = '%' . addcslashes($search, "%_\\") . '%';
        $or = $qb->expr()->orX(
            'c.firstName LIKE :search',
            'c.lastName LIKE :search',
            'c.email LIKE :search',
            "CONCAT(c.firstName, ' ', c.lastName) LIKE :search",
            'w.name LIKE :search',
        );
        if (0 === strcasecmp($search, 'guest')) {
            $or->add('w.customer IS NULL');
        }

        $qb->andWhere($or)->setParameter('search', $like);
    }
}
