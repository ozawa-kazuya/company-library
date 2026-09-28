<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            return redirect()->route('dashboard')->with([
                'error_message' => '管理者権限が必要なページです。',
            ]);
        }

        return $next($request);
    }
}
