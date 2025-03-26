<?php
declare(strict_types=1);

namespace Uzulla\MCP\Time\Model;

class TimeResult
{
    public string $timezone;
    public string $datetime;
    public bool $is_dst;

    public function __construct(string $timezone, string $datetime, bool $is_dst)
    {
        $this->timezone = $timezone;
        $this->datetime = $datetime;
        $this->is_dst = $is_dst;
    }

    public function toArray(): array
    {
        return [
            'timezone' => $this->timezone,
            'datetime' => $this->datetime,
            'is_dst' => $this->is_dst
        ];
    }
}