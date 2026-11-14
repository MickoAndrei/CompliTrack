<section class="workflow-card" id="evidence-requests">
    <div class="aw-section-heading"><div><h3>Auditee Evidence Requests</h3><p>Requests and auditee responses. Internal checklist and interview notes are not shared.</p></div>
    @if($editable)<button type="button" class="aw-button" @click="openModal('request')" @disabled(empty($audit->items))>＋ New Evidence Request</button>@endif</div>
    @if((int) auth()->user()->roleId === 2 && in_array($audit->status, ['draft','ready','returned']))
    <dialog id="workspace-request-dialog" class="aw-dialog" aria-labelledby="aw-request-title" @cancel.prevent="closeModal($event)">
    <div class="aw-dialog-heading"><h3 id="aw-request-title">New Evidence Request</h3><button type="button" class="aw-button" aria-label="Close evidence request form" @click="closeModal($event)">✕</button></div>
    <form method="POST" action="/audits/{{ $audit->getKey() }}/requests" class="aw-form">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="workspaceAction" value="request">
        <label>Related check item<select name="itemId" required autofocus>@foreach($audit->items ?? [] as $item)<option value="{{ $item['id'] }}" @selected(old('itemId') === $item['id'])>{{ $item['question'] }}</option>@endforeach</select></label>
        <label>Auditee<select name="auditeeId" required>@foreach($auditees as $person)<option value="{{ $person->getKey() }}" @selected(old('workspaceAction') === 'request' && old('auditeeId') === (string) $person->getKey())>{{ $person->fullName }}</option>@endforeach</select></label>
        <label>Permitted evidence request<textarea name="description" required maxlength="2000">{{ old('description') }}</textarea></label>
        <label>Reference standard<select name="referenceStandard">@foreach(['ISO9001-9.2','ARTA-3days','ARTA-7days','ARTA-20days'] as $standard)<option @selected(old('referenceStandard') === $standard)>{{ $standard }}</option>@endforeach</select></label>
        <label><input type="checkbox" name="allowUpload" value="1" @checked(old('allowUpload'))> Authorize file copying for this request (only if document-control rules permit).</label>
        <div class="aw-actions"><button type="button" class="aw-button" @click="closeModal($event)">Cancel</button><button class="aw-button aw-button-primary">Send evidence request</button></div>
    </form>
    </dialog>
    @endif
    @forelse($audit->evidenceRequests ?? [] as $entry)
    <article class="workflow-request"><div class="aw-row"><h4>{{ $entry['description'] }}</h4><span class="aw-badge">{{ ucwords(str_replace('_', ' ', $entry['status'])) }}</span></div><p class="aw-muted">Recipient: {{ $name($entry['auditeeId']) }} · {{ $date($entry['createdAt'] ?? null) }}</p>
        @php($relatedIndex = collect($items)->search(fn ($item) => ($item['id'] ?? '') === ($entry['itemId'] ?? null)))
        @if($relatedIndex !== false)<button type="button" class="aw-button" @click="go('checklist', {{ $relatedIndex }})">Check item {{ $relatedIndex + 1 }} · {{ $items[$relatedIndex]['question'] }} →</button>@endif
        <p class="aw-muted">Auditee response: {{ count($entry['responses'] ?? []) ? count($entry['responses']).' response(s) received' : 'Awaiting response' }}</p>
        @if((int) auth()->user()->roleId === 2 && $audit->status === 'accepted' && empty($entry['published']))
        <form method="POST" action="/audits/{{ $audit->getKey() }}/requests/{{ $entry['id'] }}/publish" class="workflow-form">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><label>Permitted result summary for this auditee<textarea name="publicSummary" required maxlength="3000"></textarea></label><p>The saved N/C/O result is shared. Internal remarks and interviews stay private; the accepted checklist is unchanged.</p><button class="auditor-gold-button">Share accepted result</button></form>
        @endif
        @foreach($entry['responses'] ?? [] as $response)<div class="workflow-response"><p>{{ ucwords(str_replace('-', ' ', $response['method'])) }} · {{ $response['reference'] ?? '' }} · {{ $date($response['at'] ?? null) }}</p><p>{{ $response['notes'] }}</p>@if(isset($response['availability']))<p>Reported availability: {{ $response['availability'] }} — not a verification.</p>@endif @if(!empty($response['archiveId']) && ($attachment = $attachments->get((string) $response['archiveId']))) @if($attachment['canDownload'])<a href="/api/v1/documents/{{ $attachment['id'] }}/download" data-evidence-preview data-file-name="{{ $attachment['name'] }}" aria-haspopup="dialog" aria-label="Preview {{ $attachment['name'] }}">Preview file</a>@else<small>File download restricted</small>@endif @endif @if(isset($response['review']))<p>Feedback: {{ $response['review']['feedback'] }}</p>@endif</div>@endforeach
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
