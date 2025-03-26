<?php
declare(strict_types=1);

/**
 * MCP タイムサーバー - PHP版
 * MCPに時刻とタイムゾーン変換機能を提供します
 */

namespace Uzulla\MCP\Time\Model;

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