<?php
/**
 * MCP タイムサーバー - PHP版
 * MCPに時刻とタイムゾーン変換機能を提供します
 */

/**
 * TimeTools 列挙型クラス相当
 */
class TimeTools {
    const GET_CURRENT_TIME = "get_current_time";
    const CONVERT_TIME = "convert_time";
}

/**
 * TimeResult クラス
 */
class TimeResult {
    public string $timezone;
    public string $datetime;
    public bool $is_dst;

    public function __construct(string $timezone, string $datetime, bool $is_dst) {
        $this->timezone = $timezone;
        $this->datetime = $datetime;
        $this->is_dst = $is_dst;
    }

    public function toArray(): array {
        return [
            'timezone' => $this->timezone,
            'datetime' => $this->datetime,
            'is_dst' => $this->is_dst
        ];
    }
}

/**
 * TimeConversionResult クラス
 */
class TimeConversionResult {
    public TimeResult $source;
    public TimeResult $target;
    public string $time_difference;

    public function __construct(TimeResult $source, TimeResult $target, string $time_difference) {
        $this->source = $source;
        $this->target = $target;
        $this->time_difference = $time_difference;
    }

    public function toArray(): array {
        return [
            'source' => $this->source->toArray(),
            'target' => $this->target->toArray(),
            'time_difference' => $this->time_difference
        ];
    }
}

/**
 * ローカルタイムゾーンを取得
 */
function get_local_tz(?string $local_tz_override = null): DateTimeZone {
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
function get_zoneinfo(string $timezone_name): DateTimeZone {
    try {
        return new DateTimeZone($timezone_name);
    } catch (Exception $e) {
        throw new Exception("無効なタイムゾーン: " . $e->getMessage());
    }
}

/**
 * TimeServer クラス
 */
class TimeServer {
    /**
     * 指定されたタイムゾーンでの現在時刻を取得
     */
    public function get_current_time(string $timezone_name): TimeResult {
        $timezone = get_zoneinfo($timezone_name);
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
        $source_timezone = get_zoneinfo($source_tz);
        $target_timezone = get_zoneinfo($target_tz);

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

/**
 * MCP Server クラス
 * python-sdk/src/mcp/server/stdio.pyを参考に実装
 */
class Server {
    private string $name;
    private array $tools = [];
    private TimeServer $time_server;
    private string $local_tz;

    public function __construct(string $name, ?string $local_timezone = null) {
        $this->name = $name;
        $this->time_server = new TimeServer();
        $this->local_tz = (string)get_local_tz($local_timezone)->getName();
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

/**
 * メインのserve関数
 */
function serve(?string $local_timezone = null): void {
    $server = new Server("mcp-time", $local_timezone);
    $server->run();
}