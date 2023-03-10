<?php

namespace BarrelStrength\Sprout\meta\migrations;

use Craft;
use craft\db\Migration;

class m211101_000001_update_seo_projectconfig extends Migration
{
    public const SPROUT_KEY = 'sprout';
    public const MODULE_ID = 'sprout-module-meta';
    public const OLD_CONFIG_KEY = 'plugins.sprout-seo.settings';
    public const SETTING_METADATA_VARIABLE = 'metadata';

    public function safeUp(): void
    {
        $moduleSettingsKey = self::SPROUT_KEY . '.' . self::MODULE_ID;

        $defaultSettings = [
            'enableRenderMetadata' => true,
            'maxMetaDescriptionLength' => 160,
            'metadataVariableName' => self::SETTING_METADATA_VARIABLE,
            'useMetadataVariable' => false,
        ];

        $oldConfig = Craft::$app->getProjectConfig()->get(self::OLD_CONFIG_KEY) ?? [];
        $newConfig = [];

        foreach ($defaultSettings as $key => $defaultValue) {
            $newConfig[$key] = isset($oldConfig[$key]) ? $oldConfig[$key] ?? $defaultValue : $defaultValue;
        }

        // Ensure proper data types
        if (!is_int($newConfig['maxMetaDescriptionLength'])) {
            $newConfig['maxMetaDescriptionLength'] = (int)$newConfig['maxMetaDescriptionLength'];
        }

        if ($newConfig['enableRenderMetadata'] === '1') {
            $newConfig['enableRenderMetadata'] = true;
        }

        if ($newConfig['enableRenderMetadata'] === '') {
            $newConfig['enableRenderMetadata'] = false;
        }

        if ($newConfig['useMetadataVariable'] === '1') {
            $newConfig['useMetadataVariable'] = true;
        }

        if ($newConfig['useMetadataVariable'] === '') {
            $newConfig['useMetadataVariable'] = false;
        }

        Craft::$app->getProjectConfig()->set($moduleSettingsKey, $newConfig,
            'Update Sprout Settings for: ' . $moduleSettingsKey
        );

        Craft::$app->getProjectConfig()->remove(self::OLD_CONFIG_KEY);
    }

    public function safeDown(): bool
    {
        echo self::class . " cannot be reverted.\n";

        return false;
    }
}
