<?php

namespace App\Http\Middleware;

use App\Services\ReturnLocationGuard;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureReturnLocation
{
    public function handle(Request $request, Closure $next, string $action = 'return'): Response
    {
        if (ReturnLocationGuard::allows($request)) {
            return $next($request);
        }

        $flash = [
            'error_message' => ReturnLocationGuard::denialMessage($action),
        ];

        if ($action === 'return') {
            $flash['active_tab'] = 'return';
        }

        return redirect()->back()->with($flash);
    }
}
