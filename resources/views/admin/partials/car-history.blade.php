<section id="car-history" class="car-detail" data-car-reference="{{ $detail['formal'] ? $detail['carNo'] : $detail['checkCode'] }}">
    <aside class="car-overview" aria-label="CAR overview">
        <h3>CAR Overview</h3>
        <div class="car-overview-badges"><span class="car-badge {{ $detail['displayStatus'] }}">{{ $detail['statusLabel'] }}</span>@if($detail['overdue'])<span class="car-badge overdue">Overdue</span>@endif</div>
        @unless($detail['formal'])<p class="car-legacy-note">Legacy compliance check — no formal CAR number recorded</p>@endunless
        <dl class="car-overview-fields">
            @foreach(['CAR No.', 'Date Submitted', 'Type', 'Source', 'Process', 'Assigned To', 'Target Closed-Out Date', 'Actual Closed-Out Date'] as $label)
            <div data-car-field="{{ $label }}"><dt>{{ $label }}</dt><dd>{{ $detail['official'][$label] }}</dd></div>
            @endforeach
        </dl>
    </aside>
    <div class="car-detail-main">
        <p class="car-detail-eyebrow">Official CAR Log Fields · FM-USTP-009-2</p>
        <div class="car-detail-tabs" role="tablist" aria-label="CAR detail sections">
            @foreach(['issue'=>'Issue & Assignment','verification'=>'Verification','history'=>'Evidence & History'] as $key=>$label)
            <button type="button" id="car-tab-{{ $key }}" role="tab" data-car-tab="{{ $key }}" aria-selected="{{ $key === 'issue' ? 'true' : 'false' }}" aria-controls="car-panel-{{ $key }}" tabindex="{{ $key === 'issue' ? '0' : '-1' }}">{{ $label }}</button>
            @endforeach
        </div>
        <section id="car-panel-issue" role="tabpanel" aria-labelledby="car-tab-issue" data-car-panel="issue">
            <h3>Issue and Responsibility</h3><p class="car-muted">All 14 FM-USTP-009-2 columns are represented across overview and verification.</p>
            <dl class="car-assignment-fields">
                @foreach(['Name of Person Submitting CAR', 'Issue', 'Assigned To', 'Target Closed-Out Date'] as $label)
                <div data-car-field="{{ $label }}"><dt>{{ $label }}</dt><dd>{{ $detail['official'][$label] }}</dd></div>
                @endforeach
            </dl>
        </section>
        <section id="car-panel-verification" role="tabpanel" aria-labelledby="car-tab-verification" data-car-panel="verification" hidden>
            <h3>Verification</h3><p class="car-muted">Verification results: {{ $detail['verification'] }}. Results are recorded separately from workflow status. Verification and closure require explicit permissions in the assigned auditor workflow.</p>
        </section>
        <section class="car-result-section" data-car-panel="issue verification" aria-label="Recorded verification results">
            <h3>Results — Pass / Fail / Action</h3>
            <dl class="car-result-fields">@foreach(['Pass'=>'pass','Fail'=>'fail','Action'=>'action'] as $label=>$class)<div class="car-result-{{ $class }}" data-car-field="Results — {{ $label }}"><dt>Results — {{ $label }}</dt><dd>{{ $detail['official']['Results — '.$label] }}</dd></div>@endforeach</dl>
            <dl class="car-assignment-fields car-remarks"><div data-car-field="Remarks"><dt>Remarks</dt><dd>{{ $detail['official']['Remarks'] }}</dd></div></dl>
        </section>
        <section id="car-panel-history" role="tabpanel" aria-labelledby="car-tab-history" data-car-panel="history" hidden>
            <h3>Corrective Evidence</h3>
            @forelse($detail['responses'] as $response)<article class="car-event"><strong>{{ $response['actor'] ?? 'Not recorded' }}</strong><p>{{ $response['method'] }} · {{ $response['reference'] }}</p><p>{{ $response['notes'] }}</p><p>{{ $response['availability'] }}</p><time>{{ \App\Services\AtlWorkspace::date($response['at']) }}</time>@if($response['review'])<p>{{ $response['review']['feedback'] }}</p><time>{{ \App\Services\AtlWorkspace::date($response['review']['at']) }}</time>@endif</article>@empty<p>No corrective evidence responses recorded.</p>@endforelse
            <h4>Permitted linked files</h4>@forelse($detail['evidence'] as $file)<p>@if($file['canDownload'])<a href="/api/v1/documents/{{ $file['id'] }}/download">{{ $file['name'] }}</a>@else{{ $file['name'] }} · Download not authorized @endif</p>@empty<p>No linked files available within your archive permissions.</p>@endforelse
            <h3>CAR Activity History</h3>
            @forelse($detail['timeline'] as $event)<article class="car-event"><strong>{{ $event['label'] }}</strong><p>{{ $event['remarks'] ?? 'No remarks recorded' }}</p><small>{{ $event['actor'] ?? 'Not recorded' }} · {{ $event['role'] }} · {{ \App\Services\AtlWorkspace::date($event['at']) }}</small></article>@empty<p>No CAR status history recorded.</p>@endforelse
        </section>
    </div>
</section>
