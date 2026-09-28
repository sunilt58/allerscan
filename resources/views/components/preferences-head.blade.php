<script>
    // Apply the saved appearance before the stylesheet paints the page.
    (() => {
        try {
            const preferences = JSON.parse(localStorage.getItem('allerscan-display-preferences') || '{}') || {};
            document.documentElement.dataset.theme = ['forest', 'ocean', 'midnight'].includes(preferences.theme) ? preferences.theme : 'forest';
            document.documentElement.classList.toggle('high-contrast', !!preferences.contrast);
            document.documentElement.classList.toggle('large-text', !!preferences.largeText);
        } catch { document.documentElement.dataset.theme = 'forest'; }
    })();
</script>
