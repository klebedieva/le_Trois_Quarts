<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251006144447 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` CHANGE status status VARCHAR(255) DEFAULT \'pending\' NOT NULL, CHANGE delivery_mode delivery_mode VARCHAR(255) DEFAULT \'delivery\' NOT NULL, CHANGE payment_mode payment_mode VARCHAR(255) DEFAULT \'card\' NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `order` CHANGE status status VARCHAR(20) NOT NULL, CHANGE delivery_mode delivery_mode VARCHAR(20) NOT NULL, CHANGE payment_mode payment_mode VARCHAR(20) NOT NULL');
    }
}
