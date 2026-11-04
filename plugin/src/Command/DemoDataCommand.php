<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Command;

use Doctrine\ORM\EntityManagerInterface;
use Hackathon\SyliusWishlistPlugin\Entity\Wishlist;
use Sylius\Component\Core\Model\CustomerInterface;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Factory\FactoryInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates example wishlists to demonstrate the administration (development and demo only).
 *
 * John Doe owns "My Favorites" (3 products), "Birthday" (5) and "Christmas" (2), Jane Smith has a "My Favorites"
 * with two products and an empty "Birthday", and one anonymous visitor has a "My Favorites" with two products.
 */
#[AsCommand(name: 'hackathon:wishlist:demo-data', description: 'Create example wishlists for the administration demo')]
final class DemoDataCommand extends Command
{
    private const GUEST_TOKEN_PREFIX = 'demo-guest-';

    /** @var array<string, array{first: string, last: string, lists: array<string, int>}> */
    private const CUSTOMERS = [
        'john@example.com' => ['first' => 'John', 'last' => 'Doe', 'lists' => ['My Favorites' => 3, 'Birthday' => 5, 'Christmas' => 2]],
        'jane@example.com' => ['first' => 'Jane', 'last' => 'Smith', 'lists' => ['My Favorites' => 2, 'Birthday' => 0]],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly FactoryInterface $customerFactory,
        private readonly RepositoryInterface $customerRepository,
        private readonly RepositoryInterface $productRepository,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('reset', null, InputOption::VALUE_NONE, 'Delete the demo wishlists first and create them again');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $variants = $this->variants();
        if (count($variants) < 8) {
            $io->error('The store needs at least 8 enabled products. Load the fixtures first.');

            return Command::FAILURE;
        }

        if ($input->getOption('reset')) {
            $this->deleteDemoWishlists();
        } elseif (null !== $this->customerRepository->findOneBy(['email' => 'john@example.com'])) {
            $io->note('The demo data already exists. Use --reset to create it again.');

            return Command::SUCCESS;
        }

        $offset = 0;
        foreach (self::CUSTOMERS as $email => $data) {
            $customer = $this->customerRepository->findOneBy(['email' => $email]);
            if (!$customer instanceof CustomerInterface) {
                $customer = $this->customerFactory->createNew();
                $customer->setEmail($email);
                $customer->setFirstName($data['first']);
                $customer->setLastName($data['last']);
                $this->entityManager->persist($customer);
            }

            foreach ($data['lists'] as $name => $count) {
                $wishlist = new Wishlist();
                $wishlist->setName($name);
                $wishlist->setCustomer($customer);
                $this->fill($wishlist, $variants, $offset, $count);
                $this->entityManager->persist($wishlist);
                $offset += 2;
            }
        }

        $guest = new Wishlist();
        $guest->setName(Wishlist::DEFAULT_NAME);
        $guest->setToken(self::GUEST_TOKEN_PREFIX . bin2hex(random_bytes(16)));
        $this->fill($guest, $variants, 1, 2);
        $this->entityManager->persist($guest);

        $this->entityManager->flush();
        $io->success('Demo wishlists created: John Doe (3 lists), Jane Smith (2 lists) and one guest list.');

        return Command::SUCCESS;
    }

    /** @param list<ProductVariantInterface> $variants */
    private function fill(Wishlist $wishlist, array $variants, int $offset, int $count): void
    {
        for ($i = 0; $i < $count; ++$i) {
            $wishlist->addVariant($variants[($offset + $i) % count($variants)]);
        }
    }

    /** @return list<ProductVariantInterface> the first enabled variant of each of the first enabled products */
    private function variants(): array
    {
        $variants = [];
        foreach ($this->productRepository->findBy(['enabled' => true], ['id' => 'ASC'], 12) as $product) {
            if (!$product instanceof ProductInterface) {
                continue;
            }
            $variant = $product->getEnabledVariants()->first();
            if ($variant instanceof ProductVariantInterface) {
                $variants[] = $variant;
            }
        }

        return $variants;
    }

    private function deleteDemoWishlists(): void
    {
        $repository = $this->entityManager->getRepository(Wishlist::class);

        foreach (array_keys(self::CUSTOMERS) as $email) {
            $customer = $this->customerRepository->findOneBy(['email' => $email]);
            if (null !== $customer) {
                foreach ($repository->findBy(['customer' => $customer]) as $wishlist) {
                    $this->entityManager->remove($wishlist);
                }
            }
        }

        foreach ($repository->createQueryBuilder('w')->where('w.token LIKE :prefix')->setParameter('prefix', self::GUEST_TOKEN_PREFIX . '%')->getQuery()->getResult() as $wishlist) {
            $this->entityManager->remove($wishlist);
        }

        $this->entityManager->flush();
    }
}
