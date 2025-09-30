<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250929130204 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // NO-OP: Disabled destructive operations to keep schema stable
        return;
    }

    public function down(Schema $schema): void
    {
        // NO-OP: Do nothing on downgrade
        return;
    }
}
