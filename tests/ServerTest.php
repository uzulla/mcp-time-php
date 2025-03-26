<?php
declare(strict_types=1);

namespace Uzulla\MCP\Time\Tests;

use Exception;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionException;
use Uzulla\MCP\Time\Enum\TimeTools;
use Uzulla\MCP\Time\StdioServer;

class ServerTest extends TestCase
{
    private StdioServer $server;

    protected function setUp(): void
    {
        parent::setUp();
        $this->server = new StdioServer('test-server', 'UTC');
    }

    /**
     * ツール一覧取得をテスト
     */
    public function testListTools(): void
    {
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
     * @throws Exception
     */
    public function testCallToolGetCurrentTime(): void
    {
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
     * @throws Exception
     */
    public function testCallToolConvertTime(): void
    {
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
    public function testCallToolInvalidTool(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/不明なツール/');
        $this->server->call_tool('invalid_tool', []);
    }

    /**
     * 引数がないツール呼び出しをテスト
     */
    public function testCallToolGetCurrentTimeMissingArguments(): void
    {
        $this->expectException(Exception::class);
        $this->expectExceptionMessageMatches('/必須引数がありません/');
        $this->server->call_tool(TimeTools::GET_CURRENT_TIME, []);
    }

    /**
     * JSONRPCメッセージの処理をモックテスト
     * @throws ReflectionException
     */
    public function testProcessMessage(): void
    {
        // process_messageメソッドにアクセスするためのモックサーバー
        $server = $this->getMockBuilder(StdioServer::class)
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
        $reflectionClass = new ReflectionClass(StdioServer::class);
        $method = $reflectionClass->getMethod('process_message');

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
     * @throws ReflectionException
     */
    public function testProcessMessageError(): void
    {
        // process_messageメソッドにアクセスするためのモックサーバー
        $server = $this->getMockBuilder(StdioServer::class)
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
        $reflectionClass = new ReflectionClass(StdioServer::class);
        $method = $reflectionClass->getMethod('process_message');

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