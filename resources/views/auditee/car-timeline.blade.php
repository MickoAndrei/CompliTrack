@php($timeline = $carTimeline ?? \App\Support\CarTimeline::forCheck($check))
<section class="car-timeline-section" aria-labelledby="car-timeline-title">
    <h3 id="car-timeline-title">Status Timeline</h3>
    @if(!$hasCar)
        <p class="car-timeline-empty">No corrective action (CAR) is recorded for this audit result.</p>
    @else
        <p class="workflow-muted">Current CAR status: <strong>{{ \App\Support\CarTimeline::label($check->correctiveStatus) }}</strong>. Only an authorized auditor can officially close this CAR.</p>
        @if(empty($check->statusHistory))<p class="workflow-muted">No status history recorded. The current status is shown without an assumed date.</p>@endif
        <ol class="car-timeline" aria-label="Corrective action status history">
            @foreach($timeline as $event)
            <li class="car-timeline-event is-{{ $event['state'] }}" data-car-event="{{ $event['kind'] }}" data-car-status="{{ $event['status'] }}" @if($event['state'] === 'current') aria-current="step" @endif>
                <span class="car-timeline-marker" aria-hidden="true">@if($event['state'] === 'completed' || ($event['state'] === 'current' && $event['status'] === 'closed'))<svg class="car-timeline-check" viewBox="0 0 20 20" fill="none"><path d="m4 10 4 4 8-8" pathLength="1" /></svg>@endif</span>
                <div class="car-timeline-content"><div class="car-timeline-heading"><strong>{{ $event['label'] }}</strong><span class="car-timeline-state">{{ $event['state'] === 'completed' ? 'Recorded' : ucfirst($event['state']) }}</span></div>
                    @if($event['at'])<time datetime="{{ $event['at']->toIso8601String() }}">{{ $event['at']->format('M j, Y · g:i:s A') }} UTC</time>@elseif($event['state'] !== 'upcoming')<span class="car-timeline-meta">Timestamp not recorded</span>@endif
                    <span class="car-timeline-meta">{{ $event['role'] ? 'Responsible role: '.$event['role'] : 'Responsible role not recorded' }}</span>
                    @if($event['kind'] === 'evidence')<p class="workflow-muted">Evidence submitted for review; this does not close the CAR.</p>@endif
                    @if($event['state'] === 'upcoming')<p class="workflow-muted">Upcoming stage. No completion date recorded.</p>@endif
                </div>
            </li>
            @endforeach
        </ol>
        <p class="workflow-muted">Repeated entries remain separate. Undated entries retain their recorded order; past closure does not mean a reopened CAR is currently closed.</p>
    @endif
</section>
