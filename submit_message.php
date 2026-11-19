<?php
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/admin/includes/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store, max-age=0');

$respond = static function (int $status, array $payload): void {
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
};

if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'token') {
    $respond(200, ['csrf_token' => getCsrfToken()]);
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: GET, POST');
    $respond(405, ['message' => 'Method not allowed.']);
}

if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
    $respond(403, ['message' => 'Your session expired. Reload the page and try again.']);
}

$name = trim($_POST['name'] ?? '');
$email = trim($_POST['email'] ?? '');
$body = trim($_POST['message'] ?? '');
$honeypot = trim($_POST['website'] ?? '');

if ($honeypot !== '') {
    $respond(200, ['message' => 'Thank you. Your message has been sent.']);
}
if (isset($_SESSION['last_contact_message']) && time() - (int) $_SESSION['last_contact_message'] < 30) {
    $respond(429, ['message' => 'Please wait a moment before sending another message.']);
}
if ($name === '' || mb_strlen($name) < 2 || mb_strlen($name) > 150) {
    $respond(422, ['message' => 'Name must be between 2 and 150 characters.']);
}
if (mb_strlen($email) > 150 || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $respond(422, ['message' => 'Please enter a valid email address.']);
}
if (mb_strlen($body) < 10 || mb_strlen($body) > 5000) {
    $respond(422, ['message' => 'Message must be between 10 and 5000 characters.']);
}

try {
    $stmt = $pdo->prepare('INSERT INTO messages (sender_name, email, subject, message) VALUES (?, ?, ?, ?)');
    $stmt->execute([$name, $email, 'Portfolio contact', $body]);
    $_SESSION['last_contact_message'] = time();
    $respond(201, ['message' => 'Thank you. Your message has been sent.']);
} catch (Throwable $exception) {
    error_log('Contact form submission error: ' . $exception->getMessage());
    $respond(500, ['message' => 'Your message could not be sent. Please try again later.']);
}
