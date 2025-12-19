<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class VpnRedirectController extends Controller
{
    /**
     * Redirect to VPN app import URL
     *
     * This endpoint is used to redirect users to their VPN app
     * with the subscription link for auto-import.
     */
    public function redirect(Request $request): RedirectResponse
    {
        $url = $request->query('url');

        if (!$url) {
            abort(400, 'Missing URL parameter');
        }

        // Validate that the URL is a VPN protocol URL
        $allowedProtocols = ['v2raytun://', 'hiddify://'];
        $isValidProtocol = false;

        foreach ($allowedProtocols as $protocol) {
            if (str_starts_with($url, $protocol)) {
                $isValidProtocol = true;
                break;
            }
        }

        if (!$isValidProtocol) {
            abort(400, 'Invalid URL protocol');
        }

        return redirect()->away($url);
    }
}
