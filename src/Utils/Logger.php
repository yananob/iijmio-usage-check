<?php declare(strict_types=1);

namespace App\Utils;

use Monolog\Level;
use Monolog\Logger as MonologLogger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;

final class Logger
{
    private MonologLogger $logger;

    public function __construct(string $tag)
    {
        $this->logger = new MonologLogger($tag);
        $handler = new StreamHandler('php://stderr', Level::Info);
        
        $formatter = new LineFormatter("[%datetime%][%channel%] %level_name%: %message% %context% %extra%\n", "Y-m-d H:i:s");
        $handler->setFormatter($formatter);
        
        $this->logger->pushHandler($handler);
    }

    public function log(string $message): void
    {
        $this->logger->info($message);
    }

    public function info(string $message, array $context = []): void
    {
        $this->logger->info($message, $context);
    }

    public function warning(string $message, array $context = []): void
    {
        $this->logger->warning($message, $context);
    }

    public function error(string $message, array $context = []): void
    {
        $this->logger->error($message, $context);
    }
}
