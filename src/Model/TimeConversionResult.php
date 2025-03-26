<?php
declare(strict_types=1);

namespace Uzulla\MCP\Time\Model;

class TimeConversionResult
{
    public TimeResult $source;
    public TimeResult $target;
    public string $time_difference;

    public function __construct(TimeResult $source, TimeResult $target, string $time_difference)
    {
        $this->source = $source;
        $this->target = $target;
        $this->time_difference = $time_difference;
    }

    public function toArray(): array
    {
        return [
            'source' => $this->source->toArray(),
            'target' => $this->target->toArray(),
            'time_difference' => $this->time_difference
        ];
    }
}