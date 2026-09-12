<?php
require_once __DIR__ . '/../includes/bootstrap.php';

if (!isset($_SESSION['admin_username'])) {
    header('Location: admin_login.php');
    exit();
}

$msg_success = '';
$msg_error = '';

$DEFAULTS = [
    'provider'        => 'openai',
    'openai_api_key'  => '',
    'openai_model'    => 'gpt-3.5-turbo',
    'gemini_api_key'  => '',
    'gemini_model'    => 'gemini-1.5-flash',
    'llm_enabled'     => '0',
    'chatbot_name'    => 'AI Assistant',
    'request_timeout' => '30',
];

$settings = $DEFAULTS;
$has_table = false;
if ($q = @mysqli_query($con, "SHOW TABLES LIKE 'ai_settings'")) {
    if (mysqli_num_rows($q) > 0) {
        $has_table = true;
        if ($rs = mysqli_query($con, "SELECT setting_key, setting_value FROM ai_settings")) {
            while ($row = mysqli_fetch_assoc($rs)) $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
}

if (!$has_table) {
    mysqli_query($con, "CREATE TABLE IF NOT EXISTS ai_settings (
        id INT AUTO_INCREMENT PRIMARY KEY,
        setting_key VARCHAR(100) NOT NULL UNIQUE,
        setting_value TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $has_table = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_ai'])) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $msg_error = 'Your session expired. Please refresh and try again.';
    } else {
        $payload = [
            'provider'        => in_array($_POST['provider'] ?? '', ['openai', 'gemini', 'none'], true) ? $_POST['provider'] : 'openai',
            'openai_api_key'  => trim($_POST['openai_api_key'] ?? ''),
            'openai_model'    => trim($_POST['openai_model'] ?? 'gpt-3.5-turbo'),
            'gemini_api_key'  => trim($_POST['gemini_api_key'] ?? ''),
            'gemini_model'    => trim($_POST['gemini_model'] ?? 'gemini-1.5-flash'),
            'llm_enabled'     => isset($_POST['llm_enabled']) ? '1' : '0',
            'chatbot_name'    => trim($_POST['chatbot_name'] ?? 'AI Assistant'),
            'request_timeout' => max(5, min(120, (int)($_POST['request_timeout'] ?? 30))),
        ];
        foreach ($payload as $key => $value) {
            $stmt = mysqli_prepare($con, "INSERT INTO ai_settings (setting_key, setting_value) VALUES (?, ?)
                                          ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
            mysqli_stmt_bind_param($stmt, 'ss', $key, $value);
            mysqli_stmt_execute($stmt);
        }
        $settings = array_merge($settings, $payload);
        $msg_success = 'AI settings saved successfully.';
    }
}

$has_key = in_array($settings['provider'], ['openai', 'gemini'], true) && trim($settings[$settings['provider'] . '_api_key']) !== '';

include 'header.php';
?>

<style>
    .ai-wrap { padding: 0 0 40px; }
    .ai-hero {
        position: relative; margin-top: -72px; padding: 96px 0 84px;
        background: linear-gradient(120deg, #1a56db 0%, #0ea5e9 55%, #0ea5e9 120%);
        overflow: hidden;
    }
    .ai-hero::before, .ai-hero::after {
        content: ''; position: absolute; border-radius: 50%; background: rgba(255,255,255,.08);
    }
    .ai-hero::before { width: 400px; height: 400px; top: -180px; right: -80px; }
    .ai-hero::after { width: 260px; height: 260px; bottom: -120px; left: -60px; }
    .ai-hero .container { position: relative; z-index: 2; }
    .ai-hero h1 { color: #fff; font-weight: 800; font-size: 2rem; margin: 0; font-family: 'Sora', sans-serif; }
    .ai-hero p { color: rgba(255,255,255,.85); margin: 8px 0 0; }

    .ai-card {
        background: var(--an-bg); border: 1px solid var(--an-border); border-radius: var(--an-radius);
        padding: 28px; backdrop-filter: blur(14px); margin-bottom: 24px;
    }
    .ai-card h5 { font-weight: 800; margin-bottom: 6px; font-family: 'Sora', sans-serif; }
    .ai-card .ai-sub { color: var(--an-text-muted); font-size: .85rem; margin-bottom: 22px; }

    .ai-status {
        margin-top: -34px; position: relative; z-index: 5;
        background: #fff; border-radius: 14px; padding: 18px 26px;
        box-shadow: 0 10px 30px rgba(15,23,42,.1); border: 1px solid rgba(226,232,240,.6);
        display: flex; align-items: center; gap: 14px;
    }
    [data-theme="dark"] .ai-status { background: var(--an-card); border-color: var(--an-border); }
    .ai-status .dot { width: 12px; height: 12px; border-radius: 50%; flex-shrink: 0; }
    .ai-status .on { background: #059669; box-shadow: 0 0 0 4px rgba(5,150,105,.18); }
    .ai-status .off { background: #94a3b8; box-shadow: 0 0 0 4px rgba(148,163,184,.18); }

    .form-check-input:checked { background-color: #1a56db; border-color: #1a56db; }
</style>

<div class="ai-wrap">
    <div class="ai-hero">
        <div class="container">
            <h1><i class="fas fa-robot"></i> AI Settings</h1>
            <p>Configure the hybrid AI engine — OpenAI / Google Gemini.</p>
        </div>
    </div>

    <div class="container">
        <div class="ai-status mb-4">
            <span class="dot <?php echo $has_key ? 'on' : 'off'; ?>"></span>
            <div>
                <strong>
                    <?php
                    if ($has_key) {
                        echo 'LLM Ready';
                    } else {
                        echo 'Offline Mode';
                    }
                    if (isset($settings['llm_enabled']) && $settings['llm_enabled'] !== '1') {
                        echo ' (LLM disabled)';
                    }
                    ?>
                </strong>
                <div style="font-size:.82rem;color:var(--an-text-muted);">
                    <?php echo $has_key ? 'Enhanced AI responses are enabled.' : 'All AI tools still work without an API key.'; ?>
                </div>
            </div>
        </div>

        <?php if ($msg_success): ?>
            <div class="alert alert-success"><?php echo htmlspecialchars($msg_success); ?></div>
        <?php endif; ?>
        <?php if ($msg_error): ?>
            <div class="alert alert-danger"><?php echo htmlspecialchars($msg_error); ?></div>
        <?php endif; ?>

        <div class="ai-card">
            <h5><i class="fas fa-satellite-dish"></i> LLM Provider</h5>
            <p class="ai-sub">No API key required — all AI tools work offline. Connect a provider for enhanced responses.</p>

            <form method="POST" action="ai_settings.php">
                <?php echo csrf_field(); ?>
                <div class="row">
                    <div class="col-md-8">
                        <div class="form-group">
                            <label for="provider"><strong>Provider</strong></label>
                            <select name="provider" id="provider" class="form-control">
                                <option value="openai" <?php echo $settings['provider'] === 'openai' ? 'selected' : ''; ?>>OpenAI</option>
                                <option value="gemini" <?php echo $settings['provider'] === 'gemini' ? 'selected' : ''; ?>>Google Gemini</option>
                                <option value="none" <?php echo $settings['provider'] === 'none' ? 'selected' : ''; ?>>Offline Only</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label for="openai_api_key"><strong>OpenAI API Key</strong></label>
                            <input type="password" class="form-control" name="openai_api_key" id="openai_api_key" placeholder="sk-..." value="<?php echo htmlspecialchars($settings['openai_api_key']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="openai_model"><strong>OpenAI Model</strong></label>
                            <input type="text" class="form-control" name="openai_model" id="openai_model" value="<?php echo htmlspecialchars($settings['openai_model']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="gemini_api_key"><strong>Gemini API Key</strong></label>
                            <input type="password" class="form-control" name="gemini_api_key" id="gemini_api_key" placeholder="AIza..." value="<?php echo htmlspecialchars($settings['gemini_api_key']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="gemini_model"><strong>Gemini Model</strong></label>
                            <input type="text" class="form-control" name="gemini_model" id="gemini_model" value="<?php echo htmlspecialchars($settings['gemini_model']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="chatbot_name"><strong>Chatbot Name</strong></label>
                            <input type="text" class="form-control" name="chatbot_name" id="chatbot_name" value="<?php echo htmlspecialchars($settings['chatbot_name']); ?>">
                        </div>
                        <div class="form-group">
                            <label for="request_timeout"><strong>Request Timeout (seconds)</strong></label>
                            <input type="number" class="form-control" name="request_timeout" id="request_timeout" min="5" max="120" value="<?php echo (int)$settings['request_timeout']; ?>">
                        </div>
                        <div class="form-check mb-4">
                            <input type="checkbox" class="form-check-input" name="llm_enabled" id="llm_enabled" <?php echo $settings['llm_enabled'] === '1' ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="llm_enabled"><strong>Enable LLM</strong> (use API key when present)</label>
                        </div>
                        <button type="submit" name="save_ai" class="btn btn-primary btn-lg"><i class="fas fa-save"></i> Save Settings</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

</body>
</html>