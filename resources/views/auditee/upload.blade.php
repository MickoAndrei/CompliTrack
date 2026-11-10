@extends('layouts.app')
@section('title', 'Provide Evidence')
@section('heading', 'Provide Evidence')
@section('content')
<div class="corrective-upload-page">
<nav class="workflow-actions" aria-label="Evidence purpose"><a href="/auditee/evidence-requests">During Audit</a><a href="/auditee/upload" aria-current="page">Corrective Evidence</a></nav>
<section x-data="evidenceUploadPage()" data-evidence-upload class="corrective-upload" aria-labelledby="corrective-upload-title">
    <form @submit.prevent="submit()" class="corrective-upload-card" :aria-busy="loading || saving">
        <header class="corrective-upload-header"><h2 id="corrective-upload-title">Upload corrective evidence</h2><p>Attach authorized evidence to an open non-compliant finding.</p></header>
        <div x-page-notice x-cloak x-show="error || message" class="corrective-upload-feedback" :class="error ? 'is-error' : 'is-success'" :role="error ? 'alert' : 'status'" aria-live="polite"><span x-text="error || message"></span></div>
        <div class="corrective-upload-field"><label for="corrective-upload-check">Related finding</label><select id="corrective-upload-check" x-model="selectedCheckId" required :disabled="loading || saving"><option value="" x-text="loading ? 'Loading assigned findings…' : checks.length ? 'Select a non-compliant finding' : 'No open non-compliant findings available'">Select a non-compliant finding</option><template x-for="check in checks" :key="check._id"><option :value="check._id" x-text="`${check.checkCode} · ${check.referenceStandard}`"></option></template></select></div>
        <div class="corrective-upload-field"><label for="corrective-upload-reference">Reference standard</label><input id="corrective-upload-reference" type="text" :value="selectedReferenceStandard" readonly aria-readonly="true" placeholder="Select a finding first"></div>
        <div class="corrective-upload-file-field">
            <input id="corrective-upload-file" x-ref="fileInput" type="file" accept=".pdf,.docx,.png,.jpg,.jpeg" @change="selectFile($event.target.files[0])" :disabled="loading || saving" class="sr-only" tabindex="-1"><label for="corrective-upload-file" class="sr-only">Choose corrective evidence file</label>
            <button type="button" class="corrective-upload-dropzone" :class="{ 'is-dragging': dragging, 'has-file': file }" :disabled="loading || saving" aria-label="Choose corrective evidence file or drop a file here" aria-describedby="corrective-upload-hint" @click="$refs.fileInput.click()" @dragover.prevent="dragging = !saving && !loading" @dragleave.prevent="if (!$el.contains($event.relatedTarget)) dragging = false" @drop.prevent="handleDrop($event)">
                <span x-show="!file" class="corrective-upload-file-content"><i data-lucide="cloud-upload" aria-hidden="true"></i><span class="corrective-upload-drop-title">Drop a file here or browse</span><span id="corrective-upload-hint" class="corrective-upload-hint">PDF, DOCX, PNG, JPG — maximum 20 MB</span></span>
                <span x-cloak x-show="file" class="corrective-upload-file-content" aria-live="polite"><i data-lucide="file-check-2" aria-hidden="true"></i><span class="corrective-upload-file-name" x-text="file?.name"></span><span class="corrective-upload-hint" x-text="file ? `${formatFileSize(file.size)} · Click to replace` : ''"></span></span>
            </button>
            <button x-cloak x-show="file" type="button" @click="clearFile()" :disabled="saving" class="corrective-upload-remove">Remove selected file</button>
        </div>
        <div class="corrective-upload-encryption"><i data-lucide="lock-keyhole" aria-hidden="true"></i><span>Encrypted storage · AES-256-GCM</span></div>
        <label class="corrective-upload-permission"><input type="checkbox" required :disabled="saving"><span>I have permission to store this file, including any protected QMS records.</span></label>
        <p class="corrective-upload-help">References or originals? <a href="/auditee/my-findings?actionable=1">My Audit Results</a>. Uploading does not close the CAR.</p>
        <div class="corrective-upload-actions"><button type="button" @click="cancelUpload()" :disabled="saving" class="workflow-secondary">Cancel</button><button :disabled="loading || saving || !file || !selectedCheckId" type="submit" class="auditor-gold-button"><span x-text="saving ? 'Uploading…' : 'Submit Evidence'">Submit Evidence</span></button></div>
    </form>
</section>
</div>
@endsection
