<?php
declare(strict_types=1);

namespace Uzulla\MCP\Time\Service;

use DateInvalidTimeZoneException;
use DateTimeZone;
use Exception;

class TimeUtils
{
    /**
     * ローカルタイムゾーンを取得
     * @throws DateInvalidTimeZoneException
     * @throws Exception
     */
    public static function getLocalTz(?string $local_tz_override = null): DateTimeZone
    {
        if ($local_tz_override) {
            try {
                return new DateTimeZone($local_tz_override);
            } catch (Exception $e) {
                throw new Exception("無効なタイムゾーンオーバーライド: " . $e->getMessage());
            }
        }

        // ローカルタイムゾーンを取得
        $local_tz = date_default_timezone_get();
        return new DateTimeZone($local_tz);
    }

    /**
     * タイムゾーンオブジェクトを取得
     * @throws Exception
     */
    public static function getZoneinfo(string $timezone_name): DateTimeZone
    {
        try {
            return new DateTimeZone($timezone_name);
        } catch (Exception $e) {
            throw new Exception("無効なタイムゾーン: " . $e->getMessage());
        }
    }
}