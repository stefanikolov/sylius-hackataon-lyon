<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Let a guest own several wishlists and keep one entry per product in a list (the last chosen variant).';
    }

    public function up(Schema $schema): void
    {
        // A guest token no longer identifies a single list: several lists of one guest share it.
        $this->addSql('DROP INDEX UNIQ_93ECA8F9B6A2DD68 ON mark_app_wishlist');
        $this->addSql('CREATE INDEX idx_mark_app_wishlist_access_token ON mark_app_wishlist (access_token)');

        // A product occurs once per list. Where one list held several variants of a product, keep the newest entry.
        $this->addSql('DELETE i FROM mark_app_wishlist_item i INNER JOIN mark_app_wishlist_item j ON i.wishlist_id = j.wishlist_id AND i.product_id = j.product_id AND i.id < j.id');
        $this->addSql('DROP INDEX uniq_mark_app_wishlist_item_variant ON mark_app_wishlist_item');
        $this->addSql('CREATE UNIQUE INDEX uniq_mark_app_wishlist_item_product ON mark_app_wishlist_item (wishlist_id, product_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_mark_app_wishlist_item_product ON mark_app_wishlist_item');
        $this->addSql('CREATE UNIQUE INDEX uniq_mark_app_wishlist_item_variant ON mark_app_wishlist_item (wishlist_id, variant_id)');

        // The old key allowed one list per guest: keep the oldest list of each guest.
        $this->addSql('DELETE w FROM mark_app_wishlist w INNER JOIN mark_app_wishlist x ON w.access_token = x.access_token AND w.customer_id IS NULL AND x.customer_id IS NULL AND w.id > x.id');
        $this->addSql('DROP INDEX idx_mark_app_wishlist_access_token ON mark_app_wishlist');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_93ECA8F9B6A2DD68 ON mark_app_wishlist (access_token)');
    }
}
