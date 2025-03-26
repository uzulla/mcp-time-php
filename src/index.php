<?php
/**
 * MCP タイムサーバー - PHP版
 * MCP タイムサーバーのエントリーポイント
 */

require_once __DIR__ . '/server.php';

/**
 * メイン関数
 */
function main() {
    // コマンドライン引数を解析
    $options = getopt('', ['local-timezone::']);
    $local_timezone = $options['local-timezone'] ?? null;
    
    serve($local_timezone);
}

// サーバーを実行
main();