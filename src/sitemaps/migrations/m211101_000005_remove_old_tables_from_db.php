<?php

namespace BarrelStrength\Sprout\sitemaps\migrations;

use craft\db\Migration;

class m211101_000005_remove_old_tables_from_db extends Migration
{
    public const SITEMAPS_TABLE = '{{%sproutseo_sitemaps}}';

    public function safeUp(): void
    {
        $this->dropTableIfExists(self::SITEMAPS_TABLE);
    }

    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
