@extends('layouts.app')
@section('title', 'Settings')
@section('heading', 'System Settings')
@section('content')
<section class="app-view-panel active app-settings-page">
    <header class="settings-page-header">
        <h2 class="font-heading">System settings</h2>
        <p>Protected configuration is maintained by the deployment administrator.</p>
    </header>
    <article class="settings-info-card">
        <span class="settings-info-icon"><i data-lucide="shield-check" aria-hidden="true"></i></span>
        <div class="settings-info-copy">
            <h3>Configuration is managed securely</h3>
            <p>Encryption keys, storage configuration, and operational settings are not exposed in the browser.</p>
        </div>
    </article>
</section>
@endsection
