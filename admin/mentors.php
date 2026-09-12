<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('Location: admin_login.php');
    exit();
}

$msg_success = '';
$msg_error = '';

if (isset($_GET['action'], $_GET['id'])) {
    $mid = (int)$_GET['id'];
    $action = $_GET['action'];
    $allowed = ['approve', 'reject', 'suspend', 'delete'];
    if (in_array($action, $allowed, true)) {
        if ($action === 'delete') {
            mysqli_query($con, "DELETE FROM mentors WHERE id = $mid");
            mysqli_query($con, "DELETE FROM mentor_slots WHERE mentor_id = $mid");
            mysqli_query($con, "DELETE FROM mentor_earnings WHERE mentor_id = $mid");
            mysqli_query($con, "DELETE FROM grooming_sessions WHERE mentor_id = $mid");
            $msg_success = 'Mentor deleted permanently.';
        } else {
            $status_map = ['approve' => 'approved', 'reject' => 'rejected', 'suspend' => 'suspended'];
            $stmt = mysqli_prepare($con, "UPDATE mentors SET status = ? WHERE id = ?");
            mysqli_stmt_bind_param($stmt, 'si', $status_map[$action], $mid);
            mysqli_stmt_execute($stmt);
            $msg_success = 'Mentor status updated to ' . ucwords($status_map[$action]) . '.';
        }
    }
}

$records_per_page = 10;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;

$tr = mysqli_query($con, "SELECT COUNT(*) AS c FROM mentors");
$total_records = $tr ? (int)mysqli_fetch_assoc($tr)['c'] : 0;
$total_pages = max(1, (int)ceil($total_records / $records_per_page));
if ($page > $total_pages) $page = $total_pages;
$start_from = ($page - 1) * $records_per_page;

