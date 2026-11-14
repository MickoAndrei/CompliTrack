@if($preparing)
<dialog id="workspace-question-dialog" class="aw-dialog" aria-labelledby="aw-question-title" @cancel.prevent="closeModal($event)">
    <div class="aw-dialog-heading"><h3 id="aw-question-title" x-text="editing < 0 ? 'Add Checklist Item' : 'Edit Checklist Item'"></h3><button type="button" class="aw-button" aria-label="Close checklist item form" @click="closeModal($event)">✕</button></div>
    <form method="POST" class="aw-form">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="action" :value="editing < 0 ? 'add' : 'edit'"><input type="hidden" name="item" :value="editing">
        <label>Check item / question<textarea name="question" required maxlength="2000" x-model="question" autofocus></textarea></label><label>Applicable criterion reference<input name="reference" maxlength="500" x-model="reference"></label>
        <div class="aw-actions"><button type="button" class="aw-button" @click="closeModal($event)">Cancel</button><button class="aw-button aw-button-primary">Save checklist item</button></div>
    </form>
</dialog>
@endif
@if($editable)
<dialog id="workspace-evidence-dialog" class="aw-dialog" aria-labelledby="aw-evidence-title" @cancel.prevent="closeModal($event)">
    <div class="aw-dialog-heading"><h3 id="aw-evidence-title">Add Evidence Review</h3><button type="button" class="aw-button" aria-label="Close evidence review form" @click="closeModal($event)">✕</button></div>
    <form method="POST" class="aw-form">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="action" value="evidence"><input type="hidden" name="item" :value="evidenceTarget">
        <p>Linked to check item <strong x-text="evidenceTarget + 1"></strong>. Record only permitted facts; omit restricted titles and identifiers. No copy or upload is required.</p>
        <label>Evidence method<select name="method" x-model="method" autofocus><option value="digital">Digital</option><option value="physical-original">Physical original</option><option value="restricted">Restricted / controlled</option><option value="interview-process">Interview / observed process</option></select></label>
        <label>Permitted document reference (optional)<input name="reference" maxlength="500" value="{{ old('action') === 'evidence' ? old('reference') : '' }}"></label>
        <label>Verification summary<textarea name="notes" required maxlength="5000">{{ old('action') === 'evidence' ? old('notes') : '' }}</textarea></label>
        <label x-show="method === 'physical-original'">Physical signature verification<select name="signature"><option value="not-required">Not required</option><option value="verified" @selected(old('signature') === 'verified')>Verified</option><option value="missing-incomplete" @selected(old('signature') === 'missing-incomplete')>Missing / incomplete</option><option value="not-verified" @selected(old('signature') === 'not-verified')>Not yet verified</option></select></label>
        <div class="aw-actions"><button type="button" class="aw-button" @click="closeModal($event)">Cancel</button><button class="aw-button aw-button-primary">Record evidence review</button></div>
    </form>
</dialog>
<dialog id="workspace-interview-dialog" class="aw-dialog" aria-labelledby="aw-interview-title" @cancel.prevent="closeModal($event)">
    <div class="aw-dialog-heading"><h3 id="aw-interview-title">Add Interview</h3><button type="button" class="aw-button" aria-label="Close interview form" @click="closeModal($event)">✕</button></div>
    <form method="POST" class="aw-form">@csrf<input type="hidden" name="version" value="{{ $audit->version }}"><input type="hidden" name="action" value="interview">
        <label>Auditee<select name="auditeeId" required autofocus><option value="">Select departmental auditee</option>@foreach($auditees as $person)<option value="{{ $person->getKey() }}" @selected(old('action') === 'interview' && old('auditeeId') === (string) $person->getKey())>{{ $person->fullName }}</option>@endforeach</select></label>
        @foreach(['process' => 'Process / area', 'topics' => 'Questions / topics', 'responses' => 'Auditee responses', 'notes' => 'Factual notes (optional)'] as $field => $label)<label>{{ $label }}<textarea name="{{ $field }}" @required($field !== 'notes') maxlength="{{ $field === 'process' ? 500 : ($field === 'topics' ? 3000 : 5000) }}">{{ old('action') === 'interview' ? old($field) : '' }}</textarea></label>@endforeach
        <div class="aw-actions"><button type="button" class="aw-button" @click="closeModal($event)">Cancel</button><button class="aw-button aw-button-primary">Save interview</button></div>
    </form>
</dialog>
@endif
