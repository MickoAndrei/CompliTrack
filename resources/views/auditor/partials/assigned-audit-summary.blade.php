<div class="ct-metric-grid ct-metric-grid-three" data-my-audit-summary aria-label="Assignment summary">
@foreach([['total','Assigned',$summary['total'],'Authorized assignments'],['toSubmit','To submit',max(0,$summary['total']-$summary['awaitingReview']-$summary['completed']),'Reports outstanding'],['submitted','Submitted',$summary['awaitingReview']+$summary['completed'],'Submitted or accepted']] as [$key,$label,$value,$detail])
<article class="ct-metric"><p>{{ $label }}</p><strong data-metric="{{ $key }}">{{ $value }}</strong><span>{{ $detail }}</span></article>@endforeach</div>
