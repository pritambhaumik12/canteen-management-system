<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Offline - BWU Smart Dine</title>
    
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.3/css/all.min.css">
    
    <style>
        body {
            font-family: 'Poppins', sans-serif;
            background: linear-gradient(135deg, #fdfbfb 0%, #ebedee 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            overflow: hidden;
        }

        /* Background decorative elements */
        .bg-shapes {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            overflow: hidden;
        }
        .shape {
            position: absolute;
            filter: blur(50px);
            opacity: 0.4;
            animation: float 10s infinite ease-in-out alternate;
        }
        .shape-1 { top: -10%; left: -10%; width: 300px; height: 300px; background: #ff7675; border-radius: 50%; }
        .shape-2 { bottom: -10%; right: -10%; width: 400px; height: 400px; background: #74b9ff; border-radius: 50%; animation-delay: -5s; }

        @keyframes float {
            0% { transform: translate(0, 0) scale(1); }
            100% { transform: translate(30px, 50px) scale(1.1); }
        }

        /* Glassmorphism Card */
        .offline-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 24px;
            padding: 3rem 2rem;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0,0,0,0.08);
            max-width: 450px;
            width: 90%;
            transform: translateY(20px);
            opacity: 0;
            animation: slideUp 0.6s ease forwards;
            transition: transform 0.3s ease;
        }

        @keyframes slideUp {
            to { transform: translateY(0); opacity: 1; }
        }

        /* Icon styling */
        .icon-wrapper {
            width: 100px;
            height: 100px;
            background: #fff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem auto;
            box-shadow: 0 10px 20px rgba(214, 48, 49, 0.15);
            position: relative;
        }

        .offline-icon {
            font-size: 3rem;
            color: #d63031;
            animation: pulse 2s infinite;
            transition: color 0.4s ease;
        }

        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }

        /* Typography */
        .title {
            font-weight: 700;
            color: #2d3436;
            margin-bottom: 0.5rem;
            font-size: 1.75rem;
        }

        .subtitle {
            color: #636e72;
            font-size: 0.95rem;
            margin-bottom: 2rem;
            line-height: 1.6;
        }

        /* Status indicator */
        .status-dot {
            width: 10px;
            height: 10px;
            background-color: #d63031;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
            animation: blink 1.5s infinite;
        }

        .status-text {
            font-size: 0.85rem;
            color: #d63031;
            font-weight: 600;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: color 0.4s ease;
        }

        @keyframes blink {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.4; }
        }

        /* Buttons */
        .btn-custom {
            padding: 0.75rem 1.5rem;
            border-radius: 50px;
            font-weight: 600;
            transition: all 0.3s ease;
            width: 100%;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .btn-retry {
            background: linear-gradient(135deg, #0984e3 0%, #74b9ff 100%);
            color: white;
            border: none;
            box-shadow: 0 8px 15px rgba(9, 132, 227, 0.3);
        }

        .btn-retry:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 20px rgba(9, 132, 227, 0.4);
            color: white;
        }

        .btn-retry:active {
            transform: translateY(0);
        }

        .btn-home {
            background: white;
            color: #2d3436;
            border: 2px solid #dfe6e9;
        }

        .btn-home:hover {
            background: #f5f6fa;
            border-color: #b2bec3;
            color: #2d3436;
            text-decoration: none;
        }

        /* Shaking animation for failed retry */
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            50% { transform: translateX(10px); }
            75% { transform: translateX(-10px); }
        }

        .shake-active {
            animation: shake 0.4s ease-in-out;
        }

        /* Mobile Adjustments */
        @media (max-width: 480px) {
            .offline-card {
                padding: 2.5rem 1.5rem;
            }
            .title {
                font-size: 1.5rem;
            }
            .icon-wrapper {
                width: 80px;
                height: 80px;
            }
            .offline-icon {
                font-size: 2.5rem;
            }
            .subtitle {
                font-size: 0.9rem;
            }
        }
    </style>
</head>
<body>

    <div class="bg-shapes">
        <div class="shape shape-1"></div>
        <div class="shape shape-2"></div>
    </div>

    <div class="offline-card" id="offlineCard">
        <div class="icon-wrapper">
            <i class="fas fa-wifi-slash offline-icon" id="mainIcon"></i>
        </div>
        
        <h1 class="title" id="mainTitle">Oops! You're Offline</h1>
        
        <div class="status-text" id="status-indicator">
            <span class="status-dot" id="statusDot"></span> Waiting for connection...
        </div>
        
        <p class="subtitle" id="mainSubtitle">
            It looks like you've lost your internet connection. We'll automatically reconnect you when the network is back, or you can try again manually.
        </p>

        <button onclick="attemptReconnect()" class="btn btn-custom btn-retry" id="retryBtn">
            <i class="fas fa-sync-alt mr-2" id="retryIcon"></i> Try Again
        </button>

        <a href="/canteen/index.php" class="btn btn-custom btn-home">
            <i class="fas fa-home mr-2"></i> Return to Cached Home
        </a>
    </div>

    <script>
        // Function to trigger when network is restored
        function updateOnlineStatus() {
            if (navigator.onLine) {
                // Change Status Text
                const statusIndicator = document.getElementById('status-indicator');
                statusIndicator.style.color = '#00b894'; // Green
                statusIndicator.innerHTML = '<i class="fas fa-check-circle mr-2"></i> Back Online! Refreshing...';
                
                // Change Icon
                const icon = document.getElementById('mainIcon');
                icon.className = 'fas fa-wifi offline-icon';
                icon.style.color = '#00b894';

                // Update Titles
                document.getElementById('mainTitle').innerText = "Connection Restored";
                document.getElementById('mainSubtitle').innerText = "Redirecting you back to where you were...";
                
                // Hide Buttons
                document.getElementById('retryBtn').style.display = 'none';
                document.querySelector('.btn-home').style.display = 'none';
                
                // Reload page after a short delay so user sees the success message
                setTimeout(() => {
                    window.location.reload();
                }, 1500);
            }
        }

        // Listen for the browser's native 'online' event
        window.addEventListener('online', updateOnlineStatus);
        
        // Manual retry button logic with visual feedback
        function attemptReconnect() {
            const btn = document.getElementById('retryBtn');
            const icon = document.getElementById('retryIcon');
            const card = document.getElementById('offlineCard');
            
            // Add spinning animation to icon and change text
            icon.classList.remove('fa-sync-alt');
            icon.classList.add('fa-circle-notch', 'fa-spin');
            btn.innerHTML = '<i class="fas fa-circle-notch fa-spin mr-2"></i> Checking Connection...';
            btn.style.opacity = '0.8';
            btn.disabled = true;

            // Fake delay to show checking process
            setTimeout(() => {
                if (navigator.onLine) {
                    updateOnlineStatus(); // Trigger success flow
                } else {
                    // Reset button if still offline
                    icon.classList.remove('fa-circle-notch', 'fa-spin');
                    icon.classList.add('fa-sync-alt');
                    btn.innerHTML = '<i class="fas fa-sync-alt mr-2" id="retryIcon"></i> Try Again';
                    btn.style.opacity = '1';
                    btn.disabled = false;
                    
                    // Trigger shake animation to indicate failure
                    card.classList.remove('shake-active');
                    void card.offsetWidth; // Trigger reflow to restart animation
                    card.classList.add('shake-active');
                }
            }, 800);
        }

        // Check immediately in case the page loaded while internet was actually working
        if (navigator.onLine) {
            updateOnlineStatus();
        }
    </script>
</body>
</html>