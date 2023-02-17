<?php

namespace BarrelStrength\Sprout\sitemaps\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Table;

class m211101_000000_run_install_migration extends Migration
{
    public const SPROUT_KEY = 'sprout';
    public const MODULE_ID = 'sprout-module-sitemaps';
    public const MODULE_CLASS = 'BarrelStrength\Sprout\sitemaps\SitemapsModule';
    public const SITEMAP_AGGREGATION_METHOD_SETTING = 'singleLanguageSitemaps';
    public const SITEMAPS_TABLE = '{{%sprout_sitemaps}}';

    public function safeUp(): void
    {
        $moduleSettingsKey = self::SPROUT_KEY . '.' . self::MODULE_ID;
        $coreModuleSettingsKey = $moduleSettingsKey . '.modules.' . self::MODULE_CLASS;

        $this->createTables();

        $primarySite = Craft::$app->getSites()->getPrimarySite();

        Craft::$app->getProjectConfig()->set($moduleSettingsKey, [
            'enableDynamicSitemaps' => true,
            'sitemapAggregationMethod' => self::SITEMAP_AGGREGATION_METHOD_SETTING,
            'totalElementsPerSitemap' => 3,
            'siteSettings' => [$primarySite->id],
            'groupSettings' => [$primarySite->groupId],
        ], "Update Sprout CP Settings for “{$moduleSettingsKey}”");

        Craft::$app->getProjectConfig()->set($coreModuleSettingsKey, [
            'alternateName' => '',
            'enabled' => true,
        ]);
    }

    public function safeDown(): bool
    {
        echo "m211101_000000_run_install_migration cannot be reverted.\n";

        return false;
    }

    public function createTables(): void
    {
        if (!$this->db->tableExists(self::SITEMAPS_TABLE)) {
            $this->createTable(self::SITEMAPS_TABLE, [
                'id' => $this->primaryKey(),
                'siteId' => $this->integer()->notNull(),
                'uniqueKey' => $this->string(),
                'urlEnabledSectionId' => $this->integer(),
                'enabled' => $this->boolean()->defaultValue(false),
                'type' => $this->string(),
                'uri' => $this->string(),
                'priority' => $this->decimal(11, 1),
                'changeFrequency' => $this->string(),
                'dateCreated' => $this->dateTime()->notNull(),
                'dateUpdated' => $this->dateTime()->notNull(),
                'uid' => $this->uid(),
            ]);

            $this->createIndex(null, self::SITEMAPS_TABLE, ['siteId']);

            $this->addForeignKey(null, self::SITEMAPS_TABLE, ['siteId'], Table::SITES, ['id'], 'CASCADE', 'CASCADE');
        }
    }
}