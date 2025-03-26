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
use Uzulla\MCP\Time\Model\TimeResult;
use Uzulla\MCP\Time\Model\TimeConversionResult;

/**
 * TimeServer クラス
 */
class TimeService {
    /**
     * 指定されたタイムゾーンでの現在時刻を取得
     */
    public function get_current_time(string $timezone_name): TimeResult {
        $timezone = TimeUtils::getZoneinfo($timezone_name);
        $current_time = new DateTime('now', $timezone);
        
        return new TimeResult(
            $timezone_name,
            $current_time->format(DATE_ATOM),
            (bool)$current_time->format('I') // 'I'はDSTなら1、そうでなければ0を返す
        );
    }

    /**
     * タイムゾーン間の時刻変換
     */
    public function convert_time(string $source_tz, string $time_str, string $target_tz): TimeConversionResult {
        $source_timezone = TimeUtils::getZoneinfo($source_tz);
        $target_timezone = TimeUtils::getZoneinfo($target_tz);

        // 時間をパース（HH:MM形式）
        if (!preg_match('/^([0-1][0-9]|2[0-3]):([0-5][0-9])$/', $time_str)) {
            throw new Exception("無効な時間形式。HH:MM [24時間形式]が必要です");
        }

        list($hours, $minutes) = explode(':', $time_str);
        
        // 今日の日付でソース時間を作成
        $now = new DateTime('now', $source_timezone);
        $source_time = new DateTime(
            $now->format('Y-m-d') . ' ' . $hours . ':' . $minutes . ':00',
            $source_timezone
        );

        // ターゲットタイムゾーンに変換
        $target_time = clone $source_time;
        $target_time->setTimezone($target_timezone);

        // 時差を計算
        $source_offset = $source_time->getOffset();
        $target_offset = $target_time->getOffset();
        $hours_difference = ($target_offset - $source_offset) / 3600;

        // 時差文字列をフォーマット
        if ($hours_difference == (int)$hours_difference) {
            $time_diff_str = sprintf("%+.1f", $hours_difference) . "h";
        } else {
            // 小数時間の場合（ネパールのUTC+5:45など）
            $time_diff_str = sprintf("%+.2f", $hours_difference);
            $time_diff_str = rtrim(rtrim($time_diff_str, '0'), '.') . "h";
        }

        return new TimeConversionResult(
            new TimeResult(
                $source_tz,
                $source_time->format(DATE_ATOM),
                (bool)$source_time->format('I')
            ),
            new TimeResult(
                $target_tz,
                $target_time->format(DATE_ATOM),
                (bool)$target_time->format('I')
            ),
            $time_diff_str
        );
    }
}