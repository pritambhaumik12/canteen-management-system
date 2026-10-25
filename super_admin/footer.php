</div> </div> </div> <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="../assets/js/script.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            const sidebarBtn = document.getElementById('sidebarCollapse');
            const sidebar = document.getElementById('sidebar');
            const overlay = document.getElementById('sidebarOverlay');
            const mobileCloseBtn = document.getElementById('mobileSidebarClose');

            // Retrieve the previous sidebar state from localStorage for desktop
            const sidebarState = localStorage.getItem('superAdminSidebarState');
            if (sidebarState === 'collapsed' && sidebar && window.innerWidth > 768) {
                sidebar.classList.add('active'); // .active on desktop collapses it
            }

            function toggleSidebar() {
                sidebar.classList.toggle('active');
                
                if (window.innerWidth <= 768) {
                    // On mobile, .active means the sidebar is SHOWN
                    if (sidebar.classList.contains('active')) {
                        overlay.classList.add('active');
                    } else {
                        overlay.classList.remove('active');
                    }
                } else {
                    // On desktop, .active means the sidebar is COLLAPSED
                    if (sidebar.classList.contains('active')) {
                        localStorage.setItem('superAdminSidebarState', 'collapsed');
                    } else {
                        localStorage.setItem('superAdminSidebarState', 'expanded');
                    }
                }
            }

            // Bind toggle to the top nav button
            if (sidebarBtn && sidebar) {
                sidebarBtn.addEventListener('click', toggleSidebar);
            }
            
            // Allow the mobile internal "X" button to explicitly close the sidebar
            if (mobileCloseBtn) {
                mobileCloseBtn.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            }
            
            // Allow clicking outside the sidebar (on the overlay) to close it
            if (overlay) {
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('active');
                    overlay.classList.remove('active');
                });
            }
            
            // Auto-clean classes to prevent glitchy states when resizing
            window.addEventListener('resize', function() {
                if (window.innerWidth > 768) {
                    // Desktop mode: Drop overlay, sync with localStorage
                    if(overlay) overlay.classList.remove('active');
                    
                    const state = localStorage.getItem('superAdminSidebarState');
                    if (state === 'collapsed') {
                        sidebar.classList.add('active');
                    } else {
                        sidebar.classList.remove('active');
                    }
                } else {
                    // Mobile mode: Always default to hidden when resizing downwards
                    sidebar.classList.remove('active');
                    if(overlay) overlay.classList.remove('active');
                }
            });
        });

        // ASYNCHRONOUS EMAIL QUEUE TRIGGER
        // Silently trigger the email queue in the background every 30 seconds
        setInterval(function() {
            fetch('../includes/process_email_queue.php', { method: 'POST', cache: 'no-store' })
                .catch(err => {}); // Ignore any network errors silently
        }, 30000);
    </script>
</body>
</html>