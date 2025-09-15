<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250910123239 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_message ADD replied_by_id INT DEFAULT NULL, ADD is_replied TINYINT(1) NOT NULL, ADD replied_at DATETIME DEFAULT NULL, ADD reply_message LONGTEXT DEFAULT NULL');
        $this->addSql('ALTER TABLE contact_message ADD CONSTRAINT FK_2C9211FED6FBBEB5 FOREIGN KEY (replied_by_id) REFERENCES users (id)');
        $this->addSql('CREATE INDEX IDX_2C9211FED6FBBEB5 ON contact_message (replied_by_id)');
        $this->addSql('ALTER TABLE reviews DROP consent, CHANGE name name VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE contact_message DROP FOREIGN KEY FK_2C9211FED6FBBEB5');
        $this->addSql('DROP INDEX IDX_2C9211FED6FBBEB5 ON contact_message');
        $this->addSql('ALTER TABLE contact_message DROP replied_by_id, DROP is_replied, DROP replied_at, DROP reply_message');
        $this->addSql('ALTER TABLE reviews ADD consent TINYINT(1) NOT NULL, CHANGE name name VARCHAR(80) NOT NULL, CHANGE email email VARCHAR(180) DEFAULT NULL');
    }
}
