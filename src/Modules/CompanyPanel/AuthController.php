<?php

class AuthController
{
    public function loginForm(): void
    {
        if (SessionMiddleware::isLoggedIn()) {
            $tenant = SessionMiddleware::getTenantContext();
            redirect($tenant->getLayer() === 'company' ? '/dashboard' : '/customer/dashboard');
        }

        $error = $_SESSION['_flash']['error'] ?? null;
        unset($_SESSION['_flash']['error']);

        ?>
        <!DOCTYPE html>
        <html lang="id">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login - MOVA</title>
            <link rel="stylesheet" href="/assets/css/login.css">
        </head>
        <body>
            <div class="login-page">
                <aside class="login-brand" aria-hidden="true">
                    <div class="login-brand__map"></div>
                    <div class="login-brand__route">
                        <svg viewBox="0 0 800 600" preserveAspectRatio="xMidYMid slice" xmlns="http://www.w3.org/2000/svg">
                            <defs>
                                <marker id="dot" viewBox="0 0 6 6" refX="3" refY="3" markerWidth="6" markerHeight="6">
                                    <circle cx="3" cy="3" r="2.4" fill="#DE9A3C" />
                                </marker>
                            </defs>
                            <polyline points="50,520 160,440 260,470 340,360 430,340 520,220 620,180 710,90" fill="none" stroke="rgba(255,255,255,0.35)" stroke-width="1.8" stroke-dasharray="6 6" marker-start="url(#dot)" marker-mid="url(#dot)" marker-end="url(#dot)"/>
                            <polyline points="80,540 150,500 240,510 320,430 400,390 480,300 560,280 640,210" fill="none" stroke="rgba(160,210,190,0.25)" stroke-width="1.4" stroke-dasharray="5 7"/>
                            <circle cx="160" cy="440" r="3.2" fill="#DE9A3C" opacity="0.9"/>
                            <circle cx="340" cy="360" r="3.2" fill="#DE9A3C" opacity="0.9"/>
                            <circle cx="520" cy="220" r="3.2" fill="#DE9A3C" opacity="0.9"/>
                            <circle cx="710" cy="90" r="3.2" fill="#DE9A3C" opacity="0.9"/>
                        </svg>
                    </div>
                    <div class="login-brand__header">
                        <span class="login-brand__wordmark">MOVA</span>
                    </div>
                    <div class="login-brand__body">
                        <div class="login-brand__eyebrow">Fleet &amp; Driver Management</div>
                        <div class="login-brand__title">Satu dasbor untuk seluruh armada dan pengemudi Anda</div>
                        <div class="login-brand__desc">Kelola kendaraan, perjalanan, biaya operasional, dan maintenance — dalam satu platform yang terpadu.</div>
                    </div>
                    <div class="login-brand__stats">
                        <div class="login-brand__stat">
                            <div class="login-brand__stat-value">2,480+</div>
                            <div class="login-brand__stat-label">Armada dipantau</div>
                        </div>
                        <div class="login-brand__stat">
                            <div class="login-brand__stat-value">14</div>
                            <div class="login-brand__stat-label">Site aktif</div>
                        </div>
                        <div class="login-brand__stat">
                            <div class="login-brand__stat-value">99.9%</div>
                            <div class="login-brand__stat-label">Uptime tracking</div>
                        </div>
                    </div>
                    <div class="login-brand__footer">
                        &copy; <?= date('Y') ?> PT. Bumi Jasa Utama / Kalla Transport &amp; Logistics
                    </div>
                </aside>
                <main class="login-form-panel">
                    <div class="login-form">
                        <img class="login-form__logo" src="/assets/mova-logo.png" alt="MOVA">
                        <div class="login-form__eyebrow">Fleet &amp; Driver Management</div>
                        <h1 class="login-form__title">Masuk ke akun Anda</h1>
                        <p class="login-form__subtitle">Gunakan akun yang diberikan administrator untuk mengakses panel MOVA.</p>
                        <?php if ($error): ?>
                            <div class="login-form__error"><?= e($error) ?></div>
                        <?php endif; ?>
                        <form method="POST" action="/login" novalidate>
                            <?= csrf_field() ?>
                            <div class="login-form__field">
                                <label class="login-form__label" for="email">Email</label>
                                <input class="login-form__input" type="email" id="email" name="email" placeholder="nama@perusahaan.com" required autofocus autocomplete="email">
                            </div>
                            <div class="login-form__field">
                                <label class="login-form__label" for="password">Kata Sandi</label>
                                <input class="login-form__input" type="password" id="password" name="password" placeholder="Masukkan kata sandi" required autocomplete="current-password">
                            </div>
                            <div class="login-form__actions">
                                <label class="login-form__remember">
                                    <input type="checkbox" name="remember">
                                    <span>Ingat saya</span>
                                </label>
                                <a class="login-form__forgot" href="#">Lupa sandi?</a>
                            </div>
                            <button class="login-form__submit" type="submit">Masuk</button>
                        </form>
                        <div class="login-form__footer">
                            MOVA v1.0 &middot; PT. Bumi Jasa Utama
                        </div>
                    </div>
                </main>
            </div>
        </body>
        </html>
        <?php
    }

