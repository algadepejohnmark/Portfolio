<?php
require_once __DIR__ . '/includes/db.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

try {
    $settings = [];
    foreach ($pdo->query('SELECT setting_key, setting_value FROM website_settings')->fetchAll() as $row) {
        if (in_array($row['setting_key'], ['site_title', 'logo_text', 'default_theme', 'accent_color', 'footer_text'], true)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }

    $profileStmt = $pdo->query('SELECT full_name, title, introduction, biography, email, profile_image FROM personal_info ORDER BY id DESC LIMIT 1');
    $profile = $profileStmt->fetch() ?: null;
    if ($profile && $profile['profile_image']) {
        $profileFilename = basename($profile['profile_image']);
        $profile['profile_image'] = preg_match('/\A[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)\z/i', $profileFilename)
            ? 'images/' . $profileFilename
            : null;
    }

    $projectColumns = $pdo->query('SHOW COLUMNS FROM projects')->fetchAll(PDO::FETCH_COLUMN);
    $hasProjectCategory = in_array('category', $projectColumns, true);
    $projects = $pdo->query('SELECT * FROM projects ORDER BY ' . (in_array('sort_order', $projectColumns, true) ? 'sort_order ASC, id DESC' : 'id DESC'))->fetchAll();
    foreach ($projects as &$project) {
        $project['tech_stack'] = $project['tech_stack'] ?? $project['technologies'] ?? '';
        $project['category'] = $hasProjectCategory ? (string) $project['category'] : '';
        $image = basename((string) ($project['image'] ?? ''));
        if (preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $image)) {
            $project['image_url'] = 'uploads/projects/' . $image;
        } elseif (preg_match('/\A[a-zA-Z0-9_-]+\.(?:jpg|jpeg|png|webp)\z/i', $image)) {
            $project['image_url'] = 'images/' . $image;
        } else {
            $project['image_url'] = '';
        }
    }
    unset($project);

    $skills = [];
    foreach ($pdo->query('SELECT skill_name, category, proficiency FROM skills ORDER BY id ASC')->fetchAll() as $skill) {
        $categoryText = strtolower((string) $skill['category']);
        if (str_contains($categoryText, 'front') || str_contains($categoryText, 'web')) {
            $category = 'frontend';
        } elseif (str_contains($categoryText, 'workflow') || str_contains($categoryText, 'tool')) {
            $category = 'workflow';
        } else {
            $category = 'programming';
        }
        $proficiency = strtolower(trim((string) $skill['proficiency']));
        $level = is_numeric($proficiency)
            ? max(0, min(100, (int) $proficiency))
            : (['beginner' => 40, 'intermediate' => 65, 'advanced' => 85, 'expert' => 95][$proficiency] ?? 50);
        $skills[] = ['name' => $skill['skill_name'], 'category' => $category, 'level' => $level];
    }

    $education = $pdo->query('SELECT school, course, start_year, end_year, description FROM education ORDER BY start_year DESC, id DESC')->fetchAll();
    $experience = $pdo->query('SELECT position, company, start_date, end_date, description FROM experience ORDER BY start_date DESC, id DESC')->fetchAll();
    $achievements = $pdo->query('SELECT title, organization, date_received, description, certificate_image FROM achievements ORDER BY date_received DESC, id DESC')->fetchAll();
    foreach ($achievements as &$achievement) {
        $filename = basename((string) ($achievement['certificate_image'] ?? ''));
        $achievement['image_url'] = preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename)
            ? 'uploads/achievements/' . $filename
            : '';
    }
    unset($achievement);

    $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    $interests = in_array('interests', $tables, true)
        ? $pdo->query('SELECT name FROM interests ORDER BY id ASC')->fetchAll(PDO::FETCH_COLUMN)
        : [];
    echo json_encode([
        'settings' => $settings,
        'profile' => $profile,
        'projects' => $projects,
        'projects_have_categories' => $hasProjectCategory,
        'skills' => $skills,
        'education' => $education,
        'experience' => $experience,
        'achievements' => $achievements,
        'interests' => $interests,
    ], JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
} catch (Throwable $exception) {
    error_log('Public portfolio content error: ' . $exception->getMessage());
    http_response_code(500);
    echo json_encode(['message' => 'Portfolio content is temporarily unavailable.']);
}
