<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/** Stage 1: wishlist and wishlist item tables. */
final class Version20261104143025 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wishlist plugin, stage 1: hackathon_wishlist and hackathon_wishlist_item tables.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE hackathon_wishlist (id INT AUTO_INCREMENT NOT NULL, token VARCHAR(64) DEFAULT NULL, createdAt DATETIME NOT NULL, customer_id INT DEFAULT NULL, UNIQUE INDEX UNIQ_F26AA04D5F37A13B (token), UNIQUE INDEX UNIQ_F26AA04D9395C3F3 (customer_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('CREATE TABLE hackathon_wishlist_item (id INT AUTO_INCREMENT NOT NULL, createdAt DATETIME NOT NULL, wishlist_id INT NOT NULL, variant_id INT NOT NULL, INDEX IDX_CCFC1D31FB8E54CD (wishlist_id), INDEX IDX_CCFC1D313B69A9AF (variant_id), UNIQUE INDEX uniq_wishlist_variant (wishlist_id, variant_id), PRIMARY KEY (id)) DEFAULT CHARACTER SET utf8mb4');
        $this->addSql('ALTER TABLE hackathon_wishlist ADD CONSTRAINT FK_F26AA04D9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hackathon_wishlist_item ADD CONSTRAINT FK_CCFC1D31FB8E54CD FOREIGN KEY (wishlist_id) REFERENCES hackathon_wishlist (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE hackathon_wishlist_item ADD CONSTRAINT FK_CCFC1D313B69A9AF FOREIGN KEY (variant_id) REFERENCES sylius_product_variant (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE hackathon_wishlist_item DROP FOREIGN KEY FK_CCFC1D31FB8E54CD');
        $this->addSql('ALTER TABLE hackathon_wishlist_item DROP FOREIGN KEY FK_CCFC1D313B69A9AF');
        $this->addSql('ALTER TABLE hackathon_wishlist DROP FOREIGN KEY FK_F26AA04D9395C3F3');
        $this->addSql('DROP TABLE hackathon_wishlist_item');
        $this->addSql('DROP TABLE hackathon_wishlist');
    }
}
