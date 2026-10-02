<?php

namespace App\Http\Middleware;

use App\Services\LibraryPracticeBridge;
use App\Services\LibraryPracticeService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLibraryPracticeSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $started = hrtime(true);
        $account = app(LibraryPracticeService::class)->account($request, app(LibraryPracticeBridge::class));
        $request->attributes->set('library_practice_account', $account);

        $response = $next($request);

        return $response->header('Server-Timing', 'practice;dur='.round((hrtime(true) - $started) / 1e6, 1))->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }
}
