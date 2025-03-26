<?php
declare(strict_types=1);

/**
 * MCP タイムサーバー - PHP版
 * MCPに時刻とタイムゾーン変換機能を提供します
 */

namespace Uzulla\MCP\Time\Enum;

/**
 * TimeTools 列挙型クラス相当
 */
class TimeTools {
    const GET_CURRENT_TIME = "get_current_time";
    const CONVERT_TIME = "convert_time";
}