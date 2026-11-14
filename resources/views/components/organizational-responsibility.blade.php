@props(['user'])
@php
    $responsibility = match ($user->archiveResponsibility) {
        'department_head' => 'Department Head',
        'department_secretary' => 'Department Secretary',
        'college_dean' => 'College Dean',
        'college_secretary' => 'College Secretary',
        'cluster_administrator' => 'Cluster Administrator',
        'faculty_staff' => 'Faculty / Staff',
        default => null,
    };
@endphp
@if ($responsibility)
    <span {{ $attributes }}><span aria-hidden="true">&middot;</span> {{ $responsibility }} &mdash; {{ app(\App\Services\PositionScope::class)->label($user) }}</span>
@endif
