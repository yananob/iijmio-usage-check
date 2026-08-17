<?php declare(strict_types=1);

namespace App\Services;

interface ConfigServiceInterface
{
    /**
     * @return array<string, mixed>
     */
    public function getConfig(): array;

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function parseConfigFromParams(array $params): array;

    /**
     * @param array<string, mixed> $configData
     * @return string
     */
    public function saveConfig(array $configData): string;

    /**
     * @param array<string, mixed> $configData
     * @return string
     */
    public function generatePreview(array $configData): string;
}
