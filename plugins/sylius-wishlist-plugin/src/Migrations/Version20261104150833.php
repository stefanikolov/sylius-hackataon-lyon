<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104150833 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Remember the chosen product variant on every wishlist item.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mark_app_wishlist_item ADD variant_id INT DEFAULT NULL');

        // Items saved before this version only knew their product: use the product's first variant.
        $this->addSql('UPDATE mark_app_wishlist_item i SET i.variant_id = (SELECT v.id FROM sylius_product_variant v WHERE v.product_id = i.product_id ORDER BY v.position ASC, v.id ASC LIMIT 1)');
        // A Sylius product always has a variant; an item without any cannot be bought and cannot be migrated.
        $this->addSql('DELETE FROM mark_app_wishlist_item WHERE variant_id IS NULL');

        $this->addSql('ALTER TABLE mark_app_wishlist_item CHANGE variant_id variant_id INT NOT NULL');
        $this->addSql('ALTER TABLE mark_app_wishlist_item ADD CONSTRAINT FK_7551A6DE3B69A9AF FOREIGN KEY (variant_id) REFERENCES sylius_product_variant (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_7551A6DE3B69A9AF ON mark_app_wishlist_item (variant_id)');
        $this->addSql('DROP INDEX uniq_mark_app_wishlist_item_product ON mark_app_wishlist_item');
        $this->addSql('CREATE UNIQUE INDEX uniq_mark_app_wishlist_item_variant ON mark_app_wishlist_item (wishlist_id, variant_id)');
    }

    public function down(Schema $schema): void
    {
        // The old unique key allowed one entry per product: keep the oldest entry of each product.
        $this->addSql('DELETE i FROM mark_app_wishlist_item i INNER JOIN mark_app_wishlist_item j ON i.wishlist_id = j.wishlist_id AND i.product_id = j.product_id AND i.id > j.id');

        $this->addSql('DROP INDEX uniq_mark_app_wishlist_item_variant ON mark_app_wishlist_item');
        $this->addSql('CREATE UNIQUE INDEX uniq_mark_app_wishlist_item_product ON mark_app_wishlist_item (wishlist_id, product_id)');
        $this->addSql('ALTER TABLE mark_app_wishlist_item DROP FOREIGN KEY FK_7551A6DE3B69A9AF');
        $this->addSql('DROP INDEX IDX_7551A6DE3B69A9AF ON mark_app_wishlist_item');
        $this->addSql('ALTER TABLE mark_app_wishlist_item DROP variant_id');
    }
}
