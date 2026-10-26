<?php

namespace App\Http\Controllers;

use App\Services\WorkshopAlerts;
use Illuminate\Http\Request;

/**
 * Tareas programadas. Vercel Cron llama con `Authorization: Bearer <CRON_SECRET>`.
 */
class CronController extends Controller
{
    public function alerts(Request $request, WorkshopAlerts $alerts)
    {
        $secret = config('services.alerts.cron_secret');
        abort_unless($secret && hash_equals('Bearer ' . $secret, (string) $request->header('Authorization')), 401);

        return response()->json($alerts->check());
    }
}
