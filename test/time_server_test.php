<?php
/**
 * MCP タイムサーバー PHP テスト
 */

require_once __DIR__ . '/../src/server.php';

class TimeServerTest {
    /**
     * get_current_time関数をテスト
     */
    public function test_get_current_time() {
        $time_server = new TimeServer();
        
        // 既知のタイムゾーンでテスト
        $result = $time_server->get_current_time('Europe/London');
        
        // 基本的な検証（モックなしでは正確な時間はテストできない）
        echo "Europe/Londonでget_current_timeをテスト\n";
        echo "タイムゾーン: " . $result->timezone . "\n";
        echo "日時: " . $result->datetime . "\n";
        echo "DST: " . ($result->is_dst ? 'true' : 'false') . "\n\n";
        
        // タイムゾーンが正しいことを検証
        assert($result->timezone === 'Europe/London', 'タイムゾーンはEurope/Londonであるべき');
        
        // 無効なタイムゾーンをテスト
        try {
            $time_server->get_current_time('Invalid/Timezone');
            assert(false, '無効なタイムゾーンに対して例外がスローされるべき');
        } catch (Exception $e) {
            echo "想定通り無効なタイムゾーンに対して例外が発生: " . $e->getMessage() . "\n\n";
            assert(strpos($e->getMessage(), '無効なタイムゾーン') !== false, '例外には無効なタイムゾーンについて言及すべき');
        }
    }
    
    /**
     * convert_time関数をテスト
     */
    public function test_convert_time() {
        $time_server = new TimeServer();
        
        // 基本的な変換をテスト
        $result = $time_server->convert_time('Europe/London', '12:00', 'America/New_York');
        
        echo "Europe/London 12:00からAmerica/New_Yorkへのconvert_timeをテスト\n";
        echo "ソースタイムゾーン: " . $result->source->timezone . " 時間: " . $result->source->datetime . "\n";
        echo "ターゲットタイムゾーン: " . $result->target->timezone . " 時間: " . $result->target->datetime . "\n";
        echo "時差: " . $result->time_difference . "\n\n";
        
        // タイムゾーン名を検証
        assert($result->source->timezone === 'Europe/London', 'ソースタイムゾーンはEurope/Londonであるべき');
        assert($result->target->timezone === 'America/New_York', 'ターゲットタイムゾーンはAmerica/New_Yorkであるべき');
        
        // 固定オフセットでの変換をテスト
        $result = $time_server->convert_time('UTC', '12:00', 'Europe/Warsaw');
        echo "UTC 12:00からEurope/Warsawへのconvert_timeをテスト\n";
        echo "ソースタイムゾーン: " . $result->source->timezone . " 時間: " . $result->source->datetime . "\n";
        echo "ターゲットタイムゾーン: " . $result->target->timezone . " 時間: " . $result->target->datetime . "\n";
        echo "時差: " . $result->time_difference . "\n\n";
        
        // 無効な時間形式をテスト
        try {
            $time_server->convert_time('Europe/London', '25:00', 'Europe/Warsaw');
            assert(false, '無効な時間形式に対して例外がスローされるべき');
        } catch (Exception $e) {
            echo "想定通り無効な時間形式に対して例外が発生: " . $e->getMessage() . "\n\n";
            assert(strpos($e->getMessage(), '無効な時間形式') !== false, '例外には無効な時間形式について言及すべき');
        }
        
        // 無効なタイムゾーンをテスト
        try {
            $time_server->convert_time('Invalid/Timezone', '12:00', 'Europe/Warsaw');
            assert(false, '無効なタイムゾーンに対して例外がスローされるべき');
        } catch (Exception $e) {
            echo "想定通り無効なタイムゾーンに対して例外が発生: " . $e->getMessage() . "\n\n";
            assert(strpos($e->getMessage(), '無効なタイムゾーン') !== false, '例外には無効なタイムゾーンについて言及すべき');
        }
    }
    
    /**
     * 全テストを実行
     */
    public function run_all_tests() {
        echo "TimeServer PHPテストを実行中\n";
        echo "===========================\n\n";
        
        $this->test_get_current_time();
        $this->test_convert_time();
        
        echo "すべてのテストが完了しました\n";
    }
}

// テストを実行
$test_runner = new TimeServerTest();
$test_runner->run_all_tests();