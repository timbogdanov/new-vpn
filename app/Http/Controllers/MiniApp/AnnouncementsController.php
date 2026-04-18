<?php

namespace App\Http\Controllers\MiniApp;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\TelegramUser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class AnnouncementsController extends Controller
{
    public function active(Request $request): JsonResponse
    {
        /** @var TelegramUser $user */
        $user = $request->attributes->get('telegramUser');

        $rows = Announcement::query()
            ->activeAt(Carbon::now())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get()
            ->filter(fn (Announcement $a) => $a->appliesTo($user))
            ->values();

        return response()->json([
            'announcements' => $rows->map(fn (Announcement $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'bodyMd' => $a->body_md,
                'severity' => $a->severity,
                'startsAt' => $a->starts_at?->toIso8601String(),
                'endsAt' => $a->ends_at?->toIso8601String(),
            ])->all(),
        ]);
    }
}
