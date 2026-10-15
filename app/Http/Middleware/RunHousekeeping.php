<?php

namespace App\Http\Middleware;

use App\Services\Housekeeping;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Fuehrt periodische Aufgaben (Soll-Mieten, status.json) nach dem Ausliefern
 * der Seite aus, da auf dem Webspace kein Cron vorausgesetzt wird.
 */
class RunHousekeeping
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        if ($request->isMethod('GET') && ! app()->runningUnitTests()) {
            app(Housekeeping::class)->runIfDue();
        }
    }
}
