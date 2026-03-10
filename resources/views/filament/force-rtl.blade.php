<script>
    document.documentElement.lang = 'ar';
    document.documentElement.dir = 'rtl';

    const applyAdsPageClass = () => {
        const isAdsPage = window.location.pathname.includes('/admin/ads');

        if (! document.body) {
            return;
        }

        document.body.classList.toggle('ads-content-page', isAdsPage);
    };

    if (document.body) {
        document.body.dir = 'rtl';
        applyAdsPageClass();
    }

    document.addEventListener('livewire:navigated', applyAdsPageClass);
</script>

<style>
    body.ads-content-page .fi-ta-header .fi-ta-actions {
        justify-content: center;
        width: 100%;
    }
</style>
