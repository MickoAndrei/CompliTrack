import './bootstrap';
import Alpine from 'alpinejs';
import { createIcons, icons } from 'lucide';
import './pages';

const startServerStatusMonitor = () => {
    const userId = document.body?.dataset.userId;
    const banner = document.getElementById('server-offline-banner');
    if (!userId || !banner) return;

    const setOffline = (offline) => banner.classList.toggle('hidden', !offline);
    let checking = false;

    const checkServer = async () => {
        if (checking) return;
        checking = true;

        try {
            const health = await fetch('/up', {
                cache: 'no-store',
                credentials: 'same-origin',
                signal: AbortSignal.timeout(4000),
            });
            if (!health.ok) throw new Error('Server health check failed.');

            const session = await fetch('/api/v1/auth/me', {
                cache: 'no-store',
                credentials: 'same-origin',
                headers: { Accept: 'application/json' },
                signal: AbortSignal.timeout(4000),
            });

            if (session.status === 401 || session.status === 419) {
                window.location.replace('/login?reason=server-restarted');
                return;
            }
            if (!session.ok) throw new Error('Session check failed.');

            setOffline(false);
        } catch {
            setOffline(true);
        } finally {
            checking = false;
            window.setTimeout(checkServer, 5000);
        }
    };

    checkServer();
    window.addEventListener('online', checkServer);
};

window.Alpine = Alpine;

const renderIcons = () => createIcons({ icons });
window.renderIcons = renderIcons;

document.addEventListener('DOMContentLoaded', renderIcons);
document.addEventListener('alpine:initialized', renderIcons);
document.addEventListener('DOMContentLoaded', startServerStatusMonitor);
Alpine.start();
