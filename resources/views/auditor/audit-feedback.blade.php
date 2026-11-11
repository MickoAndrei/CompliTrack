@if(session('status'))<p x-page-notice role="status" class="rounded border border-green-500 bg-white p-3">{{ session('status') }}</p>@endif
@if($errors->any())<div x-page-notice role="alert" class="rounded border border-red-500 bg-white p-3"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
