<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class MiniAppController extends Controller
{
    public function show(Request $request)
    {
        return response()
            ->view('miniapp.app')
            ->header('X-Frame-Options', 'ALLOWALL')
            ->header('Content-Security-Policy', "frame-ancestors 'self' https://web.telegram.org https://telegram.org https://*.telegram.org");
    }
}
