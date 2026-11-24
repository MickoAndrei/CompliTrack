@extends('layouts.app')
@section('title', 'Help & Standards')
@section('heading', 'Help & Standards')
@section('subtitle', 'Guidance for preparing evidence and responding to audits')
@push('styles')
<link rel="stylesheet" href="{{ asset('css/auditee-help.css') }}?v={{ filemtime(public_path('css/auditee-help.css')) }}">
@endpush
@section('content')
@php
    $helpRole = (int) auth()->user()->roleId;
    $roleGuide = config('help.'.$helpRole);
    $helpTopics = $helpRole === 3 ? ['prepare' => ['Prepare for Audit', 'clipboard-list'], 'present' => ['Present Evidence', 'file-check'], 'respond' => ['Respond to Findings', 'clipboard-check'], 'nco' => ['Understanding N/C/O', 'circle-check'], 'standards' => ['Reference Standards', 'book-open']] : collect($roleGuide['topics'])->map(fn ($topic) => [$topic['label'], $topic['icon']])->all();
@endphp
<div class="help-center" x-data="auditeeHelp" x-cloak>
    <header class="help-hero">
        <span class="help-eyebrow">{{ [0 => 'SUPER ADMIN', 1 => 'ADMIN / ATL', 2 => 'AUDITOR', 3 => 'AUDITEE'][$helpRole] }} HELP CENTER</span>
        <h2>How can we help?</h2>
        <p>{{ $helpRole === 3 ? 'Find guidance for your next audit, from preparing records to following up on findings.' : $roleGuide['intro'] }}</p>
        <label for="help-search" class="help-search-label">Search help topics</label>
        <div class="help-search">
            <i data-lucide="search" aria-hidden="true"></i>
            <input id="help-search" x-ref="search" type="search" x-model="query" placeholder="Search by topic, task, or keyword" aria-describedby="help-search-hint" aria-controls="help-topic-nav help-content" maxlength="160">
            <button type="button" x-show="query" @click="clearSearch()" aria-label="Clear help search">Clear</button>
        </div>
        <p id="help-search-hint">Search across all five topics. Matching topics appear below.</p>
    </header>
    <div class="help-layout">
        <aside class="help-topics" aria-label="Help topic selection">
            <h2>Explore topics</h2>
            <p class="help-result-count" role="status" aria-live="polite" aria-atomic="true" x-text="query.trim() ? filteredTopics.length + ' matching ' + (filteredTopics.length === 1 ? 'topic' : 'topics') : '5 topics to guide you'">5 topics to guide you</p>
            <label class="help-mobile" for="help-topic-select">Choose a topic</label>
            <select class="help-mobile" id="help-topic-select" x-model="selected" @change="choose($event.target.value)" :disabled="!filteredTopics.length" aria-controls="help-content">
                <option value="" x-show="!filteredTopics.length">No topics available</option>
                <template x-for="topic in filteredTopics" :key="topic.id"><option :value="topic.id" x-text="topic.label"></option></template>
            </select>
            <nav id="help-topic-nav" class="help-desktop" aria-label="Help topics">
                @foreach($helpTopics as $id => [$label, $icon])
                <button type="button" x-show="matches('{{ $id }}')" @click="choose('{{ $id }}')" :aria-pressed="selected === '{{ $id }}'" :class="{ 'is-active': selected === '{{ $id }}' }" aria-controls="{{ $id }}">
                    <i data-lucide="{{ $icon }}" aria-hidden="true"></i><span>{{ $label }}</span><span class="help-nav-arrow" aria-hidden="true">→</span>
                </button>
                @endforeach
            </nav>
            <p class="help-topic-empty" x-show="!filteredTopics.length">No topics to display. Clear your search to explore the Help Center.</p>
        </aside>
        <div id="help-content" class="help-content">
            <section class="help-empty" x-show="!filteredTopics.length" aria-labelledby="help-empty-title">
                <i data-lucide="search" aria-hidden="true"></i>
                <h2 id="help-empty-title" x-text="query.trim() ? 'No matching help topics' : 'No help topics available'">No matching help topics</h2>
                <p>Try a broader term such as “evidence” or “findings”, or clear your search to see all topics.</p>
                <button class="help-link-button" type="button" @click="clearSearch()">Clear search</button>
            </section>
            @if ($helpRole === 3)
                @include('help.auditee-topics')
            @else
                @include('help.role-topics')
            @endif
        </div>
    </div>
    <footer class="help-footer"><i data-lucide="circle-help" aria-hidden="true"></i><div><h2>Need additional help?</h2><p>Contact your designated audit coordinator or authorized department representative.</p></div></footer>
</div>
<noscript><div class="workflow-card"><h2>Help Center requires JavaScript</h2><p>Enable JavaScript to search and choose a help topic. Your authorized pages remain available from the main navigation.</p></div></noscript>
@endsection
