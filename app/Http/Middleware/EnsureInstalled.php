<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! app()->runningUnitTests() && ! file_exists(storage_path('installed.lock'))) {
            return redirect()->to(url('install.php'));
        }

        return $next($request);
    }
}