    public function login(): void
    {
        AuthMiddleware::validateCsrf();

        $email = $_POST['email'] ?? '';
        $password = $_POST['password'] ?? '';

        if (empty($email) || empty($password)) {
            $_SESSION['_flash']['error'] = 'Email dan password wajib diisi';
            redirect('/login');
        }

        try {
            $db = Database::getConnection();

            $limiter = new LoginRateLimiter($db);
            if ($limiter->isLocked($email)) {
                $_SESSION['_flash']['error'] = 'Akun sementara dikunci. Silakan coba lagi 15 menit.';
                redirect('/login');
            }

            $stmt = $db->prepare("
                SELECT u.*, r.name AS role_name, r.layer
                FROM mova_users u
                JOIN mova_roles r ON r.id = u.role_id
                WHERE u.email = ? AND u.is_active = 1
                LIMIT 1
            ");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user || !password_verify($password, $user['password'])) {
                $limiter->recordFailedAttempt($email);
                $_SESSION['_flash']['error'] = 'Email atau password salah';
                redirect('/login');
            }

            session_regenerate_id(true);

            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['role'] = $user['role_name'];
            $_SESSION['layer'] = $user['layer'];
            $_SESSION['customer_id'] = $user['customer_id'] ? (int) $user['customer_id'] : null;
            $_SESSION['user_name'] = $user['name'];
            $_SESSION['user_email'] = $user['email'];
            $_SESSION['_user'] = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role_name' => $user['role_name'],
                'layer' => $user['layer'],
                'customer_id' => $user['customer_id'] ? (int) $user['customer_id'] : null,
            ];

            if ($user['layer'] === 'company') {
                $stmt = $db->prepare("
                    SELECT ub.branch_id FROM mova_user_branch_access ub WHERE ub.user_id = ?
                ");
                $stmt->execute([$user['id']]);
                $branches = $stmt->fetchAll();

                if (!empty($branches)) {
                    $_SESSION['branch_id'] = (int) $branches[0]['branch_id'];
                    $_SESSION['branch_ids'] = array_column($branches, 'branch_id');
                    $_SESSION['_user']['branch_ids'] = $_SESSION['branch_ids'];
                }
            }

            $limiter->clearAttempts($email);
            $limiter->recordSuccessfulAttempt($email);

            $stmt = $db->prepare("UPDATE mova_users SET last_login_at = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);

            if ($user['layer'] === 'company') {
                redirect('/dashboard');
            } else {
                redirect('/customer/dashboard');
            }
        } catch (\PDOException $e) {
            $_SESSION['_flash']['error'] = 'Terjadi kesalahan sistem. Silakan coba lagi.';
            redirect('/login');
        }
    }

    public function logout(): void
    {
        SessionMiddleware::destroy();
        redirect('/login');
    }
}
