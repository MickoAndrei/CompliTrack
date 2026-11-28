// Shared shell: layout state only; authorization remains on the server.
export function portalShell() {
    const key = `complitrack.v2.sidebar.${document.body.dataset.userId || 'anonymous'}`;
    return {
        sidebarOpen: false,
        sidebarCollapsed: false,
        init() {
            try { this.sidebarCollapsed = localStorage.getItem(key) === 'collapsed'; } catch { /* storage unavailable */ }
        },
        toggleSidebar() {
            if (!window.matchMedia('(min-width: 1024px)').matches) {
                this.sidebarOpen = !this.sidebarOpen;
                return;
            }
            this.sidebarCollapsed = !this.sidebarCollapsed;
            try { localStorage.setItem(key, this.sidebarCollapsed ? 'collapsed' : 'expanded'); } catch { /* storage unavailable */ }
        },
    };
}
