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

    public function findOneByCustomer(CustomerInterface $customer): ?Wishlist
    {
        return $this->findOneBy(['customer' => $customer]);
    }

    /** Only anonymous wishlists are returned (a token that already belongs to a customer is ignored). */
    public function findOneByGuestToken(string $token): ?Wishlist
    {
        return $this->findOneBy(['token' => $token, 'customer' => null]);
    }
}
