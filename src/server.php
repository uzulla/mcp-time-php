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
            $current_time->format('Y-m-d\TH:i:sP'),
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
                $source_time->format('Y-m-d\TH:i:sP'),
                (bool)$source_time->format('I')
            ),
            new TimeResult(
                $target_tz,
                $target_time->format('Y-m-d\TH:i:sP'),
                (bool)$target_time->format('I')
            ),
            $time_diff_str
        );
    }
}

/**
 * MCP Server クラス
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
     * MCPリクエストを処理
     */
    public function handle_request(string $request): string {
        $data = json_decode($request, true);
        $response = [];
        
        if (isset($data['type']) && $data['type'] === 'listTools') {
            $response = [
                'type' => 'listToolsResponse',
                'tools' => $this->list_tools()
            ];
        } elseif (isset($data['type']) && $data['type'] === 'callTool') {
            if (!isset($data['name']) || !isset($data['arguments'])) {
                $response = [
                    'type' => 'callToolError',
                    'error' => 'ツール名または引数がありません'
                ];
            } else {
                try {
                    $content = $this->call_tool($data['name'], $data['arguments']);
                    $response = [
                        'type' => 'callToolResponse',
                        'content' => $content
                    ];
                } catch (Exception $e) {
                    $response = [
                        'type' => 'callToolError',
                        'error' => $e->getMessage()
                    ];
                }
            }
        } else {
            $response = [
                'type' => 'error',
                'error' => '不明なリクエストタイプ'
            ];
        }
        
        return json_encode($response);
    }

    /**
     * サーバーを実行（STDINから読み込み、STDOUTに書き込み）
     */
    public function run(): void {
        // サーバー初期化を送信
        $init = [
            'type' => 'serverInitialization',
            'serverInfo' => [
                'name' => $this->name,
                'version' => '1.0.0',
                'vendor' => 'PHP MCP タイムサーバー'
            ]
        ];
        $this->write_message($init);

        // STDINからメッセージを読み込み
        while ($line = fgets(STDIN)) {
            $line = trim($line);
            if (!empty($line)) {
                $response = $this->handle_request($line);
                $this->write_message($response);
            }
        }
    }

    /**
     * STDOUTにメッセージを書き込み
     */
    private function write_message($message): void {
        if (is_array($message)) {
            $message = json_encode($message);
        }
        $length = strlen($message);
        fwrite(STDOUT, "Content-Length: {$length}\r\n\r\n{$message}");
    }
}

/**
 * メインのserve関数
 */
function serve(?string $local_timezone = null): void {
    $server = new Server("mcp-time", $local_timezone);
    $server->run();
}