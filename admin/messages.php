<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$message = '';
$error = '';
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session token expired. Please reload the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        try {
            if (!$id) {
                throw new RuntimeException('Invalid message record.');
            }
            if ($action === 'toggle_read') {
                $isRead = filter_var($_POST['is_read'] ?? null, FILTER_VALIDATE_INT);
                if (!in_array($isRead, [0, 1], true)) {
                    throw new RuntimeException('Invalid message status.');
                }
                $stmt = $pdo->prepare('UPDATE messages SET is_read = ? WHERE id = ?');
                $stmt->execute([$isRead, $id]);
                $check = $pdo->prepare('SELECT id FROM messages WHERE id = ?');
                $check->execute([$id]);
                if (!$check->fetchColumn()) {
                    throw new RuntimeException('Message not found.');
                }
                $message = $isRead ? 'Message marked as read.' : 'Message marked as unread.';
            } elseif ($action === 'delete') {
                $stmt = $pdo->prepare('DELETE FROM messages WHERE id = ?');
                $stmt->execute([$id]);
                if ($stmt->rowCount() !== 1) {
                    throw new RuntimeException('Message not found.');
                }
                $message = 'Message deleted successfully.';
            } else {
                throw new RuntimeException('Invalid message action.');
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Message management error: ' . $exception->getMessage());
            $error = 'The message could not be updated. Please try again.';
        }
    }
}

$stmt = $pdo->query('SELECT id, sender_name, email, subject, message, is_read, created_at FROM messages ORDER BY is_read ASC, created_at DESC, id DESC');
$messages = $stmt->fetchAll();
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Contact Messages | Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; background: #f5f2ed; color: #2d211e; font-family: Arial, sans-serif; }
        main { max-width: 1100px; margin: 0 auto; }
        .topbar, .panel { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 4px 16px rgba(45, 33, 30, .07); }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        h1, h2 { margin-top: 0; }
        a { color: #5d4037; }
        .notice { padding: 12px 14px; background: #e9f5eb; border-radius: 6px; }
        .error { padding: 12px 14px; color: #8e1e1e; background: #fdecec; border-radius: 6px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 800px; }
        th, td { text-align: left; vertical-align: top; padding: 12px; border-bottom: 1px solid #eee7e3; }
        th { background: #faf8f6; }
        tr.unread { background: #fffaf0; }
        .message-body { white-space: pre-wrap; overflow-wrap: anywhere; max-width: 480px; }
        button { display: inline-block; border: 0; border-radius: 6px; padding: 9px 12px; background: #3e2723; color: #fff; font: inherit; cursor: pointer; }
        .danger { background: #a52a2a; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .actions form { margin: 0; }
        @media (max-width: 650px) { body { padding: 14px; } .topbar, .panel { padding: 18px; } .topbar { align-items: flex-start; flex-direction: column; } }
    </style>
</head>
<body>
<main>
    <header class="topbar"><div><h1>Contact Messages</h1><a href="dashboard.php">Back to Dashboard</a></div></header>
    <section class="panel" aria-labelledby="inbox-title">
        <h2 id="inbox-title">Inbox</h2>
        <?php if ($message !== ''): ?><p class="notice" role="status"><?= $escape($message) ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
        <?php if (!$messages): ?><p>No contact messages yet.</p><?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Status</th><th>Sender</th><th>Subject</th><th>Message</th><th>Received</th><th>Actions</th></tr></thead>
                <tbody><?php foreach ($messages as $item): ?>
                    <tr class="<?= (int) $item['is_read'] === 0 ? 'unread' : '' ?>">
                        <td><?= (int) $item['is_read'] === 1 ? 'Read' : 'Unread' ?></td>
                        <td><strong><?= $escape($item['sender_name']) ?></strong><br><a href="mailto:<?= $escape($item['email']) ?>"><?= $escape($item['email']) ?></a></td>
                        <td><?= $escape($item['subject']) ?></td>
                        <td><div class="message-body"><?= $escape($item['message']) ?></div></td>
                        <td><?= $escape(date('M j, Y g:i a', strtotime($item['created_at']))) ?></td>
                        <td><div class="actions">
                            <form method="post"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="toggle_read"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><input type="hidden" name="is_read" value="<?= (int) $item['is_read'] === 1 ? 0 : 1 ?>"><button type="submit"><?= (int) $item['is_read'] === 1 ? 'Mark unread' : 'Mark read' ?></button></form>
                            <form method="post" onsubmit="return confirm('Delete this message?')"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $item['id'] ?>"><button type="submit" class="danger">Delete</button></form>
                        </div></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
