<?php

class NotificationController
{
    public function index(): void
    {
        AuthMiddleware::requireAuth();
        $notifications = getNotifications(50);

        ob_start();
        ?>
        <div class="card">
            <div class="card-header"><h3>Notifikasi</h3></div>
            <div class="card-body">
                <?php if (empty($notifications)): ?>
                    <div class="empty-state"><p>Belum ada notifikasi.</p></div>
                <?php else: ?>
                <div class="table-wrap"><table>
                    <thead><tr><th>Waktu</th><th>Tipe</th><th>Pesan</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach ($notifications as $n): ?>
                        <tr style="<?= !$n['is_read'] ? 'font-weight:600;background:var(--brand-soft)' : '' ?>">
                            <td style="white-space:nowrap"><?= date('d/m/Y H:i', strtotime($n['created_at'])) ?></td>
                            <td><span class="badge badge-<?= e($n['type']) ?>"><?= e($n['type']) ?></span></td>
                            <td><?= e($n['message']) ?></td>
                            <td><?= $n['is_read'] ? 'Dibaca' : 'Baru' ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table></div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        $content = ob_get_clean();

        $layer = $_SESSION['layer'] ?? 'customer';
        if ($layer === 'company') {
            require __DIR__ . '/../CompanyPanel/Views/layout.php';
            renderLayout('Notifikasi', $content, ['active' => 'notifications']);
        } else {
            require __DIR__ . '/../CustomerPanel/Views/layout.php';
            renderCustomerLayout('Notifikasi', $content, ['active' => 'notifications']);
        }
    }

    public function markRead(): void
    {
        AuthMiddleware::requireAuth();
        AuthMiddleware::validateCsrf();
        $db = Database::getConnection();
        $stmt = $db->prepare("UPDATE mova_notifications SET is_read = 1 WHERE user_id = ?");
        $stmt->execute([(int)$_SESSION['user_id']]);
        redirect('/notifications');
    }
}
