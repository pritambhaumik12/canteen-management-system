<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>503 - Service Unavailable</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700&display=swap" rel="stylesheet">
    <style>
        :root { --primary: #64748b; --rgb-primary: 100, 116, 139; --bg: #f8fafc; --card-bg: #ffffff; --text-main: #0f172a; --text-muted: #64748b; }
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; font-family: 'Inter', sans-serif; background-color: var(--bg); overflow: hidden; }
        .orb { position: absolute; border-radius: 50%; filter: blur(80px); opacity: 0.2; z-index: -1; animation: float 12s infinite alternate ease-in-out; }
        .orb-1 { width: 400px; height: 400px; background: var(--primary); top: 5%; left: 15%; }
        .orb-2 { width: 500px; height: 500px; background: #cbd5e1; bottom: 5%; right: 10%; animation-delay: -6s; }
        @keyframes float { 0% { transform: translate(0, 0); } 100% { transform: translate(40px, 60px); } }
        .error-card { position: relative; background: var(--card-bg); padding: 50px 40px; border-radius: 24px; box-shadow: 0 20px 40px -10px rgba(0,0,0,0.08), 0 0 0 1px rgba(0,0,0,0.02); text-align: center; max-width: 420px; width: 90%; z-index: 10; animation: slideUp 0.8s cubic-bezier(0.16, 1, 0.3, 1) forwards; overflow: hidden; }
        .error-card::before { content: ''; position: absolute; top: 0; left: 0; right: 0; bottom: 0; z-index: -1; background: radial-gradient(600px circle at var(--mouse-x) var(--mouse-y), rgba(var(--rgb-primary), 0.08), transparent 40%); opacity: 0; transition: opacity 0.3s; pointer-events: none; }
        .error-card:hover::before { opacity: 1; }
        .icon-wrapper { width: 70px; height: 70px; margin: 0 auto 24px; display: flex; align-items: center; justify-content: center; background: rgba(var(--rgb-primary), 0.1); color: var(--primary); border-radius: 50%; }
        .error-code { font-size: 56px; font-weight: 700; color: var(--text-main); margin: 0 0 8px; line-height: 1; letter-spacing: -1.5px; }
        .error-title { font-size: 20px; font-weight: 500; color: var(--text-main); margin: 0 0 16px; }
        .error-desc { font-size: 15px; color: var(--text-muted); line-height: 1.6; margin-bottom: 35px; }
        .btn-group { display: flex; gap: 12px; justify-content: center; flex-direction: row; }
        .btn { padding: 12px 24px; border-radius: 12px; font-size: 14px; font-weight: 500; text-decoration: none; transition: all 0.2s; cursor: pointer; border: none; display: inline-flex; align-items: center; justify-content: center; width: 100%; }
        .btn-primary { background: var(--primary); color: #fff; box-shadow: 0 4px 12px rgba(var(--rgb-primary), 0.25); }
        .btn-primary:hover { transform: translateY(-2px); box-shadow: 0 6px 16px rgba(var(--rgb-primary), 0.35); }
        .btn-secondary { background: #f1f5f9; color: var(--text-main); }
        .btn-secondary:hover { background: #e2e8f0; transform: translateY(-2px); }
        @keyframes slideUp { from { opacity: 0; transform: translateY(20px); } to { opacity: 1; transform: translateY(0); } }
        @media (max-width: 480px) { .btn-group { flex-direction: column; } .error-card { padding: 40px 25px; } .error-code { font-size: 48px; } }
    </style>
</head>
<body>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="error-card" id="card">
        <div class="icon-wrapper">
            <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
        </div>
        <h1 class="error-code">503</h1>
        <h2 class="error-title">Service Unavailable</h2>
        <p class="error-desc">The server is currently unable to handle the request due to temporary maintenance or overloading. Please try again later.</p>
        <div class="btn-group">
            <button onclick="history.back()" class="btn btn-secondary">Return Back</button>
            <a href="../canteen/index.php" class="btn btn-primary">Go to Dashboard</a>
        </div>
    </div>
    <script>
        const card = document.getElementById('card');
        card.addEventListener('mousemove', e => {
            const rect = card.getBoundingClientRect();
            card.style.setProperty('--mouse-x', `${e.clientX - rect.left}px`);
            card.style.setProperty('--mouse-y', `${e.clientY - rect.top}px`);
        });
    </script>
</body>
</html>