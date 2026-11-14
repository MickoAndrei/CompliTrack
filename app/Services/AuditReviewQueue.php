<?php

namespace App\Services;

use App\Models\{Audit, User};
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;

class AuditReviewQueue
{
    public static function submission(Audit $audit): array
    {
        $history = collect($audit->history ?? [])->values();
        $events = $history->where('action', 'submit');
        $lastReturn = $history->where('action', 'return')->keys()->last();
        $lastSubmit = $events->keys()->last();
        return ['at' => $events->last()['at'] ?? null, 'resubmitted' => $events->count() > 1
            || ($lastReturn !== null && $lastSubmit !== null && $lastSubmit > $lastReturn)];
    }

    public function page(Request $request): array
    {
        $request->validate(['search' => 'nullable|string|max:160', 'page' => 'nullable|integer|min:1',
            'planId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i', 'auditorId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i',
            'departmentId' => 'nullable|string|regex:/^[a-f0-9]{24}$/i']);
        $status = $request->query('status', 'submitted');
        abort_unless(in_array($status, ['submitted', 'returned', 'accepted'], true), 422);
        $records = app(AtlWorkspace::class)->audits($request->user())->whereIn('status', ['submitted', 'returned', 'accepted'])->get();
        $counts = collect(['submitted', 'returned', 'accepted'])->mapWithKeys(fn ($s) => [$s => $records->where('status', $s)->count()])->all();
        $people = User::whereIn('_id', $records->pluck('auditorId')->unique()->values()->all())->get(['fullName'])->keyBy(fn ($u) => (string) $u->getKey());
        $departments = $records->mapWithKeys(fn ($a) => [(string) $a->departmentId => $a->departmentName])->all();
        $plans = app(AtlWorkspace::class)->evidence($request->user(), $records->pluck('planId')->filter()->map('strval')->all());
        $filtered = $records->filter(function ($audit) use ($request, $status) {
            if ($audit->status !== $status) { return false; }
            foreach (['planId', 'auditorId', 'departmentId'] as $field) {
                if ($request->filled($field) && (string) $audit->$field !== $request->query($field)) { return false; }
            }
            $search = mb_strtolower(trim($request->query('search', '')));
            return $search === '' || str_contains(mb_strtolower($audit->auditCode.' '.$audit->title.' '.$audit->departmentName), $search);
        })->sortBy(fn ($a) => (self::submission($a)['at'] ?? '9999').'|'.$a->getKey())->values();
        $audits = new LengthAwarePaginator($filtered->forPage($request->integer('page', 1), 20), $filtered->count(), 20,
            $request->integer('page', 1), ['path' => $request->url(), 'query' => $request->query()]);
        return compact('audits', 'counts', 'status', 'people', 'departments', 'plans');
    }
}
