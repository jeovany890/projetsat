<?php

namespace App\Service;

class NativeRateLimiter
{
    private string $storageFile;

    public function __construct(string $projectDir)
    {
        $directory = rtrim($projectDir, '\\/') . '/var';
        if (!is_dir($directory)) {
            mkdir($directory, 0755, true);
        }

        $this->storageFile = $directory . '/rate_limiter.json';
    }

    public function check(string $key, int $limit, int $intervalSeconds): bool
    {
        $entries = $this->loadEntries();
        $now     = time();

        if (!isset($entries[$key]) || $entries[$key]['expires_at'] <= $now) {
            $entries[$key] = [
                'count'      => 0,
                'expires_at' => $now + $intervalSeconds,
            ];
        }

        if ($entries[$key]['count'] >= $limit) {
            $this->saveEntries($entries);
            return false;
        }

        $entries[$key]['count']++;
        $this->saveEntries($entries);

        return true;
    }

    private function loadEntries(): array
    {
        if (!file_exists($this->storageFile)) {
            return [];
        }

        $contents = @file_get_contents($this->storageFile);
        if ($contents === false) {
            return [];
        }

        $entries = @json_decode($contents, true);
        if (!is_array($entries)) {
            return [];
        }

        return $entries;
    }

    private function saveEntries(array $entries): void
    {
        $now = time();
        foreach ($entries as $key => $value) {
            if (!isset($value['expires_at']) || $value['expires_at'] <= $now) {
                unset($entries[$key]);
            }
        }

        @file_put_contents($this->storageFile, json_encode($entries, JSON_PRETTY_PRINT), LOCK_EX);
    }
}
