<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Services\ArchiveKeyRotationService;
use App\Services\SystemBackupService;
use App\Services\SystemOperationLock;
use App\Services\SystemSettingsService;
use App\Services\SystemWipeService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SettingsController extends Controller
{
    public function show(SystemSettingsService $settings)
    {
        return response()->json($settings->publicState())->header('Cache-Control', 'no-store');
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
