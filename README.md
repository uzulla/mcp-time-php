# タイム MCP サーバー - PHP版

PHPで実装されたモデルコンテキストプロトコルサーバーで、時刻とタイムゾーン変換機能を提供します。このサーバーは、LLMがIANAタイムゾーン名を使用して現在の時刻情報を取得し、タイムゾーン変換を実行できるようにし、システムタイムゾーンを自動検出します。

### 利用可能なツール

- `get_current_time` - 特定のタイムゾーンまたはシステムタイムゾーンでの現在時刻を取得します。
  - 必須引数:
    - `timezone` (文字列): IANAタイムゾーン名（例: 'America/New_York'、'Europe/London'）

- `convert_time` - タイムゾーン間で時刻を変換します。
  - 必須引数:
    - `source_timezone` (文字列): 元のIANAタイムゾーン名
    - `time` (文字列): 24時間形式（HH:MM）の時間
    - `target_timezone` (文字列): 変換先のIANAタイムゾーン名

## インストール

### Dockerを使用

このサーバーを使用する最も簡単な方法はDockerを使用することです：

```bash
docker build -t mcp/time-php .
docker run -i --rm mcp/time-php
```

### PHPで直接実行

または、PHPで直接実行することもできます：

```bash
php src/index.php
```

## 設定

### Claude.appの設定

Claude設定に追加：

<details>
<summary>PHPを使用</summary>

```json
"mcpServers": {
  "time": {
    "command": "php",
    "args": ["src/index.php"]
  }
}
```
</details>

<details>
<summary>Dockerを使用</summary>

```json
"mcpServers": {
  "time": {
    "command": "docker",
    "args": ["run", "-i", "--rm", "mcp/time-php"]
  }
}
```
</details>

### Zedの設定

Zedのsettings.jsonに追加：

<details>
<summary>PHPを使用</summary>

```json
"context_servers": {
  "mcp-server-time": {
    "command": "php",
    "args": ["src/index.php"]
  }
},
```
</details>

### カスタマイズ - システムタイムゾーン

デフォルトでは、サーバーはシステムのタイムゾーンを自動的に検出します。設定の`args`リストに引数`--local-timezone`を追加することで、これをオーバーライドできます。

例：
```json
{
  "command": "php",
  "args": ["src/index.php", "--local-timezone=America/New_York"]
}
```

## 対話例

1. 現在時刻の取得：
```json
{
  "name": "get_current_time",
  "arguments": {
    "timezone": "Europe/Warsaw"
  }
}
```
レスポンス：
```json
{
  "timezone": "Europe/Warsaw",
  "datetime": "2024-01-01T13:00:00+01:00",
  "is_dst": false
}
```

2. タイムゾーン間の時間変換：
```json
{
  "name": "convert_time",
  "arguments": {
    "source_timezone": "America/New_York",
    "time": "16:30",
    "target_timezone": "Asia/Tokyo"
  }
}
```
レスポンス：
```json
{
  "source": {
    "timezone": "America/New_York",
    "datetime": "2024-01-01T16:30:00-05:00",
    "is_dst": false
  },
  "target": {
    "timezone": "Asia/Tokyo",
    "datetime": "2024-01-02T06:30:00+09:00",
    "is_dst": false
  },
  "time_difference": "+14.0h"
}
```

## デバッグ

MCPインスペクターを使用してサーバーをデバッグできます：

```bash
npx @modelcontextprotocol/inspector php src/index.php
```

## テスト

付属のテストスクリプトを実行します：

```bash
php test/time_server_test.php
```

## Claudeへの質問例

1. 「今何時ですか？」（システムタイムゾーンを使用）
2. 「東京の時間は？」
3. 「ニューヨークで午後4時の時、ロンドンでは何時ですか？」
4. 「東京時間の午前9時30分をニューヨーク時間に変換して」

## ライセンス

タイムMCPサーバーのPHP実装はMITライセンスの下でライセンスされています。