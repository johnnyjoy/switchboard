<?php

declare(strict_types=1);

namespace Switchboard\Runtime;

final class RegistryStore
{
    public static function emptyConfig(): array
    {
        return [
            'apps' => [],
            'endpoints' => [],
            'endpoint_predicates' => [],
            'endpoint_validations' => [],
        ];
    }

    public static function backupPath(string $configPath): string
    {
        return $configPath . '.bak';
    }

    public static function load(string $configPath): array
    {
        if (!is_file($configPath) || !is_readable($configPath)) {
            return self::emptyConfig();
        }

        $raw = file_get_contents($configPath);
        if ($raw === false) {
            return self::emptyConfig();
        }

        $data = json_decode($raw, true, 512, JSON_THROW_ON_ERROR);
        return self::normalizeConfig(is_array($data) ? $data : []);
    }

    public static function save(string $configPath, array $data): void
    {
        $dir = dirname($configPath);
        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            throw new \RuntimeException("Failed to create registry directory: {$dir}");
        }

        $normalized = self::normalizeConfig($data);
        $json = json_encode($normalized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $lockPath = $configPath . '.lock';
        $lock = fopen($lockPath, 'c');
        if ($lock === false) {
            throw new \RuntimeException("Failed to open registry lock: {$lockPath}");
        }

        try {
            if (!flock($lock, LOCK_EX)) {
                throw new \RuntimeException("Failed to lock registry: {$lockPath}");
            }

            $tmpPath = tempnam($dir, basename($configPath) . '.tmp.');
            if ($tmpPath === false) {
                throw new \RuntimeException("Failed to create registry temp file in: {$dir}");
            }

            try {
                if (file_put_contents($tmpPath, $json) === false) {
                    throw new \RuntimeException("Failed to write registry temp file: {$tmpPath}");
                }

                // Prove the temp file is readable JSON before replacing the active registry.
                self::load($tmpPath);

                if (is_file($configPath)) {
                    if (!copy($configPath, self::backupPath($configPath))) {
                        throw new \RuntimeException("Failed to backup registry: {$configPath}");
                    }
                }

                if (!rename($tmpPath, $configPath)) {
                    throw new \RuntimeException("Failed to commit registry: {$configPath}");
                }
            } finally {
                if (isset($tmpPath) && is_file($tmpPath)) {
                    unlink($tmpPath);
                }
            }
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    public static function normalizeConfig(array $data): array
    {
        $data['apps'] = $data['apps'] ?? [];
        $data['endpoints'] = $data['endpoints'] ?? [];
        $data['endpoint_predicates'] = $data['endpoint_predicates'] ?? [];
        $data['endpoint_validations'] = $data['endpoint_validations'] ?? [];
        return $data;
    }
}
