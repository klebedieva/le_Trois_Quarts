<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250929121846 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reviews ADD menu_item_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE reviews ADD CONSTRAINT FK_6970EB0F9AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) ON DELETE SET NULL');
        $this->addSql('CREATE INDEX IDX_6970EB0F9AB44FE0 ON reviews (menu_item_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE reviews DROP FOREIGN KEY FK_6970EB0F9AB44FE0');
        $this->addSql('DROP INDEX IDX_6970EB0F9AB44FE0 ON reviews');
        $this->addSql('ALTER TABLE reviews DROP menu_item_id');
    }
}
