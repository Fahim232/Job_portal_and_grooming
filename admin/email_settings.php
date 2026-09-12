<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('Location: admin_login.php');
    exit();
}

$msg_success = '';
$msg_error = '';

$DEFAULTS = [
    'smtp_host'       => 'smtp.gmail.com',
    'smtp_port'       => '587',
    'smtp_username'   => '',
    'smtp_password'   => '',
    'smtp_from_email' => '',
    'smtp_from_name'  => 'NovaHire',
    'smtp_encryption' => 'tls',
];

$settings = $DEFAULTS;
if ($q = @mysqli_query($con, "SHOW TABLES LIKE 'site_settings'")) {
    if (mysqli_num_rows($q) > 0) {
        if ($rs = mysqli_query($con, "SELECT setting_key, setting_value FROM site_settings WHERE setting_key LIKE 'smtp_%'")) {
            while ($row = mysqli_fetch_assoc($rs)) $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_smtp'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg_error = 'Your session expired. Please refresh and try again.';
    } else {
        $payload = [
            'smtp_host'       => trim($_POST['smtp_host'] ?? 'smtp.gmail.com'),
            'smtp_port'       => (string)max(1, (int)($_POST['smtp_port'] ?? 587)),
            'smtp_username'   => trim($_POST['smtp_username'] ?? ''),
            'smtp_password'   => trim($_POST['smtp_password'] ?? ''),
            'smtp_from_email' => trim($_POST['smtp_from_email'] ?? ''),
            'smtp_from_name'  => trim($_POST['smtp_from_name'] ?? 'NovaHire'),
            'smtp_encryption' => in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_encryption'] : 'tls',
        ];

        mysqli_query($con, "CREATE TABLE IF NOT EXISTS site_settings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            setting_key VARCHAR(100) NOT NULL UNIQUE,
            setting_value TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        foreach ($payload as $key => $value) {
            $stmt = mysqli_prepare($con, "INSERT INTO site_settings (setting_key, setting_value) VALUES (?, ?)
                                          ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
            mysqli_stmt_execute($stmt);
        }
        $settings = array_merge($settings, $payload);
        $msg_success = 'SMTP email settings saved successfully.';
    }
}

require_once __DIR__ . '/../includes/mail.php';
$configured = function_exists('get_smtp_config') ? ((function_exists('smtp_is_configured') && smtp_is_configured()) || ($settings['smtp_host'] !== '' && ($settings['smtp_username'] !== '' || $settings['smtp_from_email'] !== ''))) : false;

include 'header.php';
?>

<style>
    .em-wrap { padding: 0 0 40px; }
    .em-hero {
        position: relative; margin-top: -72px; padding: 96px 0 84px;
        background: linear-gradient(120deg, #6d28d9 0%, #9333ea 55%, #c026d3 120%);
        overflow: hidden;
    }
    .em-hero::before, .em-hero::after {
        content: ''; position: absolute; border-radius: 50%; background: rgba(255,255,255,.08);
    }
    .em-hero::before { width: 400px; height: 400px; top: -180px; right: -80px; }
    .em-hero::after { width: 260px; height: 260px; bottom: -120px; left: -60px; }
    .em-hero .container { position: relative; z-index: 2; }
    .em-hero h1 { color: #fff; font-weight: 800; font-size: 2rem; margin: 0; font-family: 'Sora', sans-serif; }
    .em-hero p { color: rgba(255,255,255,.85); margin: 8px 0 0; }

    .em-status {
        margin-top: -34px; position: relative; z-index: 5;
        background: #fff; border-radius: 14px; padding: 18px 26px;
        box-shadow: 0 10px 30px rgba(15,23,42,.1); border: 1px solid rgba(226,232,240,.6);
        display: flex; align-items: center; gap: 14px;
    }
    [data-theme="dark"] .em-status { background: var(--an-card); border-color: var(--an-border); }
    .em-status .dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    .em-status .on { background: #059669; box-shadow: 0 0 0 4px rgba(5,150,105,.18); }
    .em-status .off { background: #94a3b8; box-shadow: 0 0 0 4px rgba(148,163,184,.18); }

    .em-card {
        background: var(--an-bg); border: 1px solid var(--an-border); border-radius: var(--an-radius);
        padding: 28px; backdrop-filter: blur(14px); margin-bottom: 24px;
    }
    .em-card h5 { font-weight: 800; margin-bottom: 6px; font-family: 'Sora', sans-serif; }
    .em-card .em-sub { color: var(--an-text-muted); font-size: .85rem; margin-bottom: 22px; }
</style>

<div class="em-wrap">
    <div class="em-hero">
        <div class="container">
            <h1><i class="fas fa-envelope"></i> Email Settings</h1>
            <p>Configure SMTP for transactional emails (PHPMailer).</p>
        </div>
    </div>

    <div class="container">
        <div class="em-status mb-4">
            <span class="dot <?php echo $configured ? 'on' : 'off'; ?>"></span>
            <div>
                <strong><?php echo $configured ? 'SMTP Configured' : 'SMTP Not Configured'; ?></strong>
                <div style="font-size:.82rem;color:var(--an-text-muted);">
                    <?php echo $configured ? 'Emails will be sent via SMTP.' : 'Uses PHP mail() fallback, or fill in SMTP details below.'; ?>
                </div>
            </div>
        </div>

        <?php if ($msg_success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($msg_success); ?></div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($msg_error); ?></div>
        <?php endif; ?>

        <div class="em-card">
            <h5><i class="fas fa-server"></i> SMTP Configuration</h5>
            <p class="em-sub">For Gmail, use an App Password instead of your regular password.</p>

            <form method="POST" action="email_settings.php">
                <?php echo csrf_field(); ?>
                <div class="row">
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="smtp_host"><strong>SMTP Host</strong></label>
                            <input type="text" class="form-control" name="smtp_host" id="smtp_host" value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="smtp_port"><strong>SMTP Port</strong></label>
                            <input type="number" class="form-control" name="smtp_port" id="smtp_port" value="<?php echo (int)$settings['smtp_port']; ?>">
                        </div>
                        <div class="form-group">
                            <label for="smtp_encryption"><strong>Encryption</strong></label>
                            <select name="smtp_encryption" id="smtp_encryption" class="form-control">
                                <option value="tls" <?php echo $settings['smtp_encryption'] === 'tls' ? 'selected' : ''; ?>>TLS (587)</option>
                                <option value="ssl" <?php echo $settings['smtp_encryption'] === 'ssl' ? 'selected' : ''; ?>>SSL (465)</option>
                                <option value="none" <?php echo $settings['smtp_encryption'] === 'none' ? 'selected' : ''; ?>>None</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="smtp_username"><strong>SMTP Username</strong></label>
                            <input type="text" class="form-control" name="smtp_username" id="smtp_username" value="<?php echo htmlspecialchars($settings['smtp_username']); ?>" autocomplete="off">
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-group">
                            <label for="smtp_password"><strong>SMTP Password / App Password</strong></label>
                            <input type="password" class="form-control" name="smtp_password" id="smtp_password" value="<?php echo htmlspecialchars($settings['smtp_password']); ?>" autocomplete="new-password">
                        </div>
                        <div class="form-group">
                            <label for="smtp_from_email"><strong>From Email</strong></label>
                            <input type="email" class="form-control" name="smtp_from_email" id="smtp_from_email" value="<?php echo htmlspecialchars($settings['smtp_from_email']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="smtp_from_name"><strong>From Name</strong></label>
                            <input type="text" class="form-control" name="smtp_from_name" id="smtp_from_name" value="<?php echo htmlspecialchars($settings['smtp_from_name']); ?>">
                        </div>
                        <button type="submit" name="save_smtp" class="btn btn-primary btn-lg mt-4"><i class="fas fa-save"></i> Save Settings</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>