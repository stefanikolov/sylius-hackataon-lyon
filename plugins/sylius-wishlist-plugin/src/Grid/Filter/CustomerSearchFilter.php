<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Grid\Filter;

use Sylius\Component\Grid\Data\DataSourceInterface;
use Sylius\Component\Grid\Filtering\FilterInterface;

/**
 * Finds wishlists by the name or e-mail address of their owner. The search text is split in words and every word must
 * match the first name, the last name or the e-mail address, so "John Doe" finds the customer John Doe. Guest wishlists
 * have no customer and never match.
 */
final class CustomerSearchFilter implements FilterInterface
{
    private const FIELDS = ['customer.firstName', 'customer.lastName', 'customer.email'];

    /**
     * @param array{value?: string|null}|string|null $data
     * @param array<string, mixed> $options
     */
    public function apply(DataSourceInterface $dataSource, string $name, $data, array $options): void
    {
        $value = \is_array($data) ? ($data['value'] ?? null) : $data;
        $words = preg_split('/\s+/', trim((string) $value), -1, \PREG_SPLIT_NO_EMPTY);

        if (false === $words || [] === $words) {
            return;
        }

        $expressionBuilder = $dataSource->getExpressionBuilder();
        $perWord = [];

        foreach ($words as $word) {
            $pattern = '%' . $word . '%';
            $perWord[] = $expressionBuilder->orX(...array_map(
                static fn (string $field) => $expressionBuilder->like($field, $pattern),
                self::FIELDS,
            ));
        }

        $dataSource->restrict(1 === \count($perWord) ? $perWord[0] : $expressionBuilder->andX(...$perWord));
    }
}
