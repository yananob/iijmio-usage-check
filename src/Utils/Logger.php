<?php declare(strict_types=1);

namespace MyApp\Utils;

final class Logger
{
    public function __construct(private string $tag)
    {
    }

    public function log(string $message): void
    {
        $now = date('Y-m-d H:i:s');
        echo "[{$now}][{$this->tag}] {$message}" . PHP_EOL;
    }
}
