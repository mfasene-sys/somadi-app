<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EntetesSecurite
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'geolocation=(), microphone=(), camera=(self)');
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        }
        // Les pages authentifiées ne doivent pas être conservées par les caches intermédiaires.
        if ($request->user()) {
            $response->headers->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
