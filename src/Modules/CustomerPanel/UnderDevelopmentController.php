<?php

class UnderDevelopmentController
{
    public function show(string $key): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::requireLayer('customer');

        $mod = null;
        foreach (moduleCatalog() as $m) {
            if ($m['key'] === $key) { $mod = $m; break; }
        }
        if (!$mod) { redirect('/customer/dashboard'); }

        $tierLabel = $mod['tier'] === 'premium' ? 'Premium' : 'Enterprise';
        $tierBadge = $mod['tier'] === 'premium' ? 'badge-premium' : 'badge-enterprise';
        $inPlan = SessionMiddleware::getTenantContext()->hasModule($key);

        ob_start();
        ?>
        <div class="card" style="max-width:600px;margin:32px auto">
            <div class="card-body" style="text-align:center;padding:44px 32px">
                <div class="ud-icon"><?= $mod['icon'] ?></div>
                <div style="display:flex;gap:8px;justify-content:center;margin:18px 0 10px">
                    <span class="badge <?= $tierBadge ?>"><?= e($tierLabel) ?></span>
                    <?php if ($inPlan): ?>
                    <span class="badge badge-active">Termasuk paket Anda</span>
                    <span class="badge badge-warning">Under Development</span>
                    <?php else: ?>
                    <span class="badge badge-inactive">Perlu Upgrade</span>
                    <?php endif; ?>
                </div>
                <h2 style="font-size:1.4rem;font-weight:600;margin-bottom:8px"><?= e($mod['label']) ?></h2>
                <p style="color:var(--text-2);max-width:420px;margin:0 auto 18px;line-height:1.6"><?= e($mod['desc']) ?></p>
                <p style="font-size:0.82rem;color:var(--text-muted);margin-bottom:22px">
                    <?php if ($inPlan): ?>
                    Fitur ini <strong style="color:var(--text-2)">termasuk paket Anda</strong> dan sedang dalam pengembangan.
                    <?php else: ?>
                    Fitur ini tersedia di paket <strong style="color:var(--text-2)"><?= e($tierLabel) ?></strong>. Upgrade paket untuk mengaktifkannya nanti.
                    <?php endif; ?>
                </p>
                <a href="/customer/dashboard" class="btn btn-outline">&larr; Kembali ke Dashboard</a>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        require __DIR__ . '/Views/layout.php';
        renderCustomerLayout($mod['label'], $content, ['active' => $key]);
    }
}
