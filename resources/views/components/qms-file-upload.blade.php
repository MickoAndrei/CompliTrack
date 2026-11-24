<template x-if="folderUpload">
    <div>
        <header><div><p class="qms-eyebrow">Authorized archive record</p><h2 id="qms-file-upload-title">Upload files</h2></div><button type="button" class="qms-close" :disabled="saving" @click="$refs.upload.close()" aria-label="Close upload files">&times;</button></header>
        <div class="qms-dialog-body">
            <p class="qms-alert" role="alert" x-show="error" x-text="error"></p>
            <p class="qms-alert" role="alert" x-show="uploadChecksError"><span x-text="uploadChecksError"></span> <button type="button" class="qms-button" :disabled="uploadChecksLoading" @click="loadUploadChecks()">Retry</button></p>
            <p class="qms-scope-note">Upload to <strong x-text="uploadDestination?.name"></strong></p>
            <div class="qms-form-grid">
                <label class="qms-full">Document title<input x-model="form.title" required maxlength="255"></label>
                <label class="qms-full"><span x-text="uploadDestination?.correctiveOnly ? 'Related finding' : 'Related finding (optional)'"></span><select x-model="form.checkId" :required="uploadDestination?.correctiveOnly" :disabled="uploadChecksLoading || saving"><option value="" x-text="uploadChecksLoading ? 'Loading findings…' : uploadDestination?.correctiveOnly ? 'Select an open finding' : 'No related finding'"></option><template x-for="check in uploadChecks" :key="check._id"><option :value="check._id" x-text="`${check.checkCode} · ${check.referenceStandard}`"></option></template></select><small>Link corrective evidence to an open finding. Uploading does not close the CAR.</small></label>
                <div class="qms-full corrective-upload-file-field">
                    <input x-ref="folderFile" type="file" accept=".pdf,.docx,.xlsx,.pptx,.png,.jpg,.jpeg" class="sr-only" tabindex="-1" aria-label="Choose file" :disabled="saving" @change="selectUploadFile($event.target.files[0])">
                    <button type="button" class="corrective-upload-dropzone" :class="{'is-dragging':uploadDragging,'has-file':uploadFile}" :disabled="saving" @click="$refs.folderFile.click()" @dragover.prevent="uploadDragging = !saving" @dragleave.prevent="uploadDragging = false" @drop.prevent="selectUploadFile($event.dataTransfer?.files?.[0])">
                        <span class="corrective-upload-file-content"><span class="corrective-upload-drop-title" x-text="uploadFile ? uploadFile.name : 'Drop a file here or browse'"></span><span class="corrective-upload-hint">PDF, Word, Excel, PowerPoint, PNG, or JPEG · maximum 20 MB</span></span>
                    </button>
                    <button type="button" class="corrective-upload-remove" x-show="uploadFile" :disabled="saving" @click="selectUploadFile(null); $refs.folderFile.value = ''">Remove selected file</button>
                </div>
                <p class="qms-full qms-scope-note">Encrypted storage · AES-256-GCM</p>
                <label class="qms-full qms-checkbox"><input type="checkbox" x-model="form.copyAuthorized" required :disabled="saving">I have permission to store this file, including any protected QMS records.</label>
            </div>
        </div>
        <div class="qms-upload-progress" x-show="saving" role="status"><progress max="100" :value="uploadProgress"></progress><span x-text="uploadProgress &lt; 100 ? 'Uploading ' + uploadProgress + '%' : 'Securing and saving file…'"></span></div>
        <footer><button type="button" class="qms-button" :disabled="saving" @click="$refs.upload.close()">Cancel</button><button type="submit" class="qms-button qms-gold" :disabled="saving || !uploadFile || uploadChecksLoading" x-text="saving ? 'Uploading…' : 'Upload file'"></button></footer>
    </div>
</template>
