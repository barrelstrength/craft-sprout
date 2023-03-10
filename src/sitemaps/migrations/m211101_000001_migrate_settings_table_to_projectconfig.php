<?php

namespace BarrelStrength\Sprout\sitemaps\migrations;

use Craft;
use craft\db\Migration;
use craft\db\Query;
use craft\helpers\Json;

class m211101_000001_migrate_settings_table_to_projectconfig extends Migration
{
    public const SPROUT_KEY = 'sprout';
    public const MODULES_KEY = self::SPROUT_KEY . '.sprout-module-core.modules';
    public const MODULE_ID = 'sprout-module-sitemaps';
    public const MODULE_CLASS = 'BarrelStrength\Sprout\sitemaps\SitemapsModule';
    public const OLD_SETTINGS_CLASS = 'barrelstrength\sproutbasesitemaps\models\Settings';
    public const OLD_SETTINGS_TABLE = '{{%sprout_settings_craft3}}';

    public const AGGREGATION_METHOD_SINGLE_LANGUAGE = 'singleLanguageSitemaps';
    public const AGGREGATION_METHOD_MULTI_LINGUAL = 'multiLingualSitemaps';

    public function safeUp(): void
    {
        $moduleSettingsKey = self::SPROUT_KEY . '.' . self::MODULE_ID;
        $coreModuleSettingsKey = self::MODULES_KEY . '.' . self::MODULE_CLASS;

        if (!$this->db->tableExists(self::OLD_SETTINGS_TABLE)) {
            return;
        }

        // Get shared sprout settings from old schema
        $oldSettings = (new Query())
            ->select([
                'model',
                'settings',
            ])
            ->from([self::OLD_SETTINGS_TABLE])
            ->where([
                'model' => self::OLD_SETTINGS_CLASS,
            ])
            ->one();

        if (empty($oldSettings)) {
            Craft::warning('No shared settings found to migrate: ' . self::MODULE_ID);

            return;
        }

        // Prepare old settings for new settings format
        $newSettings = Json::decode($oldSettings['settings']);
        $newSettings = $this->prepareSettingsForMigration($newSettings);

        $newCoreSettings = [
            'enabled' => true,
        ];

        unset($newSettings['pluginNameOverride']);

        Craft::$app->getProjectConfig()->set($moduleSettingsKey, $newSettings,
            'Update Sprout Settings for “' . $moduleSettingsKey . '”'
        );

        Craft::$app->getProjectConfig()->set($coreModuleSettingsKey, $newCoreSettings,
            'Update Sprout Core Settings for “' . $coreModuleSettingsKey . '”'
        );

        $this->delete(self::OLD_SETTINGS_TABLE, ['model' => self::OLD_SETTINGS_CLASS]);

        $this->deleteSettingsTableIfEmpty();
    }

    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }

    public function prepareSettingsForMigration($newSettings): array
    {
        $primarySite = Craft::$app->getSites()->getPrimarySite();

        // Just in case
        if (empty($newSettings['siteSettings'])) {
            $newSettings['siteSettings'][$primarySite->id] = $primarySite->id;
        }

        if (empty($newSettings['groupSettings'])) {
            $newSettings['groupSettings'][$primarySite->groupId] = $primarySite->groupId;
        }

        // Ensure proper data types
        if (!is_int($newSettings['totalElementsPerSitemap'])) {
            $newSettings['totalElementsPerSitemap'] = (int)$newSettings['totalElementsPerSitemap'];
        }

        if ($newSettings['enableCustomSections'] === '1') {
            $newSettings['enableCustomSections'] = true;
        }

        if ($newSettings['enableCustomSections'] === '') {
            $newSettings['enableCustomSections'] = false;
        }

        if ($newSettings['enableMultilingualSitemaps'] === '1') {
            $newSettings['sitemapAggregationMethod'] = self::AGGREGATION_METHOD_SINGLE_LANGUAGE;
        }

        if ($newSettings['enableMultilingualSitemaps'] === '') {
            $newSettings['sitemapAggregationMethod'] = self::AGGREGATION_METHOD_MULTI_LINGUAL;
        }

        // TODO - migrate enableDynamicSitemaps

        unset(
            $newSettings['enableMultiLingualSitemaps'],
            $newSettings['enableDynamicSitemaps'],
        );

        return $newSettings;
    }

    public function deleteSettingsTableIfEmpty(): void
    {
        $oldSettings = (new Query())
            ->select('*')
            ->from([self::OLD_SETTINGS_TABLE])
            ->all();

        if (empty($oldSettings)) {
            $this->dropTableIfExists(self::OLD_SETTINGS_TABLE);
        }
    }
}
