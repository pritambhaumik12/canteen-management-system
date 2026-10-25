window.addEventListener('load', function() {
    const loadingOverlay = document.getElementById('loading-overlay');
    if (loadingOverlay) {
        loadingOverlay.classList.add('hidden');
    }
});

document.addEventListener('DOMContentLoaded', function() {
    const forms = document.querySelectorAll('form');
    const loadingOverlay = document.getElementById('loading-overlay');

    forms.forEach(form => {
        form.addEventListener('submit', function() {
            // Check network before submitting forms
            if (!navigator.onLine) {
                alert("You are currently offline. Please reconnect to submit this form.");
                // Stop the loader from triggering if offline
                if (loadingOverlay) loadingOverlay.classList.add('hidden');
                return false; 
            }
            if (loadingOverlay) {
                loadingOverlay.classList.remove('hidden');
            }
        });
    });

    // --- Offline/Online Status Banner ---
    const statusBanner = document.createElement('div');
    statusBanner.id = 'network-status-banner';
    statusBanner.style.cssText = `
        position: fixed; top: 0; left: 0; width: 100%; text-align: center; padding: 10px;
        color: white; font-weight: bold; z-index: 10000; transition: transform 0.3s ease;
        transform: translateY(-100%);
    `;
    document.body.appendChild(statusBanner);

    function updateNetworkStatus() {
        if (navigator.onLine) {
            statusBanner.style.backgroundColor = '#1cc88a'; // Green
            statusBanner.innerHTML = '<i class="fas fa-wifi"></i> Back Online';
            setTimeout(() => { statusBanner.style.transform = 'translateY(-100%)'; }, 3000);
        } else {
            statusBanner.style.backgroundColor = '#e74a3b'; // Red
            statusBanner.innerHTML = '<i class="fas fa-wifi-slash"></i> You are currently offline';
            statusBanner.style.transform = 'translateY(0)';
        }
    }

    window.addEventListener('online', updateNetworkStatus);
    window.addEventListener('offline', updateNetworkStatus);

    // Initial check
    if (!navigator.onLine) updateNetworkStatus();
});