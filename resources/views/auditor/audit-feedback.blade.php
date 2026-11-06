@if(session('status'))<p role="status" class="rounded border border-green-500 bg-white p-3">{{ session('status') }}</p>@endif
@if($errors->any())<div role="alert" class="rounded border border-red-500 bg-white p-3"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
