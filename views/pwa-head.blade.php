<!-- PWA Meta Tags & Manifest -->
<link rel="manifest" href="manifest.json">
<meta name="theme-color" content="#0f172a">
<meta name="mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="apple-mobile-web-app-title" content="IIJmio Usage">
<link rel="apple-touch-icon" href="icon-192.png">
<link rel="icon" type="image/svg+xml" href="icon.svg">
<link rel="icon" type="image/png" sizes="192x192" href="icon-192.png">

<!-- Service Worker Registration -->
<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function() {
            navigator.serviceWorker.register('sw.js').then(function(registration) {
                console.log('ServiceWorker registration successful with scope: ', registration.scope);
            }, function(err) {
                console.log('ServiceWorker registration failed: ', err);
            });
        });
    }
</script>
