@component('mail::message')
# CompliTrack - USTP Claveria

Hello {{ $user->fullName ?? $user->email }},

You were invited / requested a password reset. RoleId: {{ $user->roleId }} (0=Super Admin, 1=Admin, 2=Auditor, 3=Auditee).

**One-time code (60 min, SHA-256 hashed in DB):**


@component('mail::button', ['url' => $resetUrl])
Set Password on Website
@endcomponent
If button doesn't work, manually open:
{{ $resetUrl }}
Or go to {{ config('app.url') }}/reset-password and paste:
- Email: {{ $user->email }}
- Code: {{ $token }}
- New password: min 12 chars
Localhost: This log is from MAIL_MAILER=log at storage/logs/laravel.log. For Mailpit UI use http://localhost:8025
Thanks,<br>
{{ config('app.name') }}
@endcomponent