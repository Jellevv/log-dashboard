<?php

namespace App\DTO;

class LogEntry
{
    public string $timestamp;
    public string $level;
    public ?string $component;
    public string $message;
    public ?string $stackTrace;
    public ?string $codeLocation;
    public ?string $requestUrl;
    public ?int $memory;

    public function __construct(array $data)
    {
        $this->timestamp = $data['timestamp'] ?? '';
        $this->level = $data['level'] ?? '';
        $this->component = $data['component'] ?? null;
        $this->message = $data['message'] ?? '';
        $this->stackTrace = $data['stackTrace'] ?? null;
        $this->codeLocation = $data['codeLocation'] ?? null;
        $this->requestUrl = $data['requestUrl'] ?? null;
        $this->memory = $data['memory'] ?? null;
    }
}
