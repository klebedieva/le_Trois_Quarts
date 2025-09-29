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
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE menu_item_allergen DROP FOREIGN KEY FK_EF7195936E775A4A');
        $this->addSql('ALTER TABLE menu_item_allergen DROP FOREIGN KEY FK_EF7195939AB44FE0');
        $this->addSql('DROP TABLE allergen');
        $this->addSql('DROP TABLE menu_item_allergen');
        $this->addSql('ALTER TABLE menu_item DROP ingredients, DROP preparation, DROP prep_time_minutes, DROP chef_tip, DROP nutrition_calories_kcal, DROP nutrition_proteins_g, DROP nutrition_carbs_g, DROP nutrition_fats_g, DROP nutrition_fiber_g, DROP nutrition_sodium_mg, DROP prep_time_min, DROP prep_time_max');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE allergen (id INT AUTO_INCREMENT NOT NULL, name VARCHAR(100) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, code VARCHAR(50) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, UNIQUE INDEX UNIQ_25BF08CE77153098 (code), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('CREATE TABLE menu_item_allergen (menu_item_id INT NOT NULL, allergen_id INT NOT NULL, INDEX IDX_EF7195939AB44FE0 (menu_item_id), INDEX IDX_EF7195936E775A4A (allergen_id), PRIMARY KEY(menu_item_id, allergen_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE menu_item_allergen ADD CONSTRAINT FK_EF7195936E775A4A FOREIGN KEY (allergen_id) REFERENCES allergen (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item_allergen ADD CONSTRAINT FK_EF7195939AB44FE0 FOREIGN KEY (menu_item_id) REFERENCES menu_item (id) ON UPDATE NO ACTION ON DELETE CASCADE');
        $this->addSql('ALTER TABLE menu_item ADD ingredients LONGTEXT DEFAULT NULL, ADD preparation LONGTEXT DEFAULT NULL, ADD prep_time_minutes INT DEFAULT NULL, ADD chef_tip LONGTEXT DEFAULT NULL, ADD nutrition_calories_kcal INT DEFAULT NULL, ADD nutrition_proteins_g NUMERIC(6, 1) DEFAULT NULL, ADD nutrition_carbs_g NUMERIC(6, 1) DEFAULT NULL, ADD nutrition_fats_g NUMERIC(6, 1) DEFAULT NULL, ADD nutrition_fiber_g NUMERIC(6, 1) DEFAULT NULL, ADD nutrition_sodium_mg INT DEFAULT NULL, ADD prep_time_min INT DEFAULT NULL, ADD prep_time_max INT DEFAULT NULL');
    }
}
