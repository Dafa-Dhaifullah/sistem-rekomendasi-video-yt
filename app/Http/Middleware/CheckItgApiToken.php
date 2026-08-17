<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckItgApiToken
{
    public function handle(Request $request, Closure $next): Response
    {
        // Ambil token dari Header 'Authorization: Bearer <token>'
        $token = $request->bearerToken();

        // Cocokkan token request dengan ITG_API_TOKEN di file .env
        if (!$token || $token !== config('services.itg.api_token', env('ITG_API_TOKEN'))) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Unauthorized: Token API ITG tidak valid atau tidak ditemukan.'
            ], 401);
        }

        return $next($request);
    }
}