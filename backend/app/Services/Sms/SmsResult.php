<?php

namespace App\Services\Sms;

class SmsResult
{
    public bool $success;
    public string $driver;
    public ?string $messageId;
    public ?string $error;
    public ?int $status;

    public function __construct(bool $success, string $driver, ?string $messageId = null, ?string $error = null, ?int $status = null)
    {
        $this->success = $success;
        $this->driver = $driver;
        $this->messageId = $messageId;
        $this->error = $error;
        $this->status = $status;
    }

    public function toArray(): array
    {
        return array_filter([
            'success' => $this->success,
            'driver' => $this->driver,
            'message_id' => $this->messageId,
            'message' => $this->error,
        ], fn ($v) => $v !== null);
    }
}
