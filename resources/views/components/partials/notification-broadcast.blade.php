<div x-cloak x-show="broadcastModalOpen" :class="{'broadcast-is-open': broadcastModalOpen}" class="attention-overlay" @keydown.escape.window="broadcastModalOpen = false" @click.self="broadcastModalOpen = false" role="dialog" aria-modal="true" aria-labelledby="broadcast-heading">
    <form class="attention-broadcast" @submit.prevent="sendBroadcast()">
        <header class="broadcast-header">
            <div><span class="broadcast-eyebrow">ANNOUNCEMENT</span><h2 id="broadcast-heading">Broadcast notification</h2></div>
            <button type="button" class="broadcast-close" @click="broadcastModalOpen = false" aria-label="Close broadcast"><i data-lucide="x" aria-hidden="true"></i></button>
        </header>
        <div class="broadcast-body">
            <p>Send an announcement to active auditors and auditees in the selected department.</p>
            <p class="attention-error" x-show="error" x-text="error" role="alert"></p>
            <label>Department<select x-model="broadcastForm.departmentId" required><option value="">Choose a department</option><template x-for="department in departments" :key="department._id"><option :value="department._id" x-text="department.departmentName"></option></template></select></label>
            <label>Message<textarea x-model="broadcastForm.message" required maxlength="2000" rows="5"></textarea></label>
        </div>
        <footer class="attention-actions"><button type="button" class="attention-button" @click="broadcastModalOpen = false" :disabled="saving">Cancel</button><button type="submit" class="attention-button is-primary" :disabled="saving" x-text="saving ? 'Sending?' : 'Send broadcast'"></button></footer>
    </form>
</div>
