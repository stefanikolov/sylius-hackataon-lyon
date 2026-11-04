<?php

declare(strict_types=1);

namespace Hackathon\SyliusWishlistPlugin\Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Stage 2: several named wishlists per customer or guest.
 *
 * Existing Stage 1 wishlists are kept: each one becomes a list named "My Favorites" with all its products.
 */
final class Version20261104180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Wishlist plugin, stage 2: named wishlists, several per customer or guest.';
    }

    public function up(Schema $schema): void
    {
        // Existing rows get the default name through the column default, which is removed again right after.
        $this->addSql("ALTER TABLE hackathon_wishlist ADD name VARCHAR(255) DEFAULT 'My Favorites' NOT NULL");
        $this->addSql('ALTER TABLE hackathon_wishlist ALTER COLUMN name DROP DEFAULT');

        // The guest token and the customer are no longer unique: one owner can have many lists.
        $this->addSql('DROP INDEX UNIQ_F26AA04D5F37A13B ON hackathon_wishlist');
        $this->addSql('CREATE INDEX IDX_F26AA04D5F37A13B ON hackathon_wishlist (token)');

        $this->addSql('ALTER TABLE hackathon_wishlist DROP FOREIGN KEY FK_F26AA04D9395C3F3');
        $this->addSql('DROP INDEX UNIQ_F26AA04D9395C3F3 ON hackathon_wishlist');
        $this->addSql('CREATE INDEX IDX_F26AA04D9395C3F3 ON hackathon_wishlist (customer_id)');
        $this->addSql('ALTER TABLE hackathon_wishlist ADD CONSTRAINT FK_F26AA04D9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // Going back to one list per owner: keep the oldest list of each owner, drop the others.
        $this->addSql('DELETE w FROM hackathon_wishlist w INNER JOIN hackathon_wishlist o ON o.id < w.id AND ((w.customer_id IS NOT NULL AND o.customer_id = w.customer_id) OR (w.customer_id IS NULL AND o.customer_id IS NULL AND o.token = w.token))');

        $this->addSql('ALTER TABLE hackathon_wishlist DROP FOREIGN KEY FK_F26AA04D9395C3F3');
        $this->addSql('DROP INDEX IDX_F26AA04D9395C3F3 ON hackathon_wishlist');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F26AA04D9395C3F3 ON hackathon_wishlist (customer_id)');
        $this->addSql('ALTER TABLE hackathon_wishlist ADD CONSTRAINT FK_F26AA04D9395C3F3 FOREIGN KEY (customer_id) REFERENCES sylius_customer (id) ON DELETE CASCADE');

        $this->addSql('DROP INDEX IDX_F26AA04D5F37A13B ON hackathon_wishlist');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_F26AA04D5F37A13B ON hackathon_wishlist (token)');
        $this->addSql('ALTER TABLE hackathon_wishlist DROP name');
    }
}
