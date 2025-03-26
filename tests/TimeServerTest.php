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
        
        // 日時がISO 8601形式であることを検証
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[\+\-]\d{2}:\d{2}$/', $result->datetime, '日時はISO 8601形式であるべき');
        
        // is_dstがブール値であることを検証
        $this->assertIsBool($result->is_dst, 'is_dstはブール値であるべき');
    }
    
    /**
     * get_current_timeで無効なタイムゾーンをテスト
     */
    public function testGetCurrentTimeInvalidTimezone(): void {
        $time_server = new TimeServer();
        
        // 無効なタイムゾーンをテスト
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/無効なタイムゾーン/');
        $time_server->get_current_time('Invalid/Timezone');
    }
    
    /**
     * convert_time関数の基本的な変換をテスト
     */
    public function testConvertTime(): void {
        $time_server = new TimeServer();
        
        // 基本的な変換をテスト
        $result = $time_server->convert_time('Europe/London', '12:00', 'America/New_York');
        
        // タイムゾーン名を検証
        $this->assertEquals('Europe/London', $result->source->timezone, 'ソースタイムゾーンはEurope/Londonであるべき');
        $this->assertEquals('America/New_York', $result->target->timezone, 'ターゲットタイムゾーンはAmerica/New_Yorkであるべき');
        
        // 時差文字列が存在することを検証
        $this->assertNotEmpty($result->time_difference, '時差文字列が存在すべき');
        $this->assertStringContainsString('h', $result->time_difference, '時差文字列は時間単位を含むべき');
        
        // ソースとターゲットの日時がISO 8601形式であることを検証
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[\+\-]\d{2}:\d{2}$/', $result->source->datetime, 'ソース日時はISO 8601形式であるべき');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[\+\-]\d{2}:\d{2}$/', $result->target->datetime, 'ターゲット日時はISO 8601形式であるべき');
    }
    
    /**
     * 同じ日付での特定のタイムゾーン間の時差を検証
     */
    public function testSpecificTimezoneConversion(): void {
        $time_server = new TimeServer();
        
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
    public function testInvalidTimeFormat(): void {
        $time_server = new TimeServer();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/無効な時間形式/');
        $time_server->convert_time('Europe/London', '25:00', 'Europe/Warsaw');
    }
    
    /**
     * 無効なソースタイムゾーンをテスト
     */
    public function testInvalidSourceTimezone(): void {
        $time_server = new TimeServer();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/無効なタイムゾーン/');
        $time_server->convert_time('Invalid/Timezone', '12:00', 'Europe/Warsaw');
    }
    
    /**
     * 無効なターゲットタイムゾーンをテスト
     */
    public function testInvalidTargetTimezone(): void {
        $time_server = new TimeServer();
        
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/無効なタイムゾーン/');
        $time_server->convert_time('Europe/London', '12:00', 'Invalid/Timezone');
    }
}

/**
 * MCP Serverクラスのテスト
 */
class ServerTest extends TestCase {
    private $server;
    
    /**
     * テスト前の準備
     */
    protected function setUp(): void {
        $this->server = new Server('test-server', 'UTC');
    }
    
    /**
     * ツール一覧取得をテスト
     */
    public function testListTools(): void {
        $tools = $this->server->list_tools();
        
        // ツールが配列であることを検証
        $this->assertIsArray($tools, 'ツール一覧は配列であるべき');
        
        // 少なくとも2つのツールが存在することを検証
        $this->assertGreaterThanOrEqual(2, count($tools), '少なくとも2つのツールが存在すべき');
        
        // get_current_timeツールが存在することを検証
        $found_get_current_time = false;
        $found_convert_time = false;
        
        foreach ($tools as $tool) {
            if ($tool['name'] === TimeTools::GET_CURRENT_TIME) {
                $found_get_current_time = true;
                
                // スキーマをチェック
                $this->assertArrayHasKey('inputSchema', $tool, 'get_current_timeツールはinputSchemaを持つべき');
                $this->assertArrayHasKey('properties', $tool['inputSchema'], 'inputSchemaはpropertiesを持つべき');
                $this->assertArrayHasKey('timezone', $tool['inputSchema']['properties'], 'propertiesはtimezoneを持つべき');
            }
            
            if ($tool['name'] === TimeTools::CONVERT_TIME) {
                $found_convert_time = true;
                
                // スキーマをチェック
                $this->assertArrayHasKey('inputSchema', $tool, 'convert_timeツールはinputSchemaを持つべき');
                $this->assertArrayHasKey('properties', $tool['inputSchema'], 'inputSchemaはpropertiesを持つべき');
                $this->assertArrayHasKey('source_timezone', $tool['inputSchema']['properties'], 'propertiesはsource_timezoneを持つべき');
                $this->assertArrayHasKey('time', $tool['inputSchema']['properties'], 'propertiesはtimeを持つべき');
                $this->assertArrayHasKey('target_timezone', $tool['inputSchema']['properties'], 'propertiesはtarget_timezoneを持つべき');
            }
        }
        
        $this->assertTrue($found_get_current_time, 'get_current_timeツールが存在すべき');
        $this->assertTrue($found_convert_time, 'convert_timeツールが存在すべき');
    }
    
