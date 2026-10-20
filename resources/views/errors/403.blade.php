@extends('layouts.app')
@section('title', 'Access denied')
@section('content')
<main class="flex min-h-screen items-center justify-center bg-ustp-light px-4">
    <section class="max-w-lg rounded-3xl bg-white p-10 text-center shadow-sm">
        <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-error/10 text-error"><i data-lucide="shield-x" class="size-8"></i></span>
        <p class="mt-6 text-sm font-bold uppercase tracking-widest text-error">403 · Forbidden</p>
        <h1 class="mt-2 font-heading text-3xl font-bold text-ustp-navy">Access denied</h1>
        <p class="mt-3 text-sm leading-6 text-ustp-muted">You do not have permission (roleId mismatch).</p>
        <a href="/login" class="mt-7 inline-flex rounded-xl bg-ustp-navy px-5 py-3 font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy">Return to sign in</a>
    </section>
</main>
@endsection