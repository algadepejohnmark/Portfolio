<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$defaults = [
    'site_title' => 'John Mark Algadepe | Portfolio',
    'logo_text' => 'JM Algadepe',
    'default_theme' => 'light',
    'accent_color' => '#3e2723',
    'footer_text' => 'JM Algadepe. All rights reserved.',
];
$labels = [
    'site_title' => 'Website title',
    'logo_text' => 'Navigation logo text',
    'default_theme' => 'Default color theme',
    'accent_color' => 'Accent color',
    'footer_text' => 'Footer text',
];
$settings = $defaults;
$message = '';
$error = '';
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

$rows = $pdo->query('SELECT setting_key, setting_value FROM website_settings')->fetchAll();
foreach ($rows as $row) {
    if (array_key_exists($row['setting_key'], $settings)) {
        $settings[$row['setting_key']] = $row['setting_value'] ?? $defaults[$row['setting_key']];
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session token expired. Please reload the page and try again.';
    } else {
        $submitted = [];
        foreach ($defaults as $key => $default) {
            $submitted[$key] = trim((string) ($_POST[$key] ?? ''));
        }

        if ($submitted['site_title'] === '' || mb_strlen($submitted['site_title']) > 100) {
            $error = 'Website title is required and must be no more than 100 characters.';
        } elseif ($submitted['logo_text'] === '' || mb_strlen($submitted['logo_text']) > 50) {
            $error = 'Logo text is required and must be no more than 50 characters.';
        } elseif (!in_array($submitted['default_theme'], ['light', 'dark'], true)) {
            $error = 'Choose a valid default theme.';
        } elseif (!preg_match('/\A#[0-9a-fA-F]{6}\z/', $submitted['accent_color'])) {
            $error = 'Accent color must be a six-digit hex color such as #3e2723.';
        } elseif (mb_strlen($submitted['footer_text']) > 255) {
            $error = 'Footer text must be no more than 255 characters.';
        }

        if ($error === '') {
            try {
                $pdo->beginTransaction();
                $find = $pdo->prepare('SELECT id FROM website_settings WHERE setting_key = ? ORDER BY id LIMIT 1');
                $update = $pdo->prepare('UPDATE website_settings SET setting_value = ? WHERE setting_key = ?');
                $insert = $pdo->prepare('INSERT INTO website_settings (setting_key, setting_value) VALUES (?, ?)');
                foreach ($submitted as $key => $value) {
                    $find->execute([$key]);
                    if ($find->fetchColumn()) {
                        $update->execute([$value, $key]);
                    } else {
                        $insert->execute([$key, $value]);
                    }
                }
                $pdo->commit();
                $settings = $submitted;
                $message = 'Website settings saved successfully.';
            } catch (Throwable $exception) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('Website settings save error: ' . $exception->getMessage());
                $error = 'Website settings could not be saved. Please try again.';
                $settings = $submitted;
            }
        } else {
            $settings = $submitted;
        }
    }
}

$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Settings | Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; background: #f5f2ed; color: #2d211e; font-family: Arial, sans-serif; }
        main { max-width: 900px; margin: 0 auto; }
        .topbar, .panel { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 4px 16px rgba(45, 33, 30, .07); }
        h1, h2 { margin-top: 0; }
        a { color: #5d4037; }
        label { display: block; font-weight: 700; margin: 0 0 6px; }
        input, select { width: 100%; padding: 10px 12px; margin-bottom: 18px; border: 1px solid #cfc6c1; border-radius: 6px; font: inherit; }
        input[type="color"] { width: 70px; height: 42px; padding: 4px; }
        button { display: inline-block; border: 0; border-radius: 6px; padding: 10px 15px; background: #3e2723; color: #fff; font: inherit; cursor: pointer; }
        .notice { padding: 12px 14px; background: #e9f5eb; border-radius: 6px; }
        .error { padding: 12px 14px; color: #8e1e1e; background: #fdecec; border-radius: 6px; }
        .hint { color: #6b625e; font-size: .92rem; }
        @media (max-width: 650px) { body { padding: 14px; } .topbar, .panel { padding: 18px; } }
    </style>
</head>
<body>
<main>
    <header class="topbar"><h1>Website Settings</h1><a href="dashboard.php">Back to Dashboard</a></header>
    <section class="panel" aria-labelledby="settings-title">
        <h2 id="settings-title">Portfolio appearance</h2>
        <p class="hint">These settings are used by the public portfolio pages.</p>
        <?php if ($message !== ''): ?><p class="notice" role="status"><?= $escape($message) ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
        <form method="post">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <label for="site_title"><?= $escape($labels['site_title']) ?></label>
            <input id="site_title" name="site_title" maxlength="100" required value="<?= $escape($settings['site_title']) ?>">

            <label for="logo_text"><?= $escape($labels['logo_text']) ?></label>
            <input id="logo_text" name="logo_text" maxlength="50" required value="<?= $escape($settings['logo_text']) ?>">

            <label for="default_theme"><?= $escape($labels['default_theme']) ?></label>
            <select id="default_theme" name="default_theme"><option value="light" <?= $settings['default_theme'] === 'light' ? 'selected' : '' ?>>Light</option><option value="dark" <?= $settings['default_theme'] === 'dark' ? 'selected' : '' ?>>Dark</option></select>

            <label for="accent_color"><?= $escape($labels['accent_color']) ?></label>
            <input type="color" id="accent_color" name="accent_color" value="<?= $escape($settings['accent_color']) ?>">

            <label for="footer_text"><?= $escape($labels['footer_text']) ?></label>
            <input id="footer_text" name="footer_text" maxlength="255" value="<?= $escape($settings['footer_text']) ?>">

            <button type="submit">Save Settings</button>
        </form>
    </section>
</main>
</body>
</html>
