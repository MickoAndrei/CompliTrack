<?php
namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Services\AuditLogService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function __construct(private readonly AuditLogService $auditLogs) {}

    public function updateProfile(Request $request)
    {
        $validated = $request->validate([
            'fullName' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();
        $user->fullName = trim($validated['fullName']);
        $user->save();
        $this->auditLogs->record($request, 'Updated profile name', 'user', $user->getKey());

        return back()->with('status', 'Profile details updated.');
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'string', 'min:12', 'different:current_password', 'confirmed'],
        ]);

        $user = $request->user();

        if (!Hash::check($validated['current_password'], $user->password)) {
            return back()->withErrors(['current_password' => 'Current password incorrect']);
        }

        $wasTemporaryPassword = $user->passwordChangeRequired;
        $user->password = Hash::make($validated['password'], ['rounds' => 12]);
        $user->passwordChangeRequired = false;
        $user->save();
        $this->auditLogs->record($request, 'Changed account password', 'user', $user->getKey());

        if ($wasTemporaryPassword) {
            $dashboard = match ((int) $user->roleId) {
                0 => '/super-admin/dashboard',
                1 => '/admin/dashboard',
                2 => '/auditor/dashboard',
                3 => '/auditee/dashboard',
                default => '/profile',
            };

            return redirect($dashboard)->with('status', 'Password updated.');
        }

        return back()->with('status', 'Password updated.');
    }
}
