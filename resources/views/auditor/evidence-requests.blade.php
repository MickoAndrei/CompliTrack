<section class="workflow-card" id="evidence-requests">
    <h3>Auditee Evidence Requests</h3><p class="workflow-muted">Choose the recipient and write only the permitted request text. Internal checklist and interview notes are not shared.</p>
    @if((int) auth()->user()->roleId === 2 && in_array($audit->status, ['draft','ready','returned']))
    <form method="POST" action="/audits/{{ $audit->getKey() }}/requests" class="workflow-form">@csrf<input type="hidden" name="version" value="{{ $audit->version }}">
        <label>Related check item<select name="itemId" required>@foreach($audit->items ?? [] as $item)<option value="{{ $item['id'] }}">{{ $item['question'] }}</option>@endforeach</select></label>
        <label>Auditee<select name="auditeeId" required>@foreach($auditees as $person)<option value="{{ $person->getKey() }}">{{ $person->fullName }}</option>@endforeach</select></label>
        <label>Permitted evidence request<textarea name="description" required maxlength="2000"></textarea></label>
        <label>Reference standard<select name="referenceStandard">@foreach(['ISO9001-9.2','ARTA-3days','ARTA-7days','ARTA-20days'] as $standard)<option>{{ $standard }}</option>@endforeach</select></label>
        <label><input type="checkbox" name="allowUpload" value="1"> Authorize file copying for this request (only if document-control rules permit).</label>
        <button class="auditor-gold-button">Send evidence request</button>
    </form>
    @endif
    @forelse($audit->evidenceRequests ?? [] as $entry)
    <article class="workflow-request"><h4>{{ $entry['description'] }}</h4><p>{{ $entry['status'] }} · Auditee: {{ \App\Models\User::find($entry['auditeeId'])?->fullName }}</p>
        @if((int) auth()->user()->roleId === 2 && $audit->status === 'accepted' && empty($entry['published']))
        <form method="POST" action="/audits/{{ $audit->getKey() }}/requests/{{ $entry['id'] }}/publish" class="workflow-form">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><label>Permitted result summary for this auditee<textarea name="publicSummary" required maxlength="3000"></textarea></label><p>The saved N/C/O result is shared. Internal remarks and interviews stay private; the accepted checklist is unchanged.</p><button class="auditor-gold-button">Share accepted result</button></form>
        @endif
        @foreach($entry['responses'] as $response)<div class="workflow-response"><p>{{ $response['method'] }} · {{ $response['reference'] ?? '' }} · {{ $response['at'] }}</p><p>{{ $response['notes'] }}</p>@if(isset($response['availability']))<p>Reported availability: {{ $response['availability'] }} — not a verification.</p>@endif @if(isset($response['archiveId']))<a href="/api/v1/archives/{{ $response['archiveId'] }}/download">View authorized evidence file</a>@endif @if(isset($response['review']))<p>Feedback: {{ $response['review']['feedback'] }}</p>@endif</div>@endforeach
        @if((int) auth()->user()->roleId === 2 && $entry['status'] === 'response_submitted' && in_array($audit->status,['draft','ready','returned']))
        <form method="POST" action="/audits/{{ $audit->getKey() }}/requests/{{ $entry['id'] }}/review" class="workflow-form">@csrf<input type="hidden" name="version" value="{{ $audit->version }}">
            <label>Permitted feedback / factual review notes<textarea name="feedback" required maxlength="3000"></textarea></label>
            <label>Physical-original signature outcome (only for physical evidence)<select name="signature"><option value="not-verified">Not yet verified</option><option value="verified">Verified</option><option value="missing-incomplete">Missing / incomplete</option><option value="not-required">Not required</option></select></label>
            <div class="workflow-actions"><button name="decision" value="reviewed" class="auditor-gold-button">Record review and link to item</button><button name="decision" value="needs_information" class="workflow-secondary">Request more information</button></div>
        </form>
        @endif
    </article>
    @empty<p>No requests sent.</p>@endforelse
</section>
