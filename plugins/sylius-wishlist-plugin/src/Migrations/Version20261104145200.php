<?php

declare(strict_types=1);

namespace MarkApp\SyliusWishlistPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261104145200 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the wishlist and wishlist item tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE mark_app_wishlist (name VARCHAR(100) NOT NULL, access_token VARCHAR(64) DEFAULT NULL, share_token VARCHAR(64) NOT NULL, is_public TINYINT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, customer_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_93ECA8F9B6A2DD68 (access_token), UNIQUE INDEX UNIQ_93ECA8F9D6594DD6 (share_token), INDEX IDX_93ECA8F99395C3F3 (customer_id), INDEX idx_mark_app_wishlist_updated_at (updated_at), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE mark_app_wishlist_item (created_at DATETIME NOT NULL, id INT AUTO_INCREMENT NOT NULL, wishlist_id INT NOT NULL, product_id INT NOT NULL, INDEX IDX_7551A6DEFB8E54CD (wishlist_id), INDEX IDX_7551A6DE4584665A (product_id), UNIQUE INDEX uniq_mark_app_wishlist_item_product (wishlist_id, product_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE mark_app_wishlist ADD CONSTRAINT FK_93ECA8F99395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mark_app_wishlist_item ADD CONSTRAINT FK_7551A6DEFB8E54CD FOREIGN KEY (wishlist_id) REFERENCES mark_app_wishlist (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE mark_app_wishlist_item ADD CONSTRAINT FK_7551A6DE4584665A FOREIGN KEY (product_id) REFERENCES sylius_product (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE mark_app_wishlist DROP FOREIGN KEY FK_93ECA8F99395C3F3');
        $this->addSql('ALTER TABLE mark_app_wishlist_item DROP FOREIGN KEY FK_7551A6DEFB8E54CD');
        $this->addSql('ALTER TABLE mark_app_wishlist_item DROP FOREIGN KEY FK_7551A6DE4584665A');
        $this->addSql('DROP TABLE mark_app_wishlist');
        $this->addSql('DROP TABLE mark_app_wishlist_item');
    }
}
