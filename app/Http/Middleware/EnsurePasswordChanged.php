<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePasswordChanged
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->usesInitialPassword()) {
            return $next($request);
        }

        if ($this->isAllowedWhileUsingInitialPassword($request, $user->isAdmin())) {
            return $next($request);
        }

        return redirect()->route($user->passwordChangeRouteName());
    }

    private function isAllowedWhileUsingInitialPassword(Request $request, bool $isAdmin): bool
    {
        if ($request->routeIs('logout', 'password.*', 'verification.*') || $request->is('confirm-password')) {
            return true;
        }

        return $isAdmin && $request->routeIs('admin.password');
    }
}
