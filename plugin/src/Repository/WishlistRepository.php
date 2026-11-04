<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
}
