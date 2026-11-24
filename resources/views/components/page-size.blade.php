@props(['model' => 'perPage', 'change' => 'load(1)', 'sizes' => [5, 10, 20, 50, 100]])
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/pagination.css') }}?v={{ filemtime(public_path('css/pagination.css')) }}">
    @endpush
@endonce
<label class="app-page-size">Rows
    <select x-model.number="{{ $model }}" @change="{{ $change }}" :disabled="loading" aria-label="Rows per page">
        @foreach($sizes as $size)<option value="{{ $size }}">{{ $size }}</option>@endforeach
    </select>
</label>
