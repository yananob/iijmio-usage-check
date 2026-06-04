<?php declare(strict_types=1);

namespace MyApp\Utils;

final class Utils
{
    public static function getConfig(string $path, bool $asArray = true): mixed
    {
        if (!file_exists($path)) {
            throw new \RuntimeException("Config file not found: {$path}");
        }

        $content = file_get_contents($path);
        if ($content === false) {
            throw new \RuntimeException("Failed to read config file: {$path}");
        }

        $config = json_decode($content, $asArray);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to decode config file: " . json_last_error_msg());
        }

        return $config;
    }
}
