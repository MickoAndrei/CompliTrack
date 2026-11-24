@props(['paginator'])
@once
    @push('styles')
        <link rel="stylesheet" href="{{ asset('css/pagination.css') }}?v={{ filemtime(public_path('css/pagination.css')) }}">
    @endpush
@endonce
<label class="app-page-size">Rows
    <select aria-label="Rows per page" onchange="window.location.assign(this.value)">
        @foreach(collect([5, 10, 20, 50, 100, $paginator->perPage()])->unique()->sort() as $size)
            <option value="{{ request()->fullUrlWithQuery(['perPage' => $size, 'page' => 1]) }}" @selected($paginator->perPage() === $size)>{{ $size }}</option>
        @endforeach
    </select>
</label>
