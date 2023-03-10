<?php

namespace BarrelStrength\Sprout\datastudio\migrations;

use Craft;
use craft\db\Migration;
use craft\helpers\App;

class m211101_000003_add_reports_editions extends Migration
{
    public function safeUp(): void
    {
        // Don't make the same config changes twice
        $projectConfig = Craft::$app->getProjectConfig();
        $schemaVersion = $projectConfig->get('plugins.sprout-reports.schemaVersion', true);

        if ($schemaVersion) {
            $edition = App::editionHandle(Craft::Pro);
            Craft::$app->getPlugins()->switchEdition('sprout-data-studio', $edition);
        }
    }

    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