$rows = [];
$qr = mysqli_query($con, "SELECT m.*,
    (SELECT COUNT(*) FROM grooming_sessions gs WHERE gs.mentor_id = m.id) AS session_count,
    (SELECT COALESCE(SUM(amount),0) FROM mentor_earnings me WHERE me.mentor_id = m.id AND me.type='earning' AND me.status='paid') AS total_earned
    FROM mentors m ORDER BY m.id DESC LIMIT $start_from, $records_per_page");
if ($qr) {
    while ($r = mysqli_fetch_assoc($qr)) $rows[] = $r;
}

$pending_cnt = 0;
if ($q = mysqli_query($con, "SELECT COUNT(*) c FROM mentors WHERE status='pending'")) $pending_cnt = (int)mysqli_fetch_assoc($q)['c'];

function m_avatar_style($id) {
    $grads = [
        'linear-gradient(135deg,#3b82f6,#60a5fa)',
        'linear-gradient(135deg,#06b6d4,#38bdf8)',
        'linear-gradient(135deg,#059669,#34d399)',
        'linear-gradient(135deg,#d97706,#fbbf24)',
        'linear-gradient(135deg,#ec4899,#f472b6)',
        'linear-gradient(135deg,#8b5cf6,#a78bfa)',
    ];
    return $grads[$id % count($grads)];
}

include 'header.php';
?>

<style>
    .mt-wrap { padding: 0 0 40px; }
    .mt-hero {
        position: relative;
        margin-top: -72px;
        padding: 96px 0 84px;
        background: linear-gradient(120deg, #1a56db 0%, #0ea5e9 55%, #0ea5e9 120%);
        overflow: hidden;
    }
    .mt-hero::before, .mt-hero::after {
        content: '';
        position: absolute;
        border-radius: 50%;
        background: rgba(255,255,255,.08);
    }
    .mt-hero::before { width: 400px; height: 400px; top: -180px; right: -80px; }
    .mt-hero::after { width: 260px; height: 260px; bottom: -120px; left: -60px; }
    .mt-hero .container { position: relative; z-index: 2; }
    .mt-hero h1 { color: #fff; font-weight: 800; font-size: 2rem; margin: 0; font-family: 'Sora', sans-serif; }
    .mt-hero p { color: rgba(255,255,255,.85); margin: 8px 0 0; }

    .mt-stats { margin-top: -40px; position: relative; z-index: 5; }
    .mt-stat-card {
        background: #fff; border-radius: 16px; padding: 20px 24px;
        box-shadow: 0 10px 30px rgba(15,23,42,.08);
        display: flex; align-items: center; gap: 16px; border: 1px solid rgba(226,232,240,.6);
    }
    .mt-stat-card .ic {
        width: 48px; height: 48px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.15rem; flex-shrink: 0;
    }
    .mt-stat-card h3 { margin: 0; font-size: 1.5rem; font-weight: 800; }
    .mt-stat-card p { margin: 2px 0 0; color: var(--an-text-muted); font-size: .82rem; font-weight: 600; }

    .mt-card {
        background: var(--an-bg); border-radius: var(--an-radius);
        border: 1px solid var(--an-border); padding: 26px 0 12px; backdrop-filter: blur(14px);
    }
    .mt-status {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 5px 12px; border-radius: 999px; font-size: .75rem; font-weight: 700;
    }
    .mt-status.pending { background: rgba(217,119,6,.14); color: #b45309; }
    .mt-status.approved { background: rgba(5,150,105,.14); color: #059669; }
    .mt-status.rejected, .mt-status.suspended { background: rgba(220,38,38,.12); color: #dc2626; }
    .mt-badge {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(59,130,246,.1); color: #1a56db;
        padding: 4px 10px; border-radius: 8px; font-size: .75rem; font-weight: 700;
    }
    .mt-actions a {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 7px 14px; border-radius: 10px; font-size: .8rem; font-weight: 700;
        text-decoration: none; transition: all .25s;
    }
    .mt-actions a:hover { transform: translateY(-2px); }
    .mt-pagination a, .mt-pagination span {
        display: inline-flex; align-items: center; justify-content: center;
        min-width: 38px; height: 38px; padding: 0 10px; margin: 0 3px;
        border-radius: 10px; text-decoration: none; font-weight: 700; font-size: .85rem;
        border: 1px solid var(--an-border); color: var(--an-text-muted);
    }
    .mt-pagination .active { background: #1a56db; color: #fff; border-color: #1a56db; }
    [data-theme="dark"] .mt-stat-card {
        background: var(--an-card); border-color: var(--an-border);
    }
</style>

<div class="mt-wrap">
    <div class="mt-hero">
        <div class="container">
            <h1><i class="fas fa-chalkboard-user"></i> Mentor Management</h1>
            <p>Approve, review and manage all mentors on the platform.</p>
        </div>
    </div>

    <div class="container">
        <?php if ($msg_success): ?>
            <div class="alert alert-success mt-3"><?php echo htmlspecialchars($msg_success); ?></div>
        <?php endif; ?>

        <div class="row mt-stats mb-4">
            <div class="col-md-3 col-6 mb-3">
                <div class="mt-stat-card">
                    <div class="ic" style="background:rgba(59,130,246,.12);color:#1a56db;"><i class="fas fa-users"></i></div>
                    <div><h3><?php echo $total_records; ?></h3><p>Total Mentors</p></div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="mt-stat-card">
                    <div class="ic" style="background:rgba(217,119,6,.14);color:#b45309;"><i class="fas fa-clock"></i></div>
                    <div><h3><?php echo $pending_cnt; ?></h3><p>Pending</p></div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="mt-stat-card">
                    <div class="ic" style="background:rgba(5,150,105,.14);color:#059669;"><i class="fas fa-check-circle"></i></div>
                    <div><h3><?php echo $total_records - $pending_cnt; ?></h3><p>Active</p></div>
                </div>
            </div>
            <div class="col-md-3 col-6 mb-3">
                <div class="mt-stat-card">
                    <div class="ic" style="background:rgba(6,182,212,.12);color:#0ea5e9;"><i class="fas fa-star"></i></div>
                    <div><h3>&#2547;</h3><p>BDT Revenue</p></div>
                </div>
            </div>
        </div>

        <div class="mt-card">
            <div class="table-responsive">
                <table class="table table-hover mt-0">
                    <thead>
                        <tr>
                            <th>Mentor</th>
                            <th>Category</th>
                            <th>Rate</th>
                            <th>Sessions</th>
                            <th>Earned</th>
                            <th>Rating</th>
                            <th>Status</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!$rows): ?>
                            <tr><td colspan="8" class="text-center py-5 text-muted">No mentors found yet.</td></tr>
                        <?php else: foreach ($rows as $m): ?>
                            <tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:12px;">
                                        <?php $initials = strtoupper(substr($m['name'], 0, 1)); ?>
                                        <div style="width:42px;height:42px;border-radius:50%;background:<?php echo m_avatar_style($m['id']); ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-weight:800;">
                                            <?php echo htmlspecialchars($initials); ?>
                                        </div>
                                        <div>
                                            <strong><?php echo htmlspecialchars($m['name']); ?></strong>
                                            <div style="font-size:.78rem;color:var(--an-text-muted);">
                                                <?php echo htmlspecialchars($m['email']); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="mt-badge"><i class="fas fa-tag"></i> <?php echo htmlspecialchars($m['category']); ?></span></td>
                                <td>&#2547; <?php echo (int)$m['hourly_rate']; ?>/hr</td>
                                <td><?php echo (int)$m['session_count']; ?></td>
                                <td>&#2547; <?php echo number_format((float)$m['total_earned']); ?></td>
                                <td>
                                    <i class="fas fa-star" style="color:#fbbf24;"></i>
                                    <?php echo number_format((float)$m['rating'], 1); ?>
                                    <small class="text-muted">(<?php echo (int)$m['total_reviews']; ?>)</small>
                                </td>
                                <td>
                                    <span class="mt-status <?php echo htmlspecialchars($m['status']); ?>">
                                        <i class="fas fa-circle" style="font-size:.5rem;"></i>
                                        <?php echo ucwords($m['status']); ?>
                                    </span>
                                </td>
                                <td class="mt-actions">
                                    <?php if ($m['status'] !== 'approved'): ?>
                                        <a href="mentors.php?action=approve&amp;id=<?php echo $m['id']; ?>" style="background:rgba(5,150,105,.12);color:#059669;"><i class="fas fa-check"></i> Approve</a>
                                    <?php endif; ?>
                                    <?php if ($m['status'] === 'pending'): ?>
                                        <a href="mentors.php?action=reject&amp;id=<?php echo $m['id']; ?>" style="background:rgba(220,38,38,.1);color:#dc2626;"><i class="fas fa-times"></i> Reject</a>
                                    <?php endif; ?>
                                    <?php if ($m['status'] === 'approved'): ?>
                                        <a href="mentors.php?action=suspend&amp;id=<?php echo $m['id']; ?>" style="background:rgba(217,119,6,.14);color:#b45309;"><i class="fas fa-pause"></i> Suspend</a>
                                    <?php endif; ?>
                                    <?php if ($m['status'] === 'suspended'): ?>
                                        <a href="mentors.php?action=approve&amp;id=<?php echo $m['id']; ?>" style="background:rgba(5,150,105,.12);color:#059669;"><i class="fas fa-play"></i> Reinstate</a>
                                    <?php endif; ?>
                                    <a href="mentors.php?action=delete&amp;id=<?php echo $m['id']; ?>" onclick="return confirm('Delete this mentor permanently?');" style="background:rgba(220,38,38,.1);color:#dc2626;"><i class="fas fa-trash"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($total_pages > 1): ?>
                <div class="mt-pagination text-center mt-3">
                    <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="mentors.php?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

</body>
</html>