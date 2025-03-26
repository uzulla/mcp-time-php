<?php
declare(strict_types=1);

namespace Uzulla\MCP\Time\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use Uzulla\MCP\Time\Service\TimeService;

class TimeServerTest extends TestCase
{
    /**
     * get_current_time関数をテスト
     * @throws Exception
     */
    public function testGetCurrentTime(): void
    {
        $time_server = new TimeService();

        // 既知のタイムゾーンでテスト
        $result = $time_server->get_current_time('Europe/London');

        // タイムゾーンが正しいことを検証
        $this->assertEquals('Europe/London', $result->timezone, 'タイムゾーンはEurope/Londonであるべき');

        // 日時がISO 8601形式であることを検証
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/', $result->datetime, '日時はISO 8601形式であるべき');

        // is_dstがブール値であることを検証
        $this->assertIsBool($result->is_dst, 'is_dstはブール値であるべき');
    }

    /**
     * get_current_timeで無効なタイムゾーンをテスト
     */
    public function testGetCurrentTimeInvalidTimezone(): void
    {
        $time_server = new TimeService();

        // 無効なタイムゾーンをテスト
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/無効なタイムゾーン/');
        $time_server->get_current_time('Invalid/Timezone');
    }

    /**
     * convert_time関数の基本的な変換をテスト
     * @throws Exception
     */
    public function testConvertTime(): void
    {
        $time_server = new TimeService();

        // 基本的な変換をテスト
        $result = $time_server->convert_time('Europe/London', '12:00', 'America/New_York');

        // タイムゾーン名を検証
        $this->assertEquals('Europe/London', $result->source->timezone, 'ソースタイムゾーンはEurope/Londonであるべき');
        $this->assertEquals('America/New_York', $result->target->timezone, 'ターゲットタイムゾーンはAmerica/New_Yorkであるべき');

        // 時差文字列が存在することを検証
        $this->assertNotEmpty($result->time_difference, '時差文字列が存在すべき');
        $this->assertStringContainsString('h', $result->time_difference, '時差文字列は時間単位を含むべき');

        // ソースとターゲットの日時がISO 8601形式であることを検証
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/', $result->source->datetime, 'ソース日時はISO 8601形式であるべき');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+\-]\d{2}:\d{2}$/', $result->target->datetime, 'ターゲット日時はISO 8601形式であるべき');
    }

    /**
     * 同じ日付での特定のタイムゾーン間の時差を検証
     * @throws Exception
     */
    public function testSpecificTimezoneConversion(): void
    {
        $time_server = new TimeService();

        // 東京とロサンゼルスの変換をテスト
        $result = $time_server->convert_time('Asia/Tokyo', '12:00', 'America/Los_Angeles');

        // 時差が期待通りであることを検証（この場合、-16または-17時間程度の差があるはず）
        $this->assertStringMatchesFormat('%s%dh', $result->time_difference, '時差は符号付き数字+hの形式であるべき');
        $time_diff_value = (float)str_replace('h', '', $result->time_difference);
        $this->assertLessThan(0, $time_diff_value, '東京からロサンゼルスへの変換では時差がマイナスになるべき');
    }

    /**
     * 無効な時間形式をテスト
     */
    public function testInvalidTimeFormat(): void
    {
        $time_server = new TimeService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/無効な時間形式/');
        $time_server->convert_time('Europe/London', '25:00', 'Europe/Warsaw');
    }

    /**
     * 無効なソースタイムゾーンをテスト
     */
    public function testInvalidSourceTimezone(): void
    {
        $time_server = new TimeService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/無効なタイムゾーン/');
        $time_server->convert_time('Invalid/Timezone', '12:00', 'Europe/Warsaw');
    }

    /**
     * 無効なターゲットタイムゾーンをテスト
     */
    public function testInvalidTargetTimezone(): void
    {
        $time_server = new TimeService();

        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/無効なタイムゾーン/');
        $time_server->convert_time('Europe/London', '12:00', 'Invalid/Timezone');
    }
}