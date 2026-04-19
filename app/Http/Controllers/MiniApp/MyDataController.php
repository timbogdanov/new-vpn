<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Services\MyDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MyDataController extends Controller
{
    public function index(Request $request, MyDataService $service): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }

        $data = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'perPage' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $dto = $service->summary(
            telegramId: (int) $user->telegram_id,
            page: (int) ($data['page'] ?? 1),
            perPage: (int) ($data['perPage'] ?? 30),
        );

        return response()->json($dto->toArray());
    }

    public function destroy(Request $request, MyDataService $service): JsonResponse
    {
        $user = $request->user();
        if (!$user) {
            return response()->json(['error' => 'unauthenticated'], 401);
        }
        if (!$request->boolean('confirm')) {
            return response()->json([
                'error' => 'confirmation_required',
                'message' => 'Pass ?confirm=1 to tombstone all contributed signals',
            ], 422);
        }

        $count = $service->softPurge((int) $user->telegram_id);
        Log::warning('MyData: soft-purge', [
            'telegram_id' => $user->telegram_id,
            'tombstoned' => $count,
        ]);

        return response()->json(['tombstoned' => $count]);
    }

    public function export(Request $request, MyDataService $service): StreamedResponse
    {
        $user = $request->user();
        if (!$user) {
            abort(401);
        }

        $filename = 'larastory-ooni-data-' . $user->telegram_id . '.json';
        $telegramId = (int) $user->telegram_id;

        return response()->streamDownload(function () use ($service, $telegramId) {
            echo "[\n";
            $first = true;
            foreach ($service->export($telegramId) as $row) {
                echo ($first ? '' : ",\n");
                echo json_encode($row, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
                $first = false;
            }
            echo "\n]\n";
        }, $filename, [
            'Content-Type' => 'application/json',
        ]);
    }
}
