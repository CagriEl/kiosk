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
            return redirect()->route('yonetim.login');
        }

        return $next($request);
    }
}
