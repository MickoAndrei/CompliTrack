@extends('layouts.app')
@section('title', 'Auditor Dashboard')
@section('heading', 'Auditor Dashboard')
@section('content')
<section class="auditor-dashboard ad-v2" x-data="auditorOverview()" x-init="load()" :aria-busy="loading">
    <div x-page-notice x-cloak x-show="error" class="auditor-load-error" role="alert"><span x-text="error"></span><button @click="load()">Retry</button></div>
    <div class="ad-kpis">
        @foreach([['Assigned Audits','assignedAudits','clipboard-list','blue'],['Pending Checks','pendingChecks','clock-3','amber'],['Open CARs','openCars','triangle-alert','red'],['Closed CARs','closedCars','check-check','green']] as [$label,$key,$icon,$tone])
        <article class="ad-card ad-kpi"><div><span class="ad-muted">{{ $label }}</span><strong :class="{'ad-loading-number':loading}" x-text="loading || error ? '—' : count(stats.{{ $key }})"></strong></div><span class="ad-icon ad-{{ $tone }}"><i data-lucide="{{ $icon }}" aria-hidden="true"></i></span></article>
        @endforeach
    </div>
    <div class="ad-card ad-progress-card">
        <div class="ad-heading"><h2>Audit Assignment Progress</h2><a class="ad-link" href="/audits">View assigned audits <i data-lucide="arrow-right" aria-hidden="true"></i></a></div>
        <div x-show="loading" class="ad-skeleton ad-progress-skeleton" aria-hidden="true"></div>
        <div x-cloak x-show="!loading && !error">
            <div class="ad-between ad-muted"><span x-text="`${assignmentTotal} assigned audits`"></span><strong class="ad-success" x-text="completion === '—' ? 'Not yet assigned' : completion + ' completed'"></strong></div>
            <div class="ad-segments" role="img" :aria-label="stages.map(s=>`${s.label}: ${s.count}`).join(', ')"><template x-for="s in stages" :key="s.key"><span x-show="s.count > 0" :style="`flex:${s.count};background:${s.color}`" :title="`${s.label}: ${s.count}`" x-text="s.count"></span></template></div>
            <div class="ad-stage-legend"><template x-for="s in stages" :key="s.key"><span><i class="ad-dot" :style="`background:${s.color}`"></i><span x-text="`${s.label} (${s.count})`"></span></span></template></div>
        </div>
    </div>
    <div class="ad-grid">
        @foreach([['N/C/O Overview','My evaluated checklist items','results','evaluated','Evaluated','compliance','Overall compliance rate'],['Corrective Action Overview','Corrective actions under my responsibility','cars','carTotal','Total CARs','closure','CAR closure rate']] as [$title,$subtitle,$parts,$total,$center,$rate,$rateLabel])
        <article class="ad-card ad-chart-card">
            <h2>{{ $title }}</h2><span class="ad-muted">{{ $subtitle }}</span>
            <div x-show="loading" class="ad-chart ad-chart-skeleton" aria-hidden="true"><span class="ad-skeleton ad-ring"></span><div class="ad-legend"><span class="ad-skeleton"></span><span class="ad-skeleton"></span><span class="ad-skeleton"></span></div></div>
            <div class="ad-chart" x-cloak x-show="!loading && !error">
                <div class="ad-ring" :style="`background:${ring({{ $parts }})}`" role="img" :aria-label="{{ $parts }}.map(s=>`${s.label}: ${s.count}`).join(', ')"><div class="ad-ring-center"><strong x-text="{{ $total }}"></strong><small>{{ $center }}</small></div></div>
                <div class="ad-legend"><template x-for="s in {{ $parts }}" :key="s.key"><div><i class="ad-dot" :style="`background:${s.color}`"></i><span x-text="s.label"></span><strong x-text="s.count"></strong></div></template></div>
            </div>
            <div class="ad-chart-footer"><span class="ad-muted">{{ $rateLabel }}</span><strong class="ad-success" x-text="loading || error ? '—' : {{ $rate }}"></strong></div>
        </article>
        @endforeach
        <article class="ad-card ad-compact">
            <div class="ad-heading"><h2>Requires Attention</h2><i data-lucide="triangle-alert" aria-hidden="true"></i></div>
            <div x-show="loading" aria-hidden="true">@for($i=0;$i<4;$i++)<div class="ad-skeleton ad-row-skeleton"></div>@endfor</div>
            <div x-cloak x-show="!loading && !error"><template x-for="item in attention" :key="item.label"><a class="ad-small-row" :href="item.url"><span class="ad-icon" :class="`ad-${item.tone}`"><i :data-lucide="item.icon" aria-hidden="true"></i></span><span class="ad-row-copy" x-text="item.label"></span><strong :class="`ad-text-${item.tone}`" x-text="item.count"></strong><i class="ad-chevron" data-lucide="chevron-right" aria-hidden="true"></i></a></template></div>
        </article>
        <article class="ad-card ad-compact">
            <div class="ad-heading"><h2>Recent Activity</h2><i data-lucide="activity" aria-hidden="true"></i></div>
            <div x-show="loading" aria-hidden="true">@for($i=0;$i<4;$i++)<div class="ad-skeleton ad-row-skeleton"></div>@endfor</div>
            <div x-cloak x-show="!loading && !error"><p class="ad-muted" x-show="!changes.length">No recent activity yet.</p><template x-for="change in changes.slice(0,4)" :key="change.key"><a class="ad-small-row" :href="change.url" :title="`${change.code} ${change.label} · ${new Date(change.at).toLocaleString()}`"><span class="ad-icon" :class="`ad-${tone(change.status)}`"><i data-lucide="activity" aria-hidden="true"></i></span><span class="ad-row-copy" x-text="`${change.code} ${change.label}`"></span><time class="ad-muted" :datetime="change.at" x-text="activityTime(change.at)"></time><i class="ad-chevron" data-lucide="chevron-right" aria-hidden="true"></i></a></template></div>
        </article>
    </div>
    <section class="ad-card ad-table-card">
        <div class="ad-heading"><h2>My Assigned Audits</h2><a class="ad-link" href="/audits">View all <i data-lucide="arrow-right" aria-hidden="true"></i></a></div>
        <div x-show="loading" class="ad-table-skeleton" aria-hidden="true">@for($i=0;$i<5;$i++)<div class="ad-skeleton ad-row-skeleton"></div>@endfor</div>
        <p class="ad-muted ad-empty" x-cloak x-show="!loading && !error && !assignments.length">No assigned audits yet. Your audits will appear after an authorized assignment.</p>
        <div class="ad-table-scroll" x-cloak x-show="!loading && !error && assignments.length"><table><thead><tr><th scope="col">Audit / Department or Office</th><th scope="col">Status</th><th scope="col">Progress</th><th scope="col">Action</th></tr></thead><tbody>
            <template x-for="a in assignments" :key="a.id"><tr><th scope="row"><strong x-text="a.code"></strong><small x-text="a.title" :title="a.title"></small><small x-text="a.department"></small></th><td><span class="ad-pill" :class="`ad-${tone(a.status)}`" x-text="statusLabel(a.status)"></span></td><td><div class="ad-table-progress"><span x-text="progress(a)+'%'"></span><div class="ad-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" :aria-valuenow="progress(a)" :aria-label="`${a.code} checklist progress`"><span :style="`width:${progress(a)}%;background:${stages.find(s=>s.key===a.status)?.color || '#64748b'}`"></span></div></div></td><td><a class="ad-action" :class="{'ad-primary': ['draft','ready','in_progress','returned'].includes(a.status)}" :href="a.url || `/audits/${a.id}`" x-text="actionLabel(a.status)"></a></td></tr></template>
        </tbody></table></div>
        <div class="ad-table-footer"><a class="ad-link" href="/auditor/historical-records">Historical Compliance Records</a><a class="ad-link" href="/auditor/corrective-actions">Corrective Actions</a></div>
    </section>
</section>
@endsection