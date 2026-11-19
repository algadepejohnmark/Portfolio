<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$message = '';
$error = '';
$achievement = null;
$formValues = ['title' => '', 'organization' => '', 'date_received' => '', 'description' => ''];
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

function uploadAchievementImage(array $file): string
{
    if (!isset($file['error']) || is_array($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException('Please select a valid certificate image.');
    }
    if (!is_uploaded_file($file['tmp_name']) || $file['size'] <= 0 || $file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Certificate image must be a valid file no larger than 2MB.');
    }

    $allowedTypes = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
    $imageInfo = @getimagesize($file['tmp_name']);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);
    if (!$imageInfo || !isset($allowedTypes[$mimeType]) || $imageInfo['mime'] !== $mimeType) {
        throw new RuntimeException('Please upload a valid JPG, PNG, or WebP certificate image.');
    }

    $uploadDir = __DIR__ . '/../uploads/achievements';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('The certificate upload directory is unavailable.');
    }
    $filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Certificate image upload failed.');
    }
    return $filename;
}

function deleteUploadedAchievementImage(?string $filename): void
{
    if (!$filename || !preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename)) {
        return;
    }
    $path = __DIR__ . '/../uploads/achievements/' . $filename;
    if (is_file($path)) {
        unlink($path);
    }
}

