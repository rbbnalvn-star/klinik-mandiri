<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Sistem Manajemen Klinik Praktek Mandiri - Login">
    <title>Login - Klinik Praktek Mandiri</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #2F4156 0%, #567C8D 50%, #C8D9E6 100%);
            overflow: hidden;
            position: relative;
        }

        /* Animated background circles */
        body::before, body::after {
            content: '';
            position: absolute;
            border-radius: 50%;
            opacity: 0.08;
            background: #FFFFFF;
            animation: float 8s ease-in-out infinite;
        }
        body::before { width: 400px; height: 400px; top: -100px; left: -100px; }
        body::after { width: 300px; height: 300px; bottom: -80px; right: -80px; animation-delay: 4s; animation-direction: reverse; }

        @keyframes float {
            0%, 100% { transform: translateY(0) scale(1); }
            50% { transform: translateY(-30px) scale(1.05); }
        }

        .login-container {
            width: 100%;
            max-width: 420px;
            padding: 20px;
            z-index: 10;
        }

        .login-card {
            background: rgba(255, 255, 255, 0.85);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            border-radius: 24px;
            padding: 48px 40px;
            box-shadow: 0 25px 60px rgba(47, 65, 86, 0.2);
            animation: slideUp 0.6s ease-out;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(30px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .login-header {
            text-align: center;
            margin-bottom: 36px;
        }

        .login-icon {
            width: 64px;
            height: 64px;
            background: linear-gradient(135deg, #2F4156, #567C8D);
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            box-shadow: 0 8px 24px rgba(47, 65, 86, 0.25);
        }

        .login-icon svg {
            width: 32px;
            height: 32px;
            fill: none;
            stroke: #FFFFFF;
            stroke-width: 2;
            stroke-linecap: round;
            stroke-linejoin: round;
        }

        .login-header h1 {
            font-size: 22px;
            font-weight: 700;
            color: #2F4156;
            margin-bottom: 6px;
        }

        .login-header p {
            font-size: 14px;
            color: #567C8D;
            font-weight: 400;
        }

        .form-group {
            margin-bottom: 20px;
            position: relative;
        }

        .form-group label {
            display: block;
            font-size: 13px;
            font-weight: 600;
            color: #2F4156;
            margin-bottom: 8px;
            letter-spacing: 0.3px;
        }

        .form-group input {
            width: 100%;
            padding: 14px 16px 14px 46px;
            border: 2px solid #C8D9E6;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 14px;
            color: #2F4156;
            background: rgba(255, 255, 255, 0.7);
            transition: all 0.3s ease;
            outline: none;
        }

        .form-group input:focus {
            border-color: #567C8D;
            background: #FFFFFF;
            box-shadow: 0 0 0 4px rgba(86, 124, 141, 0.12);
        }

        .form-group input::placeholder {
            color: #9BB0BD;
        }

        .form-group .input-icon {
            position: absolute;
            left: 16px;
            bottom: 14px;
            width: 18px;
            height: 18px;
            color: #567C8D;
            pointer-events: none;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #2F4156, #567C8D);
            color: #FFFFFF;
            border: none;
            border-radius: 12px;
            font-family: 'Inter', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            letter-spacing: 0.5px;
            margin-top: 8px;
            position: relative;
            overflow: hidden;
        }

        .btn-login:hover {
            box-shadow: 0 8px 24px rgba(47, 65, 86, 0.35);
            transform: translateY(-2px);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .btn-login:disabled {
            opacity: 0.7;
            cursor: not-allowed;
            transform: none;
        }

        .btn-login .spinner {
            display: none;
            width: 20px;
            height: 20px;
            border: 2px solid rgba(255,255,255,0.3);
            border-top-color: #fff;
            border-radius: 50%;
            animation: spin 0.6s linear infinite;
            margin: 0 auto;
        }

        .btn-login.loading .btn-text { display: none; }
        .btn-login.loading .spinner { display: block; }

        @keyframes spin { to { transform: rotate(360deg); } }

        .error-message {
            background: rgba(220, 53, 69, 0.08);
            border: 1px solid rgba(220, 53, 69, 0.2);
            color: #c0392b;
            padding: 12px 16px;
            border-radius: 10px;
            font-size: 13px;
            margin-bottom: 20px;
            display: none;
            animation: shake 0.4s ease-out;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-6px); }
            75% { transform: translateX(6px); }
        }

        .login-footer {
            text-align: center;
            margin-top: 24px;
            font-size: 12px;
            color: #567C8D;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-card">
            <div class="login-header">
                <div class="login-icon">
                    <svg viewBox="0 0 24 24">
                        <path d="M22 12h-4l-3 9L9 3l-3 9H2"/>
                    </svg>
                </div>
                <h1>Klinik Praktek Mandiri</h1>
                <p>Silakan masuk untuk melanjutkan</p>
            </div>

            <div class="error-message" id="errorMessage"></div>

            <form id="loginForm" autocomplete="off">
                <div class="form-group">
                    <label for="username">Username</label>
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                    <input type="text" id="username" name="username" placeholder="Masukkan username" required autofocus>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <svg class="input-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"/>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
                    </svg>
                    <input type="password" id="password" name="password" placeholder="Masukkan password" required>
                </div>
                <button type="submit" class="btn-login" id="btnLogin">
                    <span class="btn-text">Masuk</span>
                    <div class="spinner"></div>
                </button>
            </form>

            <div class="login-footer">
                &copy; 2026 Klinik Praktek Mandiri
            </div>
        </div>
    </div>

    <script>
        document.getElementById('loginForm').addEventListener('submit', async function(e) {
            e.preventDefault();
            const btn = document.getElementById('btnLogin');
            const errEl = document.getElementById('errorMessage');
            errEl.style.display = 'none';
            btn.classList.add('loading');
            btn.disabled = true;

            try {
                const res = await fetch('api/auth.php?action=login', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        username: document.getElementById('username').value,
                        password: document.getElementById('password').value
                    })
                });
                const data = await res.json();
                if (data.success) {
                    window.location.href = 'index.php';
                } else {
                    errEl.textContent = data.message || 'Login gagal';
                    errEl.style.display = 'block';
                }
            } catch (err) {
                errEl.textContent = 'Koneksi ke server gagal';
                errEl.style.display = 'block';
            } finally {
                btn.classList.remove('loading');
                btn.disabled = false;
            }
        });
    </script>
</body>
</html>
