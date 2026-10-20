<aside class="sidebar-panel" aria-label="CompliTrack Branding and Features">
    <div class="bg-shape circle-top"></div><div class="bg-shape circle-bottom"></div>
    <header class="sidebar-header">
        <a href="{{ route('login') }}" class="brand-logo" title="USTP Claveria CompliTrack Home">
            <div class="logo-badge-wrapper"><img src="{{ asset('images/ustp-claveria-logo.jpg') }}" alt="USTP Claveria Logo" class="brand-logo-img"></div>
            <div class="brand-text"><div class="logo-title"><span class="text-white">Compli</span><span class="text-gold">Track</span></div><div class="logo-subtitle">CLAVERIA CAMPUS / WEBSITE</div></div>
        </a>
    </header>
    <div class="sidebar-content">
        <div class="sidebar-message {{ request()->routeIs('password.request') ? 'hidden' : '' }}">
            <h1 class="sidebar-headline">Regulatory Compliance &amp; Accountability, in one place.</h1>
            <p class="sidebar-description">CompliTrack keeps every department's evidence, checks, and corrective actions auditable and up to date.</p>
        </div>
        <div class="sidebar-message {{ request()->routeIs('password.request') ? '' : 'hidden' }}">
            <h1 class="sidebar-headline">Locked out happens. Getting back in shouldn't be hard.</h1>
            <p class="sidebar-description">Enter the email tied to your USTP account and we'll send a secure link to reset your password.</p>
        </div>
    </div>
    <div class="sidebar-footer">
        <div class="features-list {{ request()->routeIs('password.request') ? 'hidden' : '' }}" aria-label="Key Platform Features">
            <div class="feature-item" x-data="{ open: false }" :class="open ? 'active' : ''" :aria-expanded="open" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open"><div class="feature-item-header"><div class="feature-icon-badge"><i data-lucide="lock-keyhole" class="icon-gold"></i></div><span class="feature-text">Encrypted archive of compliance evidence</span><i data-lucide="chevron-down" class="feature-chevron"></i></div><div class="feature-details"><p class="feature-details-inner">Tamper-evident repository for ISO, AACCUP, and CHED accreditation documents with role-based USTP faculty permissions.</p></div></div>
            <div class="feature-item" x-data="{ open: false }" :class="open ? 'active' : ''" :aria-expanded="open" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open"><div class="feature-item-header"><div class="feature-icon-badge"><i data-lucide="clipboard-check" class="icon-gold"></i></div><span class="feature-text">Track compliance checks per department</span><i data-lucide="chevron-down" class="feature-chevron"></i></div><div class="feature-details"><p class="feature-details-inner">Monitor completion metrics, submission checklists, and audit readiness across Claveria Campus.</p></div></div>
            <div class="feature-item" x-data="{ open: false }" :class="open ? 'active' : ''" :aria-expanded="open" role="button" tabindex="0" @click="open = !open" @keydown.enter.prevent="open = !open" @keydown.space.prevent="open = !open"><div class="feature-item-header"><div class="feature-icon-badge"><i data-lucide="bell" class="icon-gold"></i></div><span class="feature-text">Real-time corrective action notifications</span><i data-lucide="chevron-down" class="feature-chevron"></i></div><div class="feature-details"><p class="feature-details-inner">Automated alerts for approaching deadlines, findings, and required Corrective Action Requests.</p></div></div>
        </div>
        <div class="reset-footnote {{ request()->routeIs('password.request') ? '' : 'hidden' }}"><div class="security-note-box"><i data-lucide="info" class="icon-gold"></i><p>Reset links are single-use and expire after 30 minutes for institutional security.</p></div></div>
    </div>
</aside>
