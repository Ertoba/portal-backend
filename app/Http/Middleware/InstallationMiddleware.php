<?php

namespace App\Http\Middleware;

use Closure;

class InstallationMiddleware
{
    public function handle($request, Closure $next)
    {
        if (
            !session()->has('mili_install_ready')
            && !filter_var(env('MILI_INSTALL', false), FILTER_VALIDATE_BOOLEAN)
        ) {
            session()->flash('error', 'Please complete the Mili configuration first.');
            return redirect()->route('step2', [
                'token' => bcrypt('step_2')
            ]);
        }

        return $next($request);
    }
}
