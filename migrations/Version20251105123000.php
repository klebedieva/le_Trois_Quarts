<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\DBAL\Schema\ForeignKeyConstraint;
use Doctrine\Migrations\AbstractMigration;

final class Version20251105123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Harden FK constraints and add indexes: cascade on m2m link tables; add helpful indexes on FK columns. No data mutations.';
    }

    public function up(Schema $schema): void
    {
        // Helper to (re)create cascading foreign keys on a link table
        $this->ensureLinkTableCascade($schema, 'menu_item_allergen', 'menu_item', 'menu_item_id', 'allergen', 'allergen_id');
        $this->ensureLinkTableCascade($schema, 'menu_item_badge', 'menu_item', 'menu_item_id', 'badge', 'badge_id');
        $this->ensureLinkTableCascade($schema, 'menu_item_tag', 'menu_item', 'menu_item_id', 'tag', 'tag_id');

        // Ensure composite (non-unique) pair indexes exist on link tables for performance
        $this->ensureIndex($schema, 'menu_item_allergen', ['menu_item_id', 'allergen_id'], 'idx_mi_allergen_pair');
        $this->ensureIndex($schema, 'menu_item_badge', ['menu_item_id', 'badge_id'], 'idx_mi_badge_pair');
        $this->ensureIndex($schema, 'menu_item_tag', ['menu_item_id', 'tag_id'], 'idx_mi_tag_pair');

        // Ensure FK column indexes
        $this->ensureIndex($schema, 'order_item', ['order_id'], 'idx_order_item_order');
        $this->ensureIndex($schema, 'order_item', ['menu_item_id'], 'idx_order_item_menuitem');
        $this->ensureIndex($schema, 'order', ['coupon_id'], 'idx_order_coupon');
        $this->ensureIndex($schema, 'reviews', ['menu_item_id'], 'idx_reviews_menu_item');
        $this->ensureIndex($schema, 'contact_message', ['replied_by_id'], 'idx_contact_msg_replied_by');
    }

    public function down(Schema $schema): void
    {
        // We only drop the indexes we explicitly created; we keep FKs as-is (safer)
        $this->dropIndexIfExists($schema, 'menu_item_allergen', 'idx_mi_allergen_pair');
        $this->dropIndexIfExists($schema, 'menu_item_badge', 'idx_mi_badge_pair');
        $this->dropIndexIfExists($schema, 'menu_item_tag', 'idx_mi_tag_pair');

        $this->dropIndexIfExists($schema, 'order_item', 'idx_order_item_order');
        $this->dropIndexIfExists($schema, 'order_item', 'idx_order_item_menuitem');
        $this->dropIndexIfExists($schema, 'order', 'idx_order_coupon');
        $this->dropIndexIfExists($schema, 'reviews', 'idx_reviews_menu_item');
        $this->dropIndexIfExists($schema, 'contact_message', 'idx_contact_msg_replied_by');
    }

    private function ensureLinkTableCascade(Schema $schema, string $linkTableName, string $leftTable, string $leftCol, string $rightTable, string $rightCol): void
    {
        if (!$schema->hasTable($linkTableName)) {
            return;
        }
        $table = $schema->getTable($linkTableName);

        // Drop existing FKs on the two columns (if present)
        foreach ($table->getForeignKeys() as $fk) {
            $localColumns = $fk->getLocalColumns();
            if (in_array($leftCol, $localColumns, true) || in_array($rightCol, $localColumns, true)) {
                $table->removeForeignKey($fk->getName());
            }
        }

        // Recreate FKs with ON DELETE CASCADE
        $table->addForeignKeyConstraint($leftTable, [$leftCol], ['id'], ['onDelete' => 'CASCADE'], 'fk_'.$linkTableName.'_'.$leftCol);
        $table->addForeignKeyConstraint($rightTable, [$rightCol], ['id'], ['onDelete' => 'CASCADE'], 'fk_'.$linkTableName.'_'.$rightCol);

        // Ensure single-column indexes exist (DBAL usually creates them, but be explicit)
        $this->ensureIndex($schema, $linkTableName, [$leftCol], 'idx_'.$linkTableName.'_'.$leftCol);
        $this->ensureIndex($schema, $linkTableName, [$rightCol], 'idx_'.$linkTableName.'_'.$rightCol);
    }

    private function ensureIndex(Schema $schema, string $tableName, array $columns, string $indexName): void
    {
        if (!$schema->hasTable($tableName)) {
            return;
        }
        $table = $schema->getTable($tableName);
        if (!$table->hasIndex($indexName)) {
            $table->addIndex($columns, $indexName);
        }
    }

    private function dropIndexIfExists(Schema $schema, string $tableName, string $indexName): void
    {
        if (!$schema->hasTable($tableName)) {
            return;
        }
        $table = $schema->getTable($tableName);
        if ($table->hasIndex($indexName)) {
            $table->dropIndex($indexName);
        }
    }
}


