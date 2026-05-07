<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class ThrottleLogin
{
    private const MAX_ATTEMPTS = 5;

    private const DECAY_SECONDS = 60;

    public function handle(Request $request, Closure $next): Response
    {
        $key = $request->input('email').'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, self::MAX_ATTEMPTS)) {
            $seconds = RateLimiter::availableIn($key);

            return redirect()->back()
                ->withInput($request->only('email'))
                ->withErrors(['email' => "登入嘗試過於頻繁，請等候 {$seconds} 秒後再試"]);
        }

        RateLimiter::hit($key, self::DECAY_SECONDS);

        return $next($request);
    }
}
