<?php

namespace Cleantalk\Custom\StorageHandler;

use Cleantalk\Common\File;

class StorageHandler implements \Cleantalk\Common\StorageHandler\StorageHandler
{
    const CRON_FILE = CLEANTALK_ROOT . 'data'. DS . 'cron_data.php';

    /**
     * @param string $setting_name
     * @return array|false|int|mixed|string
     */
    public function getSetting($setting_name)
    {
        $is_serialized = $setting_name === 'cleantalk_cron';
        return File::get__variable(self::CRON_FILE, $setting_name, $is_serialized);
    }

    public function saveSetting($setting_name, $setting_value)
    {
        $is_serialize = $setting_name === 'cleantalk_cron';
        $result = File::get__variable(self::CRON_FILE, $setting_name, $is_serialize);
        if ($result === false) {
            File::inject__variable(self::CRON_FILE, $setting_name, $setting_value, $is_serialize);
            return true;
        }
        File::replace__variable(self::CRON_FILE, $setting_name, $setting_value, $is_serialize);
        return true;
    }

    public function deleteSetting($setting_name)
    {
        // TODO: Implement deleteSetting() method.
    }

    public static function getUpdatingFolder()
    {
        // TODO: Implement getUpdatingFolder() method.
    }

    public static function getJsLocation()
    {
        // TODO: Implement getJsLocation() method.
    }
}