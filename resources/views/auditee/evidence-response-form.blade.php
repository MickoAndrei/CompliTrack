@php($draft = $entry['draft'] ?? [])
<form method="POST" action="{{ $responseAction }}" enctype="multipart/form-data" class="workflow-form" x-data="{ method: @js($draft['method'] ?? 'digital-reference') }">
    @csrf
    @if(isset($audit) || isset($version))<input type="hidden" name="version" value="{{ $version ?? $audit->version }}">@endif
    <input type="hidden" name="submissionKey" value="{{ (string) \Illuminate\Support\Str::uuid() }}">
    <label>Evidence method<select name="method" x-model="method"><option value="digital-reference">Digital reference (no copy)</option><option value="physical-original">Physical original</option><option value="interview-process">Interview / process demonstration</option>@if($entry['allowUpload'] ?? false)<option value="file">Authorized file upload</option>@endif</select></label>
    <label>Permitted reference / location (optional)<input name="reference" maxlength="500" value="{{ $draft['reference'] ?? '' }}"></label>
    <label>Factual description / what can be inspected<textarea name="notes" required maxlength="5000">{{ $draft['notes'] ?? '' }}</textarea></label>
    <label x-show="method === 'physical-original'">Original available for inspection?<select name="availability" :disabled="method !== 'physical-original'"><option value="available">Available</option><option value="to-arrange" @selected(($draft['availability'] ?? '') === 'to-arrange')>To arrange</option></select></label>
    @if($entry['allowUpload'] ?? false)
    <div x-show="method === 'file'" class="workflow-stack">
        <label>Authorized file (PDF, DOCX, PNG, JPG; maximum 20 MB)<input type="file" name="file" accept=".pdf,.docx,.png,.jpg,.jpeg" :required="method === 'file' && {{ empty($draft['archiveId']) ? 'true' : 'false' }}" :disabled="method !== 'file'"></label>
        @if(!empty($draft['archiveId']))<p>Your saved draft file will be reused unless you choose a replacement.</p>@endif
        <label><input type="checkbox" name="copyAuthorized" value="1" :required="method === 'file'" :disabled="method !== 'file'"> The document owner permits this copy to be stored in CompliTrack.</label>
    </div>
    @endif
    <p class="workflow-muted">Record only permitted metadata. Do not upload protected QMS documents without authorization. Availability is not signature verification; the auditor verifies the original separately.</p>
    <div class="workflow-actions">@if($canDraft ?? false)<button name="action" value="draft" class="workflow-secondary">Save draft</button>@endif<button name="action" value="submit" class="auditor-gold-button">Submit evidence</button></div>
</form>
