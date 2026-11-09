<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\SuperAdminDashboard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB, Http};

class SuperAdminDashboardController extends Controller
{
    public function summary(Request $request, SuperAdminDashboard $dashboard)
    {
        return response()->json(['data' => $dashboard->summary($request->user())])->header('Cache-Control', 'no-store');
    }

    public function health()
    {
        $mongodb = 'unavailable';
        try {
            DB::connection('mongodb')->getMongoDB()->command(['ping' => 1]);
            $mongodb = 'ok';
        } catch (\Throwable $exception) { report($exception); }
        $realtime = 'unavailable';
        $push = 'Unknown';
        try {
            $url = config('services.complitrack.realtime_health_url') ?: rtrim(config('services.complitrack.realtime_url'), '/').'/health';
            $response = Http::connectTimeout(1)->timeout(2)->get($url);
            if ($response->successful() && $response->json('status') === 'ok' && $response->json('database') === 'ok') {
                $realtime = 'ok';
                $push = $response->json('pushConfigured') === true ? 'Configured' : 'Not Configured';
            }
        } catch (\Throwable $exception) { report($exception); }
        return response()->json(['data' => ['laravel' => 'ok', 'mongodb' => $mongodb, 'realtime' => $realtime,
            'components' => [
                ['name' => 'Laravel', 'status' => 'Operational'],
                ['name' => 'Node.js Service', 'status' => $realtime === 'ok' ? 'Operational' : 'Unavailable'],
                ['name' => 'MongoDB Cluster', 'status' => $mongodb === 'ok' ? 'Operational' : 'Unavailable'],
                ['name' => 'FCM Push Service', 'status' => $push],
            ],
            'checkedAt' => now()->toIso8601String()]])->header('Cache-Control', 'no-store');
    }
}
