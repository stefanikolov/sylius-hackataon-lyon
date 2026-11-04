<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Command;

use MarkApp\SyliusWishlistPlugin\Service\WishlistManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'mark-app:wishlist:cleanup-guests', description: 'Removes empty and inactive guest wishlists.')]
final class CleanupGuestWishlistsCommand extends Command
{
    public function __construct(private readonly WishlistManager $manager)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('empty-after-hours', null, InputOption::VALUE_REQUIRED, 'Remove empty guest wishlists older than this many hours.', '24')
            ->addOption('inactive-after-days', null, InputOption::VALUE_REQUIRED, 'Remove guest wishlists untouched for this many days.', '90')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $hours = max(1, (int) $input->getOption('empty-after-hours'));
        $days = max(1, (int) $input->getOption('inactive-after-days'));

        $removed = $this->manager->deleteStaleGuestWishlists(
            new \DateTimeImmutable(sprintf('-%d hours', $hours)),
            new \DateTimeImmutable(sprintf('-%d days', $days)),
        );

        $io->success(sprintf('Removed %d guest wishlist(s).', $removed));

        return Command::SUCCESS;
    }
}
