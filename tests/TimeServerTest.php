<?php
/**
 * MCP タイムサーバー PHP テスト - PHPUnit版
 */

require_once __DIR__ . '/../src/server.php';
use PHPUnit\Framework\TestCase;

class TimeServerTest extends TestCase {
    /**
     * get_current_time関数をテスト
     */
    public function testGetCurrentTime(): void {
        $time_server = new TimeServer();
        
        // 既知のタイムゾーンでテスト
        $result = $time_server->get_current_time('Europe/London');
        
        // タイムゾーンが正しいことを検証
        $this->assertEquals('Europe/London', $result->timezone, 'タイムゾーンはEurope/Londonであるべき');
        
        // 無効なタイムゾーンをテスト
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/無効なタイムゾーン/');
        $time_server->get_current_time('Invalid/Timezone');
    }
    
    /**
     * convert_time関数をテスト
     */
    public function testConvertTime(): void {
        $time_server = new TimeServer();
        
        // 基本的な変換をテスト
        $result = $time_server->convert_time('Europe/London', '12:00', 'America/New_York');
        
        // タイムゾーン名を検証
        $this->assertEquals('Europe/London', $result->source->timezone, 'ソースタイムゾーンはEurope/Londonであるべき');
        $this->assertEquals('America/New_York', $result->target->timezone, 'ターゲットタイムゾーンはAmerica/New_Yorkであるべき');
    }
    
    /**
     * 無効な時間形式をテスト
     */
    public function testInvalidTimeFormat(): void {
        $time_server = new TimeServer();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/無効な時間形式/');
        $time_server->convert_time('Europe/London', '25:00', 'Europe/Warsaw');
    }
    
    /**
     * 無効なタイムゾーンをテスト
     */
    public function testInvalidTimezone(): void {
        $time_server = new TimeServer();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/無効なタイムゾーン/');
        $time_server->convert_time('Invalid/Timezone', '12:00', 'Europe/Warsaw');
    }
}