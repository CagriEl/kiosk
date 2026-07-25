<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKioskYonetimAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->get('kiosk_yonetim_ok')) {
            return redirect()->guest(route('yonetim.login', absolute: false));
        }

        return $next($request);
    }
}
