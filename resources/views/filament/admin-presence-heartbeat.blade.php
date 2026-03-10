<script>
(function () {
    const path = window.location.pathname || '';

    if (! path.startsWith('/admin')) {
        return;
    }

    if (window.__adminPresenceHeartbeatStarted) {
        return;
    }

    window.__adminPresenceHeartbeatStarted = true;

    const csrfToken = document
        .querySelector('meta[name="csrf-token"]')
        ?.getAttribute('content');

    const ping = async () => {
        try {
            await fetch('/admin/presence/ping', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken || '',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                credentials: 'same-origin',
                body: '{}',
            });
        } catch (_error) {
            // Silent fail: presence is best-effort only.
        }
    };

    const markOfflineOnLogout = () => {
        const logoutForms = document.querySelectorAll('form[action*="logout"]');

        logoutForms.forEach((form) => {
            if (form.dataset.presenceOfflineBound === '1') {
                return;
            }

            form.dataset.presenceOfflineBound = '1';

            form.addEventListener('submit', () => {
                try {
                    const data = new URLSearchParams();
                    data.append('_token', csrfToken || '');

                    navigator.sendBeacon('/admin/presence/offline', data);
                } catch (_error) {
                    // Silent fail.
                }
            });
        });
    };

    // Initial ping and recurring heartbeat for all admin pages.
    ping();
    markOfflineOnLogout();
    window.__adminPresenceHeartbeatInterval = setInterval(ping, 30000);

    // Re-bind after Livewire SPA navigations.
    document.addEventListener('livewire:navigated', () => {
        markOfflineOnLogout();
    });
})();
</script>
