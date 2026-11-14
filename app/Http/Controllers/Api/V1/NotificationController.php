<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\AtlWorkspace;
use App\Services\AuditLogService;
use App\Services\RoleScopeService;
use App\Support\MongoId;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly RoleScopeService $scopes,
        private readonly AuditLogService $auditLogs,
    ) {}

    public function index(Request $request)
    {
        $request->validate([
            'status' => ['nullable', 'in:all,unread,read'],
            'search' => ['nullable', 'string', 'max:200'],
            'type' => ['nullable', 'string', 'max:100'],
        ]);
        $query = $this->scopes->notifications($request->user());
        if ($request->boolean('unread')) {
            $query->where('isRead', false);
        }
        if ($request->query('status') === 'unread') {
            $query->where('isRead', false);
        }
        if ($request->query('status') === 'read') {
            $query->where('isRead', true);
        }
        if ($request->filled('type')) {
            $query->where('type', $request->query('type'));
        }
        if ($request->filled('search')) {
            $search = '/'.preg_quote(trim((string) $request->query('search')), '/').'/i';
            $query->where(function ($query) use ($search) {
                $query->where('title', 'regex', $search)
                    ->orWhere('message', 'regex', $search);
            });
        }
        $unreadCount = $request->boolean('includeSummary')
            ? $this->scopes->notifications($request->user())->where('isRead', false)->count() : null;
        $page = $query->orderBy('createdAt', 'desc')->orderBy('_id', 'desc')
            ->paginate(min(max((int) $request->query('perPage', 20), 1), 100));
        $data = collect($page->items())->map(fn (Notification $notification) => $this->serialize($notification));

        return response()->json([
            'data' => $data,
            'meta' => [
                'currentPage' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => $page->lastPage(),
                'unreadCount' => $unreadCount,
            ],
        ]);
    }

    public function show(Request $request, string $id)
    {
        $notification = $this->scopes->notifications($request->user())->where('_id', MongoId::parse($id))->firstOrFail();

        return response()->json(['data' => $this->serialize($notification)]);
    }

    public function open(Request $request, string $id)
    {
        $notification = $this->scopes->notifications($request->user())->where('_id', MongoId::parse($id))->firstOrFail();
        $destination = $this->destination($notification);
        abort_unless($destination, 404, 'The related record is unavailable.');
        $path = parse_url($destination, PHP_URL_PATH);
        if (preg_match('~^/audits/([a-f0-9]{24})$~i', $path, $match)) {
            app(AtlWorkspace::class)->audits($request->user())->where('_id', MongoId::parse($match[1]))->firstOrFail();
        } elseif (preg_match('~^/(?:auditor/checks|auditee/finding-details)/([a-f0-9]{24})$~i', $path, $match)) {
            $check = $this->scopes->checks($request->user())->where('_id', MongoId::parse($match[1]))->firstOrFail();
            abort_unless($this->scopes->canAccessDepartment($request->user(), MongoId::parse((string) $check->departmentId)), 404);
        }

        // The existing destination controller performs its normal scope checks too.
        return redirect($destination);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $this->scopes->notifications($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
        if (! $notification->isRead) {
            $notification->isRead = true;
            $notification->save();
            $this->auditLogs->record($request, 'Read notification', 'notification', $notification->getKey());
        }

        return response()->json(['data' => $this->serialize($notification->fresh())]);
    }

    public function markAllRead(Request $request)
    {
        $this->scopes->notifications($request->user())
            ->where('isRead', false)
            ->update(['isRead' => true]);
        $this->auditLogs->record($request, 'Read all notifications', 'notification');

        return response()->json(['message' => 'Notifications marked as read.']);
    }

    public function broadcast(Request $request)
    {
        $data = $request->validate([
            'departmentId' => ['required', 'string', 'regex:/^[a-f0-9]{24}$/'],
            'message' => ['required', 'string', 'not_regex:/^\s*$/', 'max:2000'],
        ]);
        $departmentId = MongoId::parse($data['departmentId'], 'departmentId');
        $department = $this->scopes->departments($request->user())
            ->where('_id', $departmentId)
            ->where('isActive', true)
            ->firstOrFail();
        $recipients = $this->scopes->users($request->user())
            ->whereIn('roleId', [2, 3])
            ->whereIn('departmentId', [$departmentId, (string) $departmentId])
            ->where('isActive', true)
            ->get(['_id']);

        foreach ($recipients as $recipient) {
            Notification::create([
                'recipientUserId' => $recipient->getKey(),
                'senderUserId' => $request->user()->getKey(),
                'type' => 'admin_broadcast',
                'title' => 'Broadcast Notification',
                'message' => trim($data['message']),
                'isRead' => false,
            ]);
        }

        $this->auditLogs->record(
            $request,
            'Broadcast notification to department auditors and auditees',
            'department',
            $department->getKey(),
        );

        return response()->json([
            'message' => 'Broadcast notification created.',
            'recipientCount' => $recipients->count(),
            'departmentName' => $department->departmentName,
        ], 201);
    }

    private function serialize(Notification $notification): array
    {
        $type = $notification->type ?? 'general';

        return [
            '_id' => (string) $notification->getKey(),
            'title' => $notification->title ?: 'Account update',
            'message' => $notification->message,
            'type' => $type,
            'category' => match (true) {
                str_contains($type, 'evidence') => 'Evidence',
                str_contains($type, 'audit') => 'Audit',
                in_array($type, ['status_changed', 'corrective_action_created']) => 'Corrective action',
                $type === 'admin_broadcast' => 'Announcement',
                str_contains($type, 'security') => 'Security',
                default => 'Update',
            },
            'isRead' => (bool) $notification->isRead,
            'createdAt' => $notification->createdAt,
            'relatedCheckId' => $notification->relatedCheckId ? (string) $notification->relatedCheckId : null,
            'destination' => $this->destination($notification) ? '/api/v1/notifications/'.$notification->getKey().'/open' : null,
        ];
    }

    private function destination(Notification $notification): ?string
    {
        $role = (int) auth()->user()->roleId;
        $destination = $notification->destination;
        if (! $destination && $notification->relatedCheckId) {
            $destination = match ($role) {
                3 => '/auditee/finding-details/'.$notification->relatedCheckId,
                2 => '/auditor/checks/'.$notification->relatedCheckId,
                1 => '/admin/compliance-records',
                default => null,
            };
        }
        if (! $destination && in_array($role, [1, 2], true)
            && preg_match('/^audit:([a-f0-9]{24}):/i', $notification->dedupeKey ?? '', $match)) {
            $destination = '/audits/'.$match[1];
        }
        if (! is_string($destination) || preg_match('/[\\\\\x00-\x20]/', $destination)) {
            return null;
        }
        // Stored notifications may predate the QMS route rename.
        $destination = preg_replace('~^/(super-admin|admin|auditor|auditee)/archives(?=[?#]|$)~', '/$1/documents', $destination);
        $path = parse_url($destination, PHP_URL_PATH);
        $prefix = ['super-admin', 'admin', 'auditor', 'auditee'][$role] ?? null;
        if (! $prefix || ! str_starts_with($destination, '/') || str_starts_with($destination, '//')) {
            return null;
        }
        // Only existing application portals are valid targets; never redirect to
        // arbitrary URLs, downloads, settings mutations, or another role's portal.
        $allowed = preg_match('~^/'.preg_quote($prefix, '~').'/(?:dashboard|notifications|compliance-records|my-findings|evidence-requests|documents|checks(?:/[a-f0-9]{24})?|finding-details/[a-f0-9]{24})$~i', $path)
            || (in_array($role, [1, 2], true) && preg_match('~^/audits(?:/[a-f0-9]{24})?$~i', $path));

        return $allowed ? $destination : null;
    }
}
