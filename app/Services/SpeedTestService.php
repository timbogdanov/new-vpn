<?php

namespace App\Services;

use App\DTO\SpeedTestResultDTO;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;

class SpeedTestService
{
    private int $cacheTtl = 300; // 5 minutes

    public function runTest(): ?SpeedTestResultDTO
    {
        $cacheKey = 'speed_test_result';

        // Return cached result if available
        if (Cache::has($cacheKey)) {
            return Cache::get($cacheKey);
        }

        try {
            // Run speedtest-cli (Python) with JSON output
            $result = Process::timeout(120)->run('speedtest-cli --json');

            if (!$result->successful()) {
                Log::error('SpeedTest: Command failed', [
                    'exitCode' => $result->exitCode(),
                    'error' => $result->errorOutput()
                ]);
                return null;
            }

            $output = $result->output();
            $data = json_decode($output, true);

            if (!$data || !isset($data['download'], $data['upload'], $data['ping'])) {
                Log::error('SpeedTest: Invalid JSON response', ['output' => $output]);
                return null;
            }

            // speedtest-cli returns bits per second, convert to Mbps
            $downloadMbps = $data['download'] / 1_000_000;
            $uploadMbps = $data['upload'] / 1_000_000;
            $pingMs = $data['ping'];

            $dto = new SpeedTestResultDTO(
                downloadMbps: $downloadMbps,
                uploadMbps: $uploadMbps,
                pingMs: $pingMs,
                testedAt: Carbon::now()
            );

            // Cache the result
            Cache::put($cacheKey, $dto, $this->cacheTtl);

            Log::info('SpeedTest: Completed', [
                'download' => $dto->getFormattedDownload(),
                'upload' => $dto->getFormattedUpload(),
                'ping' => $dto->getFormattedPing()
            ]);

            return $dto;

        } catch (\Exception $e) {
            Log::error('SpeedTest: Exception', ['error' => $e->getMessage()]);
            return null;
        }
    }

    public function getCachedResult(): ?SpeedTestResultDTO
    {
        return Cache::get('speed_test_result');
    }

    public function clearCache(): void
    {
        Cache::forget('speed_test_result');
    }
}
