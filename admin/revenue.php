<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('Location: admin_login.php');
    exit();
}

$total_company = 0;
$total_user = 0;
$total_completed = 0;
$total_pending = 0;
$total_amount = 0.0;

if ($q = mysqli_query($con, "SELECT COALESCE(SUM(CASE WHEN status='completed' THEN amount ELSE 0 END),0) t, COUNT(*) c FROM payments")) {
    $row = mysqli_fetch_assoc($q);
    $total_amount = (float)$row['t'];
    $total_completed = (int)$row['c'];
}
if ($q = mysqli_query($con, "SELECT COUNT(*) c FROM payments WHERE status='completed' AND payer_type='company'")) $total_company = (int)mysqli_fetch_assoc($q)['c'];
if ($q = mysqli_query($con, "SELECT COUNT(*) c FROM payments WHERE status='completed' AND payer_type='user'")) $total_user = (int)mysqli_fetch_assoc($q)['c'];

$by_purpose = [];
$qp = mysqli_query($con, "SELECT purpose, COALESCE(SUM(amount),0) amt, COUNT(*) cnt FROM payments WHERE status='completed' GROUP BY purpose ORDER BY amt DESC");
if ($qp) while ($r = mysqli_fetch_assoc($qp)) $by_purpose[] = $r;

$monthly = [];
$qm = mysqli_query($con, "SELECT DATE_FORMAT(completed_at,'%Y-%m') ym, COALESCE(SUM(amount),0) amt, COUNT(*) cnt FROM payments WHERE status='completed' AND completed_at IS NOT NULL GROUP BY ym ORDER BY ym DESC LIMIT 12");
if ($qm) while ($r = mysqli_fetch_assoc($qm)) $monthly[] = $r;

include 'header.php';
?>

<style>
    .rv-wrap { padding: 0 0 40px; }
    .rv-hero {
        position: relative; margin-top: -72px; padding: 96px 0 84px;
        background: linear-gradient(120deg, #1a56db 0%, #0ea5e9 55%, #0ea5e9 120%);
        overflow: hidden;
    }
    .rv-hero::before, .rv-hero::after {
        content: ''; position: absolute; border-radius: 50%; background: rgba(255,255,255,.08);
    }
    .rv-hero::before { width: 400px; height: 400px; top: -180px; right: -80px; }
    .rv-hero::after { width: 260px; height: 260px; bottom: -120px; left: -60px; }
    .rv-hero .container { position: relative; z-index: 2; }
    .rv-hero h1 { color: #fff; font-weight: 800; font-size: 2rem; margin: 0; font-family: 'Sora', sans-serif; }
    .rv-hero p { color: rgba(255,255,255,.85); margin: 8px 0 0; }

    .rv-gross {
        margin-top: -34px; position: relative; z-index: 5;
        background: #fff; border-radius: 16px; padding: 26px 30px;
        box-shadow: 0 12px 35px rgba(15,23,42,.1); border: 1px solid rgba(226,232,240,.6);
    }
    [data-theme="dark"] .rv-gross { background: var(--an-card); border-color: var(--an-border); }
    .rv-gross small { color: var(--an-text-muted); font-weight: 600; text-transform: uppercase; letter-spacing: .05em; font-size: .75rem; }
    .rv-gross h2 { font-size: 2.4rem; font-weight: 800; margin: 4px 0 0; color: #059669; }

    .rv-stat-card {
        background: var(--an-bg); border: 1px solid var(--an-border); border-radius: var(--an-radius);
        padding: 20px 24px; display: flex; align-items: center; gap: 16px; backdrop-filter: blur(14px);
    }
    .rv-stat-card .ic {
        width: 48px; height: 48px; border-radius: 12px;
        display: flex; align-items: center; justify-content: center; font-size: 1.15rem; flex-shrink: 0;
    }
    .rv-stat-card h3 { margin: 0; font-size: 1.35rem; font-weight: 800; }
    .rv-stat-card p { margin: 2px 0 0; color: var(--an-text-muted); font-size: .82rem; font-weight: 600; }

    .rv-card {
        background: var(--an-bg); border: 1px solid var(--an-border); border-radius: var(--an-radius);
        padding: 24px; backdrop-filter: blur(14px);
    }
    .rv-card h5 { font-weight: 800; margin-bottom: 18px; font-family: 'Sora', sans-serif; }
    .rv-bar { height: 10px; border-radius: 999px; background: rgba(59,130,246,.12); overflow: hidden; }
    .rv-bar > div { height: 100%; border-radius: 999px; background: linear-gradient(90deg,#1a56db,#0ea5e9); }
</style>

<div class="rv-wrap">
    <div class="rv-hero">
        <div class="container">
            <h1><i class="fas fa-chart-line"></i> Revenue Dashboard</h1>
            <p>Track all payments, subscriptions and platform earnings.</p>
        </div>
    </div>

    <div class="container">
        <div class="rv-gross mb-4">
            <small>Total Platform Revenue (Completed)</small>
            <h2>&#2547; <?php echo number_format($total_amount, 2); ?></h2>
        </div>

        <div class="row mb-4">
            <div class="col-md-4 col-6 mb-3">
                <div class="rv-stat-card">
                    <div class="ic" style="background:rgba(5,150,105,.14);color:#059669;"><i class="fas fa-building"></i></div>
                    <div><h3><?php echo $total_company; ?></h3><p>Company Payments</p></div>
                </div>
            </div>
            <div class="col-md-4 col-6 mb-3">
                <div class="rv-stat-card">
                    <div class="ic" style="background:rgba(59,130,246,.12);color:#1a56db;"><i class="fas fa-user"></i></div>
                    <div><h3><?php echo $total_user; ?></h3><p>User Payments</p></div>
                </div>
            </div>
            <div class="col-md-4 col-6 mb-3">
                <div class="rv-stat-card">
                    <div class="ic" style="background:rgba(217,119,6,.14);color:#b45309;"><i class="fas fa-receipt"></i></div>
                    <div><h3><?php echo $total_completed; ?></h3><p>Total Transactions</p></div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-5 mb-4">
                <div class="rv-card h-100">
                    <h5>Revenue by Purpose</h5>
                    <?php if (!$by_purpose): ?>
                        <p class="text-muted">No completed payments yet.</p>
                    <?php else:
                        $max = max(array_column($by_purpose, 'amt')) ?: 1;
                        foreach ($by_purpose as $p): ?>
                            <div class="mb-3">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                    <span style="font-weight:700;font-size:.88rem;"><?php echo ucwords(str_replace('_', ' ', $p['purpose'])); ?></span>
                                    <span style="font-size:.82rem;color:var(--an-text-muted);">
                                        &#2547; <?php echo number_format((float)$p['amt']); ?> <small>(<?php echo $p['cnt']; ?>)</small>
                                    </span>
                                </div>
                                <div class="rv-bar"><div style="width:<?php echo round(((float)$p['amt'] / $max) * 100); ?>%;"></div></div>
                            </div>
                        <?php endforeach; endif; ?>
                </div>
            </div>
            <div class="col-lg-7 mb-4">
                <div class="rv-card h-100">
                    <h5>Monthly Revenue (Last 12 months)</h5>
                    <?php if (!$monthly): ?>
                        <p class="text-muted">No completed payments yet.</p>
                    <?php else:
                        $mmax = max(array_column($monthly, 'amt')) ?: 1;
                        foreach ($monthly as $mo): ?>
                            <div class="mb-3">
                                <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:6px;">
                                    <span style="font-weight:700;font-size:.88rem;"><?php echo date('M Y', strtotime($mo['ym'] . '-01')); ?></span>
                                    <span style="font-size:.82rem;color:var(--an-text-muted);">
                                        &#2547; <?php echo number_format((float)$mo['amt']); ?> <small>(<?php echo $mo['cnt']; ?> tx)</small>
                                    </span>
                                </div>
                                <div class="rv-bar"><div style="width:<?php echo round(((float)$mo['amt'] / $mmax) * 100); ?>%;"></div></div>
                            </div>
                        <?php endforeach; endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>