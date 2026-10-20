@extends('layouts.app')
@section('title', 'Page not found')
@section('content')
<main class="flex min-h-screen items-center justify-center bg-ustp-light px-4">
    <section class="max-w-lg rounded-3xl bg-white p-10 text-center shadow-sm">
        <span class="mx-auto flex size-16 items-center justify-center rounded-full bg-ustp-navy/5 text-ustp-navy"><i data-lucide="file-question" class="size-8"></i></span>
        <p class="mt-6 text-sm font-bold uppercase tracking-widest text-ustp-muted">404 · Not found</p>
        <h1 class="mt-2 font-heading text-3xl font-bold text-ustp-navy">We can’t find that page</h1>
        <a href="/login" class="mt-7 inline-flex rounded-xl bg-ustp-navy px-5 py-3 font-semibold text-white hover:bg-ustp-gold hover:text-ustp-navy">Go to sign in</a>
    </section>
</main>
@endsection