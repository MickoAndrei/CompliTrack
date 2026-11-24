<?php

namespace App\Services;

use Illuminate\Support\Facades\{DB, File};
use RuntimeException;
use Throwable;

class AllTargetLogout
{
    public function run(): void
    {
        $driver = config('session.driver');
        if (!in_array($driver, ['database', 'mongodb', 'file'], true)) {
            throw new RuntimeException('Unsupported session driver. No sessions were cleared.');
        }
        $connections = [];
        $started = [];
        try {
            // Validate every configuration before opening connections or changing any data.
            foreach (['local', 'remote'] as $environment) {
                $target = config('database.mongodb_targets.'.$environment, []);
                if (empty($target['dsn']) || empty($target['database'])) {
                    throw new RuntimeException('A MongoDB target is not configured.');
                }
                $name = 'database_switch_logout_'.$environment;
                config(['database.connections.'.$name => array_replace(config('database.connections.mongodb', []), $target)]);
                $connections[$environment] = $name;
            }
            foreach ($connections as $name) {
                DB::purge($name);
                DB::connection($name)->getMongoDB()->command(['ping' => 1])->toArray();
            }
            $sessionConnection = config('session.connection') ?: 'mongodb';
            $separateSessionStore = $driver === 'database'
                && config('database.connections.'.$sessionConnection.'.driver') !== 'mongodb';
            if ($separateSessionStore) {
                DB::connection($sessionConnection)->table(config('session.table', 'sessions'))->limit(1)->get();
            }
            if ($driver === 'file') {
                $started[] = 'file sessions';
                if (!File::cleanDirectory(config('session.files'))) {
                    throw new RuntimeException('File session cleanup failed.');
                }
            } elseif ($separateSessionStore) {
                $started[] = 'session database';
                DB::connection($sessionConnection)->table(config('session.table', 'sessions'))->delete();
            }
            foreach ($connections as $environment => $name) {
                $started[] = $environment;
                $connection = DB::connection($name);
                if ($driver === 'mongodb' || ($driver === 'database' && !$separateSessionStore)) {
                    $connection->table(config('session.table', 'sessions'))->delete();
                }
                $connection->table('users')->update(['remember_token' => null]);
                $connection->table('personal_access_tokens')->delete();
            }
        } catch (Throwable $error) {
            // Connection exceptions may contain credentials; report only safe stage information.
            throw new RuntimeException($started
                ? 'Logout failed. Cleanup started for: '.implode(', ', $started).'. These stores may be partially cleared; retry after restoring connectivity.'
                : 'Logout preflight failed. Check both MongoDB targets and session storage. No sessions or credentials were changed.', 0, $error);
        } finally {
            foreach ($connections as $name) { DB::purge($name); }
        }
    }
}
