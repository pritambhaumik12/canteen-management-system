</div> 
    <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
    <script src="../assets/js/script.js"></script>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Run once after 1.5 seconds for immediate queue checking after page load
            setTimeout(function() {
                fetch('../includes/process_email_queue.php', { method: 'POST', cache: 'no-store' })
                .catch(function(error) {});
            }, 1500); 

            // ASYNCHRONOUS EMAIL QUEUE TRIGGER
            // Silently trigger the email queue in the background every 30 seconds
            setInterval(function() {
                fetch('../includes/process_email_queue.php', { method: 'POST', cache: 'no-store' })
                    .catch(err => {}); // Ignore any network errors silently
            }, 30000);
        });
    </script>
</body>
</html>