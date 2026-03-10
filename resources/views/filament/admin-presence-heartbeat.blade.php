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

    // Initial ping and recurring heartbeat for all admin pages.
    ping();
    window.__adminPresenceHeartbeatInterval = setInterval(ping, 30000);
})();
</script>
