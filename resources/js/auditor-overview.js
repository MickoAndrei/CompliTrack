document.addEventListener('alpine:init', () => {
    window.Alpine.data('auditorOverview', () => ({
        loading: true, error: '', stats: {}, changes: [], assignments: [],
        count(value) { return Math.max(0, Number(value) || 0); },
        get classifiedTotal() { return this.count(this.stats.compliantChecks) + this.count(this.stats.nonCompliantChecks); },
        get compliantPercent() { return this.classifiedTotal ? this.count(this.stats.compliantChecks) / this.classifiedTotal * 100 : 0; },
        get stages() {
            const s = this.stats.assignmentStatuses || {};
            return [['draft','Draft','#94a3b8'],['ready','Ready','#5279ad'],['in_progress','In Progress','#2563eb'],['submitted','Awaiting Review','#7c3aed'],['returned','Returned','#d97706'],['accepted','Completed','#16a34a'],['other','Other','#64748b']].map(([key,label,color]) => ({key,label,color,count:this.count(s[key])})).filter(s => s.key !== 'other' || s.count);
        },
        get assignmentTotal() { return this.count(this.stats.assignedAudits); },
        get completion() { return this.percent(this.stats.assignmentStatuses?.accepted, this.assignmentTotal); },
        get results() { const r = this.stats.checklistResults || {}; return [['N','Non-Conformance','#dc2626'],['C','Compliant','#16a34a'],['O','Observation','#d97706']].map(([key,label,color])=>({key,label,color,count:this.count(r[key])})); },
        get evaluated() { return this.results.reduce((sum,r)=>sum+r.count,0); },
        get compliance() { const r=this.stats.checklistResults || {}; return this.percent(r.C,this.count(r.C)+this.count(r.N)); },
        get cars() { return [{key:'open',label:'Open',color:'#d97706',count:this.count(this.stats.openCars)},{key:'closed',label:'Closed',color:'#16a34a',count:this.count(this.stats.closedCars)}]; },
        get carTotal() { return this.cars.reduce((sum,r)=>sum+r.count,0); },
        get closure() { return this.percent(this.stats.closedCars,this.carTotal); },
        percent(n,d) { return d ? `${(100*this.count(n)/d).toFixed(1)}%` : '—'; },
        ring(parts) { const total=parts.reduce((sum,r)=>sum+r.count,0); if(!total)return '#e2e8f0'; let end=0; return `conic-gradient(${parts.filter(r=>r.count).map(r=>{const start=end;end+=100*r.count/total;return `${r.color} ${start}% ${end}%`;}).join(',')})`; },
        get attention() { return [
            {label:'Returned audits', count:this.count(this.stats.assignmentStatuses?.returned), icon:'undo-2', tone:'amber', url:'/audits?status=returned'},
            {label:'Pending checks', count:this.count(this.stats.pendingChecks), icon:'clipboard-list', tone:'blue', url:'/audits'},
            {label:'Overdue CARs', count:this.count(this.stats.overdueCars), icon:'clock-alert', tone:'red', url:'/auditor/corrective-actions'},
            {label:'Awaiting verification', count:this.count(this.stats.awaitingVerificationCars), icon:'shield-check', tone:'amber', url:'/auditor/corrective-actions'}
        ]; },
        statusLabel(status) { return ({draft:'Draft',ready:'Ready',in_progress:'In Progress',submitted:'Awaiting Review',returned:'Returned',accepted:'Completed'})[status] || 'Other'; },
        actionLabel(status) { return ({draft:'Prepare',ready:'Open',in_progress:'Continue',submitted:'View',returned:'Revise',accepted:'View Results'})[status] || 'View'; },
        tone(status) { return ({draft:'slate',ready:'blue',in_progress:'blue',submitted:'purple',returned:'amber',accepted:'green',closed:'green',return:'amber',submit:'purple'})[status] || 'slate'; },
        progress(a) { return Math.min(100,this.count(a.progress?.percent)); },
        activityTime(value) { const t=new Date(value); if(!Number.isFinite(t.getTime()))return '—'; const minutes=Math.max(0,Math.floor((Date.now()-t.getTime())/60000)); return minutes<1?'Just now':minutes<60?`${minutes}m ago`:minutes<1440?`${Math.floor(minutes/60)}h ago`:minutes<2880?'Yesterday':t.toLocaleDateString(); },
        async load() {
            if(this.requesting)return;
            this.requesting=true; this.loading=true; this.error='';
            try {
                const { data } = await window.axios.get('/api/v1/auditor/metrics');
                this.stats = data.data || {}; this.changes = data.changes || []; this.assignments = data.dashboardAssignments || data.assignments || [];
                if (!this.stats.assignmentStatuses || !this.stats.checklistResults) throw new Error('Dashboard API update required');
            } catch { this.error = 'Unable to load your audit overview. Ensure the dashboard update is installed, then retry.'; }
            finally { this.loading = false; this.requesting=false; this.$nextTick(() => window.renderIcons?.()); }
        },
    }));
});