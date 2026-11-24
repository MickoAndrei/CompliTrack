<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

class MongoConnectionProbe
{
    public function healthy(array $connection): bool
    {
        $name = 'database_switch_health_probe';
        config(['database.connections.'.$name => $connection]);
        DB::purge($name);

        try {
            DB::connection($name)->getMongoDB()->command(['ping' => 1])->toArray();

            return true;
        } catch (Throwable) {
            return false;
        } finally {
            DB::purge($name);
            config(['database.connections.'.$name => null]);
        }
    }
}
