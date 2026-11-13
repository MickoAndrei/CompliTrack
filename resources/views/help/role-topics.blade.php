@foreach($roleGuide['topics'] as $id => $topic)
<section id="{{ $id }}" data-help-topic="{{ $topic['label'] }}" x-show="selected === '{{ $id }}'" aria-labelledby="{{ $id }}-title">
    <span class="help-kicker">{{ $roleGuide['name'] }} GUIDANCE</span>
    <h2 id="{{ $id }}-title">{{ $topic['label'] }}</h2>
    <p class="help-intro">{{ $topic['intro'] }}</p>
    <ol class="help-steps">
        @foreach($topic['steps'] as [$title, $text])
        <li><h3>{{ $title }}</h3><p>{{ $text }}</p></li>
        @endforeach
    </ol>
    @if(isset($topic['note']))<p class="help-note">{{ $topic['note'] }}</p>@endif
    @if($id === 'standards')
    <div class="help-resource-empty"><h3>No approved external resources listed</h3><p>Ask the audit coordinator for institution-approved references. No institutional retention period, filename rule, or classification scheme is imposed here.</p></div>
    @endif
    <a class="help-primary-link" href="{{ $topic['href'] }}">{{ $topic['action'] }} <span aria-hidden="true">→</span></a>
</section>
@endforeach