function validAchievementDate(string $value): bool
{
    if (!preg_match('/\A(\d{4})-(\d{2})-(\d{2})\z/', $value, $parts)) {
        return false;
    }
    return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session token expired. Please reload the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        try {
            if ($action === 'delete' && $id) {
                $stmt = $pdo->prepare('SELECT certificate_image FROM achievements WHERE id = ?');
                $stmt->execute([$id]);
                $record = $stmt->fetch();
                if (!$record) {
                    throw new RuntimeException('Achievement not found.');
                }
                $stmt = $pdo->prepare('DELETE FROM achievements WHERE id = ?');
                $stmt->execute([$id]);
                deleteUploadedAchievementImage($record['certificate_image']);
                $message = 'Achievement deleted successfully.';
            } elseif ($action === 'save') {
                $formValues = [
                    'title' => trim($_POST['title'] ?? ''),
                    'organization' => trim($_POST['organization'] ?? ''),
                    'date_received' => trim($_POST['date_received'] ?? ''),
                    'description' => trim($_POST['description'] ?? ''),
                ];
                if ($formValues['title'] === '' || mb_strlen($formValues['title']) > 150) {
                    throw new RuntimeException('Title is required and must be no more than 150 characters.');
                }
                if (mb_strlen($formValues['organization']) > 150) {
                    throw new RuntimeException('Issuer must be no more than 150 characters.');
                }
                if ($formValues['date_received'] !== '' && !validAchievementDate($formValues['date_received'])) {
                    throw new RuntimeException('Date received must be a valid date.');
                }

                $existingImage = null;
                if ($id) {
                    $stmt = $pdo->prepare('SELECT certificate_image FROM achievements WHERE id = ?');
                    $stmt->execute([$id]);
                    $existing = $stmt->fetch();
                    if (!$existing) {
                        throw new RuntimeException('Achievement not found.');
                    }
                    $existingImage = $existing['certificate_image'];
                } elseif (!empty($_POST['id'])) {
                    throw new RuntimeException('Invalid achievement record.');
                }

                $newImage = null;
                $imageName = $existingImage;
                if (isset($_FILES['certificate_image']) && $_FILES['certificate_image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $newImage = uploadAchievementImage($_FILES['certificate_image']);
                    $imageName = $newImage;
                }

                try {
                    if ($id) {
                        $stmt = $pdo->prepare('UPDATE achievements SET title = ?, organization = ?, date_received = ?, description = ?, certificate_image = ? WHERE id = ?');
                        $stmt->execute([$formValues['title'], $formValues['organization'] ?: null, $formValues['date_received'] ?: null, $formValues['description'] ?: null, $imageName, $id]);
                    } else {
                        $stmt = $pdo->prepare('INSERT INTO achievements (title, organization, date_received, description, certificate_image) VALUES (?, ?, ?, ?, ?)');
                        $stmt->execute([$formValues['title'], $formValues['organization'] ?: null, $formValues['date_received'] ?: null, $formValues['description'] ?: null, $imageName]);
                    }
                } catch (Throwable $exception) {
                    deleteUploadedAchievementImage($newImage);
                    throw $exception;
                }
                if ($newImage && $existingImage !== $newImage) {
                    deleteUploadedAchievementImage($existingImage);
                }
                $message = $id ? 'Achievement updated successfully.' : 'Achievement added successfully.';
                $formValues = ['title' => '', 'organization' => '', 'date_received' => '', 'description' => ''];
            } else {
                throw new RuntimeException('Invalid achievement action.');
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Achievement management error: ' . $exception->getMessage());
            $error = 'The achievement could not be saved. Please try again.';
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId) {
        $stmt = $pdo->prepare('SELECT * FROM achievements WHERE id = ?');
        $stmt->execute([$editId]);
        $achievement = $stmt->fetch() ?: null;
        if ($achievement) {
            $formValues = [
                'title' => $achievement['title'],
                'organization' => $achievement['organization'] ?? '',
                'date_received' => $achievement['date_received'] ?? '',
                'description' => $achievement['description'] ?? '',
            ];
        }
    }
}

$stmt = $pdo->query('SELECT id, title, organization, date_received, description, certificate_image FROM achievements ORDER BY date_received DESC, id DESC');
$records = $stmt->fetchAll();
$csrfToken = getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Achievements | Admin</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; padding: 28px; background: #f5f2ed; color: #2d211e; font-family: Arial, sans-serif; }
        main { max-width: 1100px; margin: 0 auto; }
        .topbar, .panel { background: #fff; border-radius: 12px; padding: 24px; margin-bottom: 20px; box-shadow: 0 4px 16px rgba(45, 33, 30, .07); }
        .topbar { display: flex; align-items: center; justify-content: space-between; gap: 16px; }
        h1, h2 { margin-top: 0; }
        a { color: #5d4037; }
        .grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 16px; }
        label { display: block; font-weight: 700; margin: 0 0 6px; }
        input, textarea { width: 100%; padding: 10px 12px; margin-bottom: 16px; border: 1px solid #cfc6c1; border-radius: 6px; font: inherit; }
        textarea { min-height: 100px; resize: vertical; }
        button, .button { display: inline-block; border: 0; border-radius: 6px; padding: 10px 15px; background: #3e2723; color: #fff; font: inherit; text-decoration: none; cursor: pointer; }
        .secondary { background: #76645d; }
        .danger { background: #a52a2a; }
        .notice { padding: 12px 14px; background: #e9f5eb; border-radius: 6px; }
        .error { padding: 12px 14px; color: #8e1e1e; background: #fdecec; border-radius: 6px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 750px; }
        th, td { text-align: left; vertical-align: top; padding: 12px; border-bottom: 1px solid #eee7e3; }
        th { background: #faf8f6; }
        .thumb { width: 85px; height: 60px; object-fit: cover; border-radius: 5px; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .actions form { margin: 0; }
        @media (max-width: 650px) { body { padding: 14px; } .topbar { align-items: flex-start; flex-direction: column; } .panel { padding: 18px; } .grid { grid-template-columns: 1fr; gap: 0; } }
    </style>
</head>
<body>
<main>
    <header class="topbar"><div><h1>Manage Achievements</h1><a href="dashboard.php">Back to Dashboard</a></div></header>
    <section class="panel" aria-labelledby="form-title">
        <h2 id="form-title"><?= $achievement ? 'Edit Achievement' : 'Add Achievement' ?></h2>
        <?php if ($message !== ''): ?><p class="notice" role="status"><?= $escape($message) ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($achievement['id'] ?? 0) ?>">
            <div class="grid">
                <div><label for="title">Title</label><input id="title" name="title" maxlength="150" required value="<?= $escape($formValues['title']) ?>"></div>
                <div><label for="organization">Issuer or organization</label><input id="organization" name="organization" maxlength="150" value="<?= $escape($formValues['organization']) ?>"></div>
            </div>
            <label for="date_received">Date received</label><input type="date" id="date_received" name="date_received" value="<?= $escape($formValues['date_received']) ?>">
            <label for="description">Description</label><textarea id="description" name="description"><?= $escape($formValues['description']) ?></textarea>
            <label for="certificate_image">Certificate image (JPG, PNG, WebP; up to 2MB)</label><input type="file" id="certificate_image" name="certificate_image" accept="image/jpeg,image/png,image/webp">
            <?php if (!empty($achievement['certificate_image'])): ?><p>Current certificate image: <?= $escape($achievement['certificate_image']) ?>. Leave empty to keep it.</p><?php endif; ?>
            <button type="submit">Save Achievement</button>
            <?php if ($achievement): ?> <a class="button secondary" href="achievements.php">Cancel</a><?php endif; ?>
        </form>
    </section>
    <section class="panel" aria-labelledby="saved-title">
        <h2 id="saved-title">Saved Achievements</h2>
        <?php if (!$records): ?><p>No achievements have been added yet.</p><?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Certificate</th><th>Title</th><th>Issuer</th><th>Date</th><th>Description</th><th>Actions</th></tr></thead>
                <tbody><?php foreach ($records as $record): ?>
                    <tr>
                        <td><?php $certificateFile = basename((string) $record['certificate_image']); if ($record['certificate_image'] && preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $certificateFile)): ?><img class="thumb" src="../uploads/achievements/<?= $escape($certificateFile) ?>" alt="Certificate for <?= $escape($record['title']) ?>"><?php endif; ?></td>
                        <td><?= $escape($record['title']) ?></td>
                        <td><?= $escape($record['organization']) ?></td>
                        <td><?= $record['date_received'] ? $escape(date('M Y', strtotime($record['date_received']))) : '' ?></td>
                        <td><?= nl2br($escape($record['description'])) ?></td>
                        <td><div class="actions"><a class="button secondary" href="achievements.php?edit=<?= (int) $record['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this achievement?')"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $record['id'] ?>"><button type="submit" class="danger">Delete</button></form></div></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
