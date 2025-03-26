<?php
declare(strict_types=1);

/**
 * MCP タイムサーバー - PHP版
 * MCPに時刻とタイムゾーン変換機能を提供します
 */

namespace Uzulla\MCP\Time\Service;

use DateTime;
use DateTimeZone;
use Exception;

/**
 * TimeUtils ユーティリティクラス
 */
class TimeUtils {
    /**
     * ローカルタイムゾーンを取得
     */
    public static function getLocalTz(?string $local_tz_override = null): DateTimeZone {
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
     */
    public static function getZoneinfo(string $timezone_name): DateTimeZone {
        try {
            return new DateTimeZone($timezone_name);
        } catch (Exception $e) {
            throw new Exception("無効なタイムゾーン: " . $e->getMessage());
        }
    }
}