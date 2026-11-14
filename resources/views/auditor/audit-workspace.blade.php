@extends('layouts.app')
@section('title', 'Audit Workspace')
@section('heading', 'Audit Workspace')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/audit-workspace.css') }}?v={{ filemtime(public_path('css/audit-workspace.css')) }}">
@endpush
@section('content')
@php
    $editable = (int) auth()->user()->roleId === 2 && in_array($audit->status, ['draft', 'ready', 'returned']);
    $preparing = $editable && $audit->status === 'draft';
    $conducting = $editable && !$preparing;
    $items = $audit->items ?? [];
    $date = fn ($value) => \App\Services\AtlWorkspace::date($value);
    $name = fn ($id) => $people->get((string) $id)?->fullName ?? 'Former or unavailable user';
    $oldEvaluation = fn ($index, $field, $fallback = '') => old('action') === 'evaluate' && (int) old('item') === $index ? old($field, $fallback) : $fallback;
    $eventNames = ['add' => 'Checklist item added', 'edit' => 'Checklist item updated', 'remove' => 'Checklist item removed', 'move' => 'Checklist reordered', 'ready' => 'Checklist marked ready', 'evaluate' => 'Evaluation saved', 'evidence' => 'Evidence review recorded', 'interview' => 'Interview recorded', 'submit' => 'Results submitted for review', 'accept' => 'Audit accepted', 'return' => 'Returned for revision', 'assigned' => 'Audit assigned', 'created' => 'Audit created', 'reassigned' => 'Assignment updated'];
    $nextSteps = match ($audit->status) {
        'draft' => ['Prepare checklist', 'Conduct audit', 'Submit audit results', 'Audit Team Leader review', 'Audit completion'],
        'ready', 'returned' => ['Conduct audit', 'Submit audit results', 'Audit Team Leader review', 'Audit completion'],
        'submitted' => ['Awaiting Audit Team Leader review', 'Audit completion'],
        default => [],
    };
@endphp
<div class="audit-workspace" x-data="auditWorkspace({{ count($items) }})" data-recovery="{{ $errors->any() ? old('action', old('workspaceAction', '')) : '' }}" data-old-item="{{ old('item', 0) }}" data-old-question="{{ old('question') }}" data-old-reference="{{ old('reference') }}" data-old-method="{{ old('method', 'digital') }}">
    <a href="/audits" class="aw-back">← My Assigned Audits</a>
    @include('auditor.audit-feedback')
    <div class="aw-shell">
        <header class="aw-header">
            <div class="aw-heading">
                <div><p class="aw-eyebrow">My Assigned Audits / Audit Workspace</p><h2>{{ $audit->title }}</h2><p class="aw-muted">{{ $audit->auditCode }} · {{ $audit->departmentName }}</p></div>
                <span class="aw-badge aw-status">{{ ucfirst($audit->status) }}</span>
            </div>
            <div class="aw-meta"><span>Auditor <strong>{{ $name($audit->auditorId) }}</strong></span><span>Scheduled <strong>{{ $audit->schedule ? \Carbon\Carbon::parse($audit->schedule)->format('M j, Y') : 'Not scheduled' }}</strong></span><span>Reviewer <strong>{{ $name($audit->reviewerId) }}</strong></span></div>
            <div class="aw-stats"><div class="aw-stat"><span>Check items</span><strong>{{ $progress['total'] }}</strong></div><div class="aw-stat"><span>Evaluated</span><strong>{{ $progress['evaluated'] }} / {{ $progress['total'] }}</strong></div><div class="aw-stat"><span>Checklist completion</span><strong>{{ $progress['percent'] }}%</strong></div></div>
            <progress class="aw-progress" value="{{ $progress['evaluated'] }}" max="{{ max(1, $progress['total']) }}" aria-label="Checklist evaluation completion"></progress>
            <div class="aw-counts"><span>{{ $progress['C'] }} Compliant</span><span>{{ $progress['N'] }} Non-Conformance</span><span>{{ $progress['O'] }} Observation</span><span>{{ $progress['pending'] }} Pending</span><span x-show="dirty.size" x-cloak role="status">Unsaved changes — kept while switching sections</span></div>
        </header>
        <nav class="aw-tabs" role="tablist" aria-label="Audit workspace sections" @keydown="tabKey($event)">
            @foreach(['overview' => 'Overview', 'checklist' => 'Audit Checklist', 'requests' => 'Evidence Requests', 'interviews' => 'Auditee Interviews', 'review' => 'Review & Submit'] as $key => $label)
            <button type="button" role="tab" id="aw-tab-{{ $key }}" aria-controls="aw-panel-{{ $key }}" :aria-selected="tab === '{{ $key }}'" :tabindex="tab === '{{ $key }}' ? 0 : -1" @click="go('{{ $key }}')">{{ $label }}</button>
            @endforeach
        </nav>
        <div class="aw-content">
            <section x-show="tab === 'overview'" x-cloak id="aw-panel-overview" role="tabpanel" aria-labelledby="aw-tab-overview" tabindex="0">
                <div class="aw-section-heading"><div><h3>Audit overview</h3><p>Your assignment, scope, and next steps.</p></div></div>
                <div class="aw-grid">
                    <article class="aw-card aw-stack"><div><h4>Scope</h4><p class="aw-preserve">{{ $audit->scope }}</p></div><div><h4>Audit criteria</h4><p class="aw-preserve">{{ $audit->criteria }}</p></div><div><h4>Authorized audit attachments</h4>@forelse($audit->planArchiveIds ?? [] as $id) @if($attachment = $attachments->get((string) $id))<p>@if($attachment['canDownload'])<a href="/api/v1/documents/{{ $attachment['id'] }}/download">{{ $attachment['name'] }}</a>@else{{ $attachment['name'] }} <small>Download restricted</small>@endif</p>@endif @empty<p class="aw-muted">No plan attachments.</p>@endforelse</div></article>
                    <article class="aw-card aw-stack"><h4>{{ $preparing ? 'Prepare checklist' : ($conducting ? 'Conduct audit' : 'Audit review') }}</h4><p class="aw-muted">{{ $preparing ? 'Add, edit, remove, and reorder questions. Mark the checklist ready when preparation is complete.' : ($conducting ? 'Review evidence, evaluate N/C/O, and record objective audit remarks.' : 'Results are locked. Submitted audits await the authorized reviewer’s decision.') }}</p><button type="button" class="aw-button aw-button-primary" @click="go('{{ $editable ? 'checklist' : 'review' }}')">{{ $editable ? 'Open checklist' : 'View review & submission' }} →</button><p class="aw-muted">Checklist completion measures evaluated items. It is not a compliance percentage.</p></article>
                </div>
            </section>

            <section x-show="tab === 'checklist'" x-cloak id="aw-panel-checklist" role="tabpanel" aria-labelledby="aw-tab-checklist" tabindex="0">
                <div class="aw-section-heading"><div><h3>Audit Checklist</h3><p>{{ $preparing ? 'Prepare questions and applicable criteria for this audit.' : 'Review evidence and record compliance evaluations.' }}</p></div>@if($preparing)<button type="button" class="aw-button" @click="prepare()">＋ Add Checklist Item</button>@endif</div>
                <div class="aw-phase">@if($preparing)<strong>Draft · Prepare checklist.</strong> Questions can be edited until the checklist is marked ready.@elseif($conducting)<strong>{{ ucfirst($audit->status) }} · Conduct audit.</strong> Prepared questions are retained. Record an evidence review before choosing N/C/O.@else<strong>{{ ucfirst($audit->status) }} · Results locked.</strong> @if($audit->status === 'submitted')Awaiting the authorized ATL's review.@elseThis workspace is read-only.@endif @endif</div>
                <div class="aw-checklist">
                    <aside class="aw-list" aria-label="Checklist items">
                        <h4>Checklist items <small>({{ count($items) }})</small></h4>
                        <label><span class="sr-only">Search checklist items</span><input type="search" placeholder="Search questions or criteria…" x-model="search"></label>
                        <div class="aw-items">
                            @foreach($items as $index => $item)
                            <button type="button" class="aw-item" x-show="matches($el.dataset.search)" data-search="{{ ($index + 1).' '.$item['question'].' '.($item['reference'] ?? '').' '.($item['result'] ?? 'Pending') }}" :aria-current="selected === {{ $index }}" @click="go('checklist', {{ $index }})" aria-controls="aw-item-{{ $index }}">
                                <span class="aw-item-title">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }} · {{ $item['question'] }}</span>
                                <span class="aw-item-meta"><span class="aw-badge aw-badge-{{ $item['result'] ?? '' }}">{{ $item['result'] ?? 'Pending' }}</span><span>{{ count($item['evidence'] ?? []) ? '✓ '.count($item['evidence']).' evidence reviewed' : 'No evidence review' }}</span></span>
                            </button>
                            @endforeach
                        </div>
                        <p x-show="search && ![...$el.parentElement.querySelectorAll('.aw-item')].some(item => matches(item.dataset.search))" x-cloak class="aw-empty">No matching checklist items.</p>
                        @if($preparing)<button type="button" class="aw-button aw-add" @click="prepare()">＋ Add checklist item</button>@endif
                    </aside>
                    <div>
                        @forelse($items as $index => $item)
                        <article class="aw-detail" id="aw-item-{{ $index }}" x-show="selected === {{ $index }}" aria-label="Check item {{ $index + 1 }}">
                            <div class="aw-detail-header">
                                <div class="aw-row"><span class="aw-eyebrow">Selected check item</span><div class="aw-actions"><span class="aw-badge">{{ str_pad($index + 1, 2, '0', STR_PAD_LEFT) }} / {{ str_pad(count($items), 2, '0', STR_PAD_LEFT) }}</span>
                                @if($preparing)
                                <details class="aw-menu"><summary>Item actions</summary><div class="aw-menu-content">
                                    <button type="button" class="aw-button" @click="prepare({{ $index }}, @js($item['question']), @js($item['reference'] ?? ''))">Edit question</button>
                                    <form method="POST">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="item" value="{{ $index }}"><button name="action" value="move" class="aw-button" @disabled($index === 0)>Move up</button></form>
                                    <form method="POST" data-confirm="Remove this draft question?">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="item" value="{{ $index }}"><button name="action" value="remove" class="aw-button">Remove item</button></form>
                                </div></details>
                                @endif</div></div>
                                <h4>{{ $item['question'] }}</h4><p class="aw-muted">Criterion: {{ ($item['reference'] ?? '') ?: 'Not specified' }}</p>
                            </div>
                            <div class="aw-subtabs" role="tablist" aria-label="Selected item sections" @keydown="tabKey($event)">
                                @foreach(['evaluation' => 'Evaluation', 'evidence' => 'Evidence Reviewed'] as $key => $label)<button type="button" role="tab" id="aw-{{ $key }}-tab-{{ $index }}" aria-controls="aw-{{ $key }}-{{ $index }}" :aria-selected="panel === '{{ $key }}'" :tabindex="panel === '{{ $key }}' ? 0 : -1" @click="go('checklist', {{ $index }}, '{{ $key }}')">{{ $label }}@if($key === 'evidence') ({{ count($item['evidence'] ?? []) }})@endif</button>@endforeach
                            </div>
                            <div x-show="panel === 'evaluation'" role="tabpanel" id="aw-evaluation-{{ $index }}" aria-labelledby="aw-evaluation-tab-{{ $index }}" tabindex="0">
                                @if($conducting)
                                <form method="POST" class="aw-form" x-data="{ result: @js($oldEvaluation($index, 'result', $item['result'] ?? '')) }">@csrf
                                    <input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="action" value="evaluate"><input type="hidden" name="item" value="{{ $index }}">
                                    <fieldset @disabled(empty($item['evidence']))><legend>Compliance decision</legend><div class="aw-choices">@foreach(['C' => 'Compliant', 'N' => 'Non-Conformance', 'O' => 'Observation'] as $value => $label)<label class="aw-choice"><input type="radio" name="result" value="{{ $value }}" x-model="result" required><span class="aw-badge aw-badge-{{ $value }}">{{ $value }}</span>{{ $label }}</label>@endforeach</div></fieldset>
                                    @if(empty($item['evidence']))<p class="aw-phase">Record an evidence review to enable evaluation. <button type="button" class="aw-button" @click="go('checklist', {{ $index }}, 'evidence')">Review evidence →</button></p>@endif
                                    <label>Audit Remarks<textarea name="remarks" required maxlength="5000" placeholder="Record your objective audit observations…">{{ $oldEvaluation($index, 'remarks', $item['remarks'] ?? '') }}</textarea></label>
                                    <label x-show="result === 'N' || result === 'O'">Finding description <span x-text="result === 'N' ? '(required for nonconformity)' : '(optional observation)' "></span><textarea name="finding" :required="result === 'N'" maxlength="5000">{{ $oldEvaluation($index, 'finding', $item['finding'] ?? '') }}</textarea></label>
                                    <p class="aw-muted">{{ count($item['evidence'] ?? []) }} evidence review(s) linked to this item. Findings do not automatically create a CAR.</p>
                                    <div><button class="aw-button aw-button-primary" @disabled(empty($item['evidence']))>Save Evaluation</button></div>
                                </form>
                                @else
                                <div class="aw-stack"><div><h4>Compliance decision</h4><span class="aw-badge aw-badge-{{ $item['result'] ?? '' }}">{{ $item['result'] ?? 'Not yet evaluated' }}</span></div><div><h4>Audit Remarks</h4><p class="aw-preserve aw-muted">{{ ($item['remarks'] ?? '') ?: ($preparing ? 'Mark the checklist ready to begin evaluation.' : 'No remarks recorded.') }}</p></div>@if(!empty($item['finding']))<div><h4>Finding description</h4><p class="aw-preserve">{{ $item['finding'] }}</p></div>@endif<div><h4>Evidence reviewed</h4><p class="aw-muted">{{ count($item['evidence'] ?? []) }} linked evidence review(s)</p></div></div>
                                @endif
                            </div>
                            <div x-show="panel === 'evidence'" role="tabpanel" id="aw-evidence-{{ $index }}" aria-labelledby="aw-evidence-tab-{{ $index }}" tabindex="0" class="aw-stack">
                                <div class="aw-row"><h4>Evidence Reviewed</h4>@if($editable)<button type="button" class="aw-button" @click="openModal('evidence')">＋ Add Evidence Review</button>@endif</div>
                                @forelse($item['evidence'] ?? [] as $evidence)
                                <article class="aw-card"><span class="aw-badge">{{ ucwords(str_replace('-', ' ', $evidence['method'])) }}</span><p>{{ $evidence['reference'] ?? '' }}</p><p class="aw-preserve">{{ $evidence['notes'] }}</p>@if(isset($evidence['signature']))<p>Physical signature: {{ ucwords(str_replace('-', ' ', $evidence['signature'])) }}</p>@endif
                                    @if(!empty($evidence['archiveId']) && ($attachment = $attachments->get((string) $evidence['archiveId'])))<p>@if($attachment['canDownload'])<a href="/api/v1/documents/{{ $attachment['id'] }}/download">{{ $attachment['name'] }}</a>@else{{ $attachment['name'] }} <small>Download restricted</small>@endif</p>@endif
                                    <small>{{ $date($evidence['reviewedAt'] ?? null) }} · {{ $name($evidence['reviewedBy'] ?? '') }}</small>
                                </article>
                                @empty<div class="aw-empty">No evidence reviews recorded. Review a permitted digital reference, physical original, or observed process.</div>@endforelse
                                <p class="aw-muted">Record only permitted facts. No copy or upload of protected QMS documents is required.</p>
                            </div>
                            <footer class="aw-footer"><button type="button" class="aw-button" @disabled($index === 0) @click="go('checklist', {{ max(0, $index - 1) }})">← Previous</button><button type="button" class="aw-button" @disabled($index === count($items) - 1) @click="go('checklist', {{ min(count($items) - 1, $index + 1) }})">Next item →</button></footer>
                        </article>
                        @empty<div class="aw-empty"><h4>No checklist items yet</h4><p>Prepare the questions and criteria for this audit.</p>@if($preparing)<button type="button" class="aw-button aw-button-primary" @click="prepare()">＋ Add first checklist item</button>@endif</div>@endforelse
                    </div>
                </div>
                @if($preparing && count($items))<footer class="aw-footer"><p class="aw-muted">Prepared questions are retained once the checklist is ready.</p><form method="POST" data-confirm="Freeze these questions for evaluation?">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><button name="action" value="ready" class="aw-button aw-button-gold">Mark checklist ready →</button></form></footer>@endif
            </section>

            <section x-show="tab === 'requests'" x-cloak id="aw-panel-requests" role="tabpanel" aria-labelledby="aw-tab-requests" tabindex="0">@include('auditor.evidence-requests')</section>

            <section x-show="tab === 'interviews'" x-cloak id="aw-panel-interviews" role="tabpanel" aria-labelledby="aw-tab-interviews" tabindex="0">
                <div class="aw-section-heading"><div><h3>Auditee Interviews</h3><p>Optional supporting notes. Internal responses remain private.</p></div>@if($editable)<button type="button" class="aw-button" @click="openModal('interview')">＋ Add Interview</button>@endif</div>
                <div class="aw-stack">@forelse($audit->interviews ?? [] as $interview)<details class="aw-card"><summary><strong>{{ $interview['auditeeName'] }} · {{ $interview['process'] }}</strong><br><small>{{ $date($interview['at'] ?? null) }}</small></summary><div class="aw-stack"><div><h4>Questions / topics</h4><p class="aw-preserve">{{ $interview['topics'] }}</p></div><div><h4>Auditee responses</h4><p class="aw-preserve">{{ $interview['responses'] }}</p></div><div><h4>Factual notes</h4><p class="aw-preserve">{{ $interview['notes'] ?? 'None' }}</p></div></div></details>@empty<div class="aw-empty">No interviews recorded. Interviews are optional.</div>@endforelse</div>
            </section>

            <section x-show="tab === 'review'" x-cloak id="aw-panel-review" role="tabpanel" aria-labelledby="aw-tab-review" tabindex="0">
                <div class="aw-section-heading"><div><h3>Review & Submit</h3><p>Check completeness before submitting results to the authorized ATL.</p></div><span class="aw-badge {{ $readiness['eligible'] && $conducting ? 'aw-badge-C' : '' }}">{{ $readiness['eligible'] && $conducting ? 'Ready to submit' : ($editable ? 'Preparation required' : ucfirst($audit->status)) }}</span></div>
                <div class="aw-grid aw-review-grid">
                    <article class="aw-card aw-stack" data-submission-readiness><h4>Submission readiness</h4><ul class="aw-checks"><li>Total checklist items <strong>{{ $progress['total'] }}</strong></li><li>Evaluated items <strong>{{ $progress['evaluated'] }}</strong></li>@foreach(['decisions' => 'Missing N/C/O decisions', 'remarks' => 'Missing remarks', 'evidence' => 'Missing evidence reviews', 'findings' => 'Nonconformities requiring descriptions'] as $field => $label)<li>{{ $label }} <span class="aw-badge {{ $readiness[$field] ? 'aw-badge-N' : 'aw-badge-C' }}">{{ $readiness[$field] }}</span></li>@endforeach</ul>
                        @if(!$progress['total'])<p class="aw-phase">Prepare at least one checklist item.</p>@endif
                        @if($preparing)<p class="aw-phase">Complete checklist preparation and mark it ready before evaluation and submission.</p>@endif
                        @foreach($readiness['incomplete'] as $index => $reasons)<button type="button" class="aw-button" @click="go('checklist', {{ $index }}, '{{ in_array('evidence', $reasons) ? 'evidence' : 'evaluation' }}')">Item {{ $index + 1 }} · {{ implode(', ', $reasons) }} →</button>@endforeach
                        @if($conducting)<form method="POST" data-confirm="Submit these results to the designated reviewer? Editing will be locked.">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><button name="action" value="submit" class="aw-button aw-button-primary" @disabled(!$readiness['eligible'])>Submit audit results →</button></form><p class="aw-muted">Submission locks editing until the reviewer returns the audit for revision.</p>@endif
                        @if((int) auth()->user()->roleId === 1 && $audit->status === 'submitted')<form method="POST" class="aw-form" data-confirm="Record this review decision?">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><label>Review notes<textarea name="reviewNotes" required maxlength="5000">{{ old('reviewNotes') }}</textarea></label><div class="aw-actions"><button name="action" value="accept" class="aw-button aw-button-primary">Accept audit</button><button name="action" value="return" class="aw-button">Return for revision</button></div></form>@endif
                    </article>
                    <article class="aw-card aw-history"><h4 id="aw-history-title">Activity & submission history</h4>
                        <div class="aw-history-scroll" tabindex="0" role="region" aria-labelledby="aw-history-title">
                            <div class="aw-timeline">
                                @forelse($audit->history ?? [] as $event)
                                <div class="aw-event" data-state="completed"><span class="aw-event-node" aria-hidden="true"><i data-lucide="check"></i></span><span class="sr-only">Completed: </span><strong>{{ $eventNames[$event['action']] ?? ucwords(str_replace(['_', '-'], ' ', $event['action'])) }}</strong><small>{{ $date($event['at'] ?? null) }} · {{ $name($event['actor'] ?? '') }}</small>@if(!empty($event['notes']))<p class="aw-preserve">{{ $event['notes'] }}</p>@endif</div>
                                @empty<p class="aw-muted">No activity recorded.</p>@endforelse
                                @foreach($nextSteps as $step)
                                <div class="aw-event" data-state="{{ $loop->first ? 'current' : 'future' }}" @if($loop->first) aria-current="step" @endif><span class="aw-event-node" aria-hidden="true"></span><span class="sr-only">{{ $loop->first ? 'Current: ' : 'Upcoming: ' }}</span><strong>{{ $step }}</strong><small>{{ $loop->first ? 'Current stage' : 'Upcoming stage' }}</small></div>
                                @endforeach
                            </div>
                        </div>
                    </article>
                </div>
            </section>
        </div>
    </div>
    @include('auditor.workspace-modals')
</div>
@include('auditee.evidence-preview-modal', ['closeLabel' => 'Back to evidence requests'])
@endsection
