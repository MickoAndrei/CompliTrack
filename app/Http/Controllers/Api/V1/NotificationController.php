<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Notification;
use App\Services\AuditLogService;
use App\Services\RoleScopeService;
use App\Support\MongoId;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function __construct(
        private readonly RoleScopeService $scopes,
        private readonly AuditLogService $auditLogs,
    ) {
    }

    public function index(Request $request)
    {
        $page = $this->scopes->notifications($request->user())
            ->orderBy('createdAt', 'desc')
            ->paginate(min(max((int) $request->query('perPage', 20), 1), 100));
        $data = collect($page->items())->map(fn (Notification $notification) => $this->serialize($notification));

        return response()->json([
            'data' => $data,
            'meta' => [
                'currentPage' => $page->currentPage(),
                'perPage' => $page->perPage(),
                'total' => $page->total(),
                'lastPage' => $page->lastPage(),
            ],
        ]);
    }

    public function markRead(Request $request, string $id)
    {
        $notification = $this->scopes->notifications($request->user())
            ->where('_id', MongoId::parse($id))
            ->firstOrFail();
        $notification->isRead = true;
        $notification->save();
        $this->auditLogs->record($request, 'Read notification', 'notification', $notification->getKey());

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
            ->where('roleId', 3)
            ->where('departmentId', $department->getKey())
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
                'fcmSent' => false,
            ]);
        }

        $this->auditLogs->record(
            $request,
            'Broadcast notification to department auditees',
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
        $data = $notification->toArray();
        $data['_id'] = (string) $notification->getKey();
        unset($data['id']);

        return $data;
    }
}
