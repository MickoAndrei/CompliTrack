<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ArchiveKeyRotationService;
use App\Services\DatabaseSwitchService;
use App\Services\SystemBackupService;
use App\Services\SystemOperationLock;
use App\Services\SystemSettingsService;
use App\Services\SystemWipeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function show(SystemSettingsService $settings, DatabaseSwitchService $databaseSwitch)
    {
        return response()->json([
            ...$settings->publicState(),
            'databaseSwitch' => $databaseSwitch->status(),
        ])->header('Cache-Control', 'no-store');
    }

    public function requestDatabaseSwitch(Request $request, DatabaseSwitchService $databaseSwitch)
    {
        $data = $request->validate([
            'target' => ['required', 'string', 'in:local,remote'],
            'confirmation' => ['sometimes', 'nullable', 'string', 'max:32'],
        ]);
        $result = $databaseSwitch->request($data['target'], $data['confirmation'] ?? '', $request->user());
        try {
            $databaseSwitch->launchAutomaticSwitch($result['requestId']);
        } catch (\RuntimeException $exception) {
            $databaseSwitch->cancelUndispatched($result['requestId']);
            report($exception);

            return response()->json([
                'message' => 'The automatic switch worker could not start. The active database was not changed.',
            ], 503)->header('Cache-Control', 'no-store');
        }

        return response()->json([
            'message' => 'The database switch started. Services will restart and all users will need to sign in again. No migrations, seeders, or data synchronization will run.',
            'request' => $result,
            'statusUrl' => $databaseSwitch->progressUrl($result['requestId']),
        ], 202)->header('Cache-Control', 'no-store');
    }

    public function databaseSwitchProgress(string $requestId, DatabaseSwitchService $databaseSwitch)
    {
        return response()->json($databaseSwitch->progress($requestId))->header('Cache-Control', 'no-store');
    }

    public function update(Request $request, SystemSettingsService $settings)
    {
        $data = $request->validate([
            'backupsEnabled' => ['sometimes', 'required', 'boolean'],
            'maintenanceEnabled' => ['sometimes', 'required', 'boolean'],
        ]);
        app(SystemOperationLock::class)->run(fn () => $settings->update(fn ($state) => array_replace($state, $data)));

        return $this->show($settings);
    }

    public function backup(SystemBackupService $backup)
    {
        set_time_limit(0);
        $file = $backup->create();

        return response()->json(['message' => 'Encrypted backup completed and verified.', 'file' => $file]);
    }

    public function rotate(Request $request, ArchiveKeyRotationService $rotation)
    {
        $this->confirmPassword($request);
        set_time_limit(0);
        $count = $rotation->rotate();

        return response()->json(['message' => "Encryption key rotated. {$count} archive files re-encrypted."]);
    }

    public function wipe(Request $request, SystemWipeService $wipe)
    {
        $request->validate(['confirmation' => ['required', 'in:WIPE ALL DATA']]);
        $this->confirmPassword($request);
        set_time_limit(0);
        $wipe->wipe($request->user(), $request->hasSession() ? $request->session()->getId() : null);

        return response()->json(['message' => 'System data and backups deleted. Your superadmin account and system configuration were retained.']);
    }

    private function confirmPassword(Request $request): void
    {
        $request->validate(['password' => ['required', 'string', 'max:1024']]);
        if (! Hash::check($request->input('password'), $request->user()->password)) {
            throw ValidationException::withMessages(['password' => 'Your current password is incorrect.']);
        }
    }
}