    /**
     * get_current_timeツール呼び出しをテスト
     */
    public function testCallToolGetCurrentTime(): void {
        $result = $this->server->call_tool(
            TimeTools::GET_CURRENT_TIME,
            ['timezone' => 'Europe/London']
        );
        
        // 結果が配列であることを検証
        $this->assertIsArray($result, '結果は配列であるべき');
        $this->assertArrayHasKey(0, $result, '結果は少なくとも1つの要素を持つべき');
        $this->assertArrayHasKey('type', $result[0], '結果の要素はtypeを持つべき');
        $this->assertEquals('text', $result[0]['type'], 'typeはtextであるべき');
        $this->assertArrayHasKey('text', $result[0], '結果の要素はtextを持つべき');
        
        // JSONをデコードして内容を検証
        $content = json_decode($result[0]['text'], true);
        $this->assertIsArray($content, 'コンテンツはJSON配列としてデコード可能であるべき');
        $this->assertArrayHasKey('timezone', $content, 'コンテンツはtimezoneを持つべき');
        $this->assertEquals('Europe/London', $content['timezone'], 'timezoneはEurope/Londonであるべき');
    }
    
    /**
     * convert_timeツール呼び出しをテスト
     */
    public function testCallToolConvertTime(): void {
        $result = $this->server->call_tool(
            TimeTools::CONVERT_TIME,
            [
                'source_timezone' => 'Europe/London',
                'time' => '12:00',
                'target_timezone' => 'America/New_York'
            ]
        );
        
        // 結果が配列であることを検証
        $this->assertIsArray($result, '結果は配列であるべき');
        $this->assertArrayHasKey(0, $result, '結果は少なくとも1つの要素を持つべき');
        $this->assertArrayHasKey('type', $result[0], '結果の要素はtypeを持つべき');
        $this->assertEquals('text', $result[0]['type'], 'typeはtextであるべき');
        $this->assertArrayHasKey('text', $result[0], '結果の要素はtextを持つべき');
        
        // JSONをデコードして内容を検証
        $content = json_decode($result[0]['text'], true);
        $this->assertIsArray($content, 'コンテンツはJSON配列としてデコード可能であるべき');
        $this->assertArrayHasKey('source', $content, 'コンテンツはsourceを持つべき');
        $this->assertArrayHasKey('target', $content, 'コンテンツはtargetを持つべき');
        $this->assertArrayHasKey('time_difference', $content, 'コンテンツはtime_differenceを持つべき');
        $this->assertEquals('Europe/London', $content['source']['timezone'], 'source timezoneはEurope/Londonであるべき');
        $this->assertEquals('America/New_York', $content['target']['timezone'], 'target timezoneはAmerica/New_Yorkであるべき');
    }
    
    /**
     * 存在しないツール呼び出しをテスト
     */
    public function testCallToolInvalidTool(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/不明なツール/');
        $this->server->call_tool('invalid_tool', []);
    }
    
    /**
     * 引数がないツール呼び出しをテスト
     */
    public function testCallToolGetCurrentTimeMissingArguments(): void {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/必須引数がありません/');
        $this->server->call_tool(TimeTools::GET_CURRENT_TIME, []);
    }
    
    /**
     * JSONRPCメッセージの処理をモックテスト
     */
    public function testProcessMessage(): void {
        // process_messageメソッドにアクセスするためのモックサーバー
        $server = $this->getMockBuilder(Server::class)
            ->setConstructorArgs(['test-server', 'UTC'])
            ->onlyMethods(['send_response', 'send_error_response'])
            ->getMock();
        
        // send_responseが呼ばれることを期待
        $server->expects($this->once())
            ->method('send_response')
            ->with(
                $this->equalTo(1),
                $this->arrayHasKey('tools')
            );
        
        // リフレクションを使用してprivateメソッドにアクセス
        $reflectionClass = new ReflectionClass(Server::class);
        $method = $reflectionClass->getMethod('process_message');
        $method->setAccessible(true);
        
        // ツール一覧リクエストを処理
        $request = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'tools/list',
            'params' => []
        ];
        
        $method->invoke($server, $request);
    }
    
    /**
     * エラー処理をモックテスト
     */
    public function testProcessMessageError(): void {
        // process_messageメソッドにアクセスするためのモックサーバー
        $server = $this->getMockBuilder(Server::class)
            ->setConstructorArgs(['test-server', 'UTC'])
            ->onlyMethods(['send_response', 'send_error_response'])
            ->getMock();
        
        // send_error_responseが呼ばれることを期待
        $server->expects($this->once())
            ->method('send_error_response')
            ->with(
                $this->equalTo(1),
                $this->equalTo(-32601),
                $this->stringContains('メソッドが見つかりません')
            );
        
        // リフレクションを使用してprivateメソッドにアクセス
        $reflectionClass = new ReflectionClass(Server::class);
        $method = $reflectionClass->getMethod('process_message');
        $method->setAccessible(true);
        
        // 不明なメソッドリクエストを処理
        $request = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => 'unknown_method',
            'params' => []
        ];
        
        $method->invoke($server, $request);
    }
}