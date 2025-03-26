<?php
declare(strict_types=1);

/**
 * MCP タイムサーバー - PHP版
 * MCPに時刻とタイムゾーン変換機能を提供します
 */

namespace Uzulla\MCP\Time;

use Exception;
use Uzulla\MCP\Time\Enum\TimeTools;
use Uzulla\MCP\Time\Service\TimeService;
use Uzulla\MCP\Time\Model\TimeResult;
use Uzulla\MCP\Time\Service\TimeUtils;

/**
 * MCP StdioServer クラス
 * python-sdk/src/mcp/server/stdio.pyを参考に実装
 */
class StdioServer {
    private string $name;
    private array $tools = [];
    private TimeService $time_server;
    private string $local_tz;

    public function __construct(string $name, ?string $local_timezone = null) {
        $this->name = $name;
        $this->time_server = new TimeService();
        $this->local_tz = (string)TimeUtils::getLocalTz($local_timezone)->getName();
        $this->register_tools();
    }

    /**
     * 利用可能なツールを登録
     */
    private function register_tools(): void {
        $this->tools = [
            [
                'name' => TimeTools::GET_CURRENT_TIME,
                'description' => '特定のタイムゾーンでの現在時刻を取得',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'timezone' => [
                            'type' => 'string',
                            'description' => "IANAタイムゾーン名（例：'America/New_York'、'Europe/London'）。ユーザーがタイムゾーンを提供しない場合は'{$this->local_tz}'をローカルタイムゾーンとして使用してください。"
                        ]
                    ],
                    'required' => ['timezone']
                ]
            ],
            [
                'name' => TimeTools::CONVERT_TIME,
                'description' => 'タイムゾーン間で時刻を変換',
                'inputSchema' => [
                    'type' => 'object',
                    'properties' => [
                        'source_timezone' => [
                            'type' => 'string',
                            'description' => "ソースIANAタイムゾーン名（例：'America/New_York'、'Europe/London'）。ユーザーがソースタイムゾーンを提供しない場合は'{$this->local_tz}'をローカルタイムゾーンとして使用してください。"
                        ],
                        'time' => [
                            'type' => 'string',
                            'description' => '24時間形式（HH:MM）で変換する時間'
                        ],
                        'target_timezone' => [
                            'type' => 'string',
                            'description' => "ターゲットIANAタイムゾーン名（例：'Asia/Tokyo'、'America/San_Francisco'）。ユーザーがターゲットタイムゾーンを提供しない場合は'{$this->local_tz}'をローカルタイムゾーンとして使用してください。"
                        ]
                    ],
                    'required' => ['source_timezone', 'time', 'target_timezone']
                ]
            ]
        ];
    }

    /**
     * 利用可能なツールをリスト
     */
    public function list_tools(): array {
        return $this->tools;
    }

    /**
     * 引数付きでツールを呼び出す
     */
    public function call_tool(string $name, array $arguments): array {
        try {
            $result = null;
            
            switch ($name) {
                case TimeTools::GET_CURRENT_TIME:
                    if (!isset($arguments['timezone'])) {
                        throw new Exception("必須引数がありません: timezone");
                    }
                    $result = $this->time_server->get_current_time($arguments['timezone']);
                    break;
                    
                case TimeTools::CONVERT_TIME:
                    if (!isset($arguments['source_timezone']) || !isset($arguments['time']) || !isset($arguments['target_timezone'])) {
                        throw new Exception("必須引数がありません");
                    }
                    $result = $this->time_server->convert_time(
                        $arguments['source_timezone'],
                        $arguments['time'],
                        $arguments['target_timezone']
                    );
                    break;
                    
                default:
                    throw new Exception("不明なツール: {$name}");
            }
            
            return [
                [
                    'type' => 'text',
                    'text' => json_encode($result instanceof TimeResult ? $result->toArray() : $result->toArray(), JSON_PRETTY_PRINT)
                ]
            ];
        } catch (Exception $e) {
            throw new Exception("mcp-server-timeクエリの処理中にエラーが発生しました: " . $e->getMessage());
        }
    }

    /**
     * サーバーを実行（STDINから読み込み、STDOUTに書き込み）
     * python-sdk/src/mcp/server/stdio.pyを参考に実装
     */
    public function run(): void {
        error_log("MCP Time Server starting up...");
        
        // STDINのバイナリモードを確保し、UTF-8エンコーディングを設定
        stream_set_blocking(STDIN, false);  // ノンブロッキングに設定
        
        while (true) {
            // 標準入力から行を読み込む
            $line = fgets(STDIN);
            
            // 入力がない場合は少し待ってから再試行
            if ($line === false) {
                usleep(10000);  // 10ミリ秒待機
                continue;
            }
            
            $line = trim($line);
            if (empty($line)) {
                continue;
            }
            
            error_log("Received message: " . substr($line, 0, 100) . (strlen($line) > 100 ? '...' : ''));
            
            try {
                // JSONRPCリクエストとしてパース
                $request = json_decode($line, true);
                if ($request === null) {
                    $this->send_error_response(null, -32700, '無効なJSONフォーマット');
                    continue;
                }
                
                // JSONRPCメッセージを処理
                $this->process_message($request);
                
            } catch (Exception $e) {
                error_log("Error processing request: " . $e->getMessage());
                $this->send_error_response(
                    isset($request['id']) ? $request['id'] : null,
                    -32603,
                    '内部エラー: ' . $e->getMessage()
                );
            }
        }
    }
    
    /**
     * JSONRPCメッセージを処理
     */
    private function process_message(array $message): void {
        $id = isset($message['id']) ? $message['id'] : null;
        
        // IDがnullかつメソッドがあれば通知、そうでなければリクエスト
        if (isset($message['method'])) {
            $method = $message['method'];
            $params = isset($message['params']) ? $message['params'] : [];
            
            error_log("Processing method: $method with id: " . (is_null($id) ? "null" : $id));
            
            if ($method === 'initialize') {
                // 初期化リクエスト
                $this->send_response($id, [
                    'protocolVersion' => '2024-11-05',
                    'capabilities' => [
                        'tools' => [
                            'listChanged' => false
                        ]
                    ],
                    'serverInfo' => [
                        'name' => $this->name,
                        'version' => '1.0.0'
                    ]
                ]);
            } else if ($method === 'shutdown') {
                // シャットダウンリクエスト
                $this->send_response($id, null);
                exit(0);
            } else if ($method === 'tools/list') {
                // ツール一覧リクエスト
                $this->send_response($id, [
                    'tools' => $this->list_tools()
                ]);
            } else if ($method === 'tools/call') {
                // ツール呼び出しリクエスト
                if (!isset($params['name']) || !isset($params['arguments'])) {
                    $this->send_error_response($id, -32602, 'ツール名または引数がありません');
                    return;
                }
                
                try {
                    $name = $params['name'];
                    $arguments = $params['arguments'];
                    
                    error_log("Calling tool: $name");
                    
                    $content = $this->call_tool($name, $arguments);
                    $this->send_response($id, [
                        'content' => $content,
                        'isError' => false
                    ]);
                } catch (Exception $e) {
                    error_log("Tool error: " . $e->getMessage());
                    $this->send_error_response($id, -32000, $e->getMessage());
                }
            } else if ($method === 'ping') {
                // pingリクエスト
                $this->send_response($id, null);
            } else {
                // 不明なメソッド
                $this->send_error_response($id, -32601, 'メソッドが見つかりません: ' . $method);
            }
        } else {
            // メソッドがないリクエストは無効
            $this->send_error_response($id, -32600, '無効なリクエスト');
        }
    }
    
    /**
     * エラーレスポンスを送信
     */
    private function send_error_response($id, int $code, string $message): void {
        $response = [
            'jsonrpc' => '2.0',
            'error' => [
                'code' => $code,
                'message' => $message
            ],
            'id' => $id
        ];
        
        $this->write_message($response);
    }
    
    /**
     * 成功レスポンスを送信
     */
    private function send_response($id, $result): void {
        $response = [
            'jsonrpc' => '2.0',
            'result' => $result,
            'id' => $id
        ];
        
        $this->write_message($response);
    }

    /**
     * メッセージをSTDOUTに書き込み
     */
    private function write_message($message): void {
        $json = json_encode($message, JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            error_log("Failed to encode message to JSON");
            return;
        }
        
        fwrite(STDOUT, $json . "\n");
        fflush(STDOUT);
        error_log("Sent response: " . substr($json, 0, 100) . (strlen($json) > 100 ? '...' : ''));
    }
}
