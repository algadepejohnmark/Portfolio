<?php
require_once __DIR__ . '/../includes/db.php';
require_once __DIR__ . '/includes/auth.php';

requireAdmin();

$categories = [
    'web' => 'Web',
    'python' => 'Python',
    'data' => 'Data',
];
$message = '';
$error = '';
$editingProject = null;
$projectColumns = $pdo->query('SHOW COLUMNS FROM projects')->fetchAll(PDO::FETCH_COLUMN);
$techColumn = in_array('tech_stack', $projectColumns, true) ? 'tech_stack' : 'technologies';
$hasCategory = in_array('category', $projectColumns, true);
$hasSortOrder = in_array('sort_order', $projectColumns, true);

function uploadProjectImage(array $file): string
{
    if (!isset($file['error']) || is_array($file['error'])) {
        throw new RuntimeException('Please select a valid image.');
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        throw new RuntimeException($file['error'] === UPLOAD_ERR_NO_FILE
            ? 'Please select an image.'
            : 'The image could not be uploaded.');
    }

    if (!is_uploaded_file($file['tmp_name']) || $file['size'] <= 0 || $file['size'] > 2 * 1024 * 1024) {
        throw new RuntimeException('Image must be a valid file no larger than 2MB.');
    }

    $allowedTypes = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];
    $imageInfo = @getimagesize($file['tmp_name']);
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mimeType = $finfo->file($file['tmp_name']);

    if (!$imageInfo || !isset($allowedTypes[$mimeType]) || $imageInfo['mime'] !== $mimeType) {
        throw new RuntimeException('Please upload a valid JPG, PNG, or WebP image.');
    }

    $uploadDir = __DIR__ . '/../uploads/projects';
    if (!is_dir($uploadDir) && !mkdir($uploadDir, 0755, true) && !is_dir($uploadDir)) {
        throw new RuntimeException('The image upload directory is unavailable.');
    }

    $filename = bin2hex(random_bytes(16)) . '.' . $allowedTypes[$mimeType];
    if (!move_uploaded_file($file['tmp_name'], $uploadDir . DIRECTORY_SEPARATOR . $filename)) {
        throw new RuntimeException('Image upload failed.');
    }

    return $filename;
}

function deleteUploadedProjectImage(?string $filename): void
{
    if (!$filename || !preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $filename)) {
        return;
    }

    $path = __DIR__ . '/../uploads/projects/' . $filename;
    if (is_file($path)) {
        unlink($path);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrfToken($_POST['csrf_token'] ?? '')) {
        $error = 'Your session token expired. Please reload the page and try again.';
    } else {
        $action = $_POST['action'] ?? '';
        $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);

        try {
            if ($action === 'delete' && $id) {
                $stmt = $pdo->prepare('SELECT image FROM projects WHERE id = ?');
                $stmt->execute([$id]);
                $project = $stmt->fetch();
                if (!$project) {
                    throw new RuntimeException('Project not found.');
                }

                $stmt = $pdo->prepare('DELETE FROM projects WHERE id = ?');
                $stmt->execute([$id]);
                deleteUploadedProjectImage($project['image']);
                $message = 'Project deleted successfully.';
            } elseif ($action === 'save') {
                $title = trim($_POST['title'] ?? '');
                $description = trim($_POST['description'] ?? '');
                $techStack = trim($_POST['tech_stack'] ?? '');
                $category = $_POST['category'] ?? '';
                $projectUrl = trim($_POST['project_url'] ?? '');
                $sortOrder = filter_var($_POST['sort_order'] ?? '0', FILTER_VALIDATE_INT);

                if ($title === '' || mb_strlen($title) > 150) {
                    throw new RuntimeException('Enter a project title of no more than 150 characters.');
                }
                if ($description === '') {
                    throw new RuntimeException('Project description is required.');
                }
                if (mb_strlen($techStack) > 255) {
                    throw new RuntimeException('Tech stack must be no more than 255 characters.');
                }
                if ($hasCategory && !isset($categories[$category])) {
                    throw new RuntimeException('Choose a valid project category.');
                }
                if ($projectUrl !== '' && (!filter_var($projectUrl, FILTER_VALIDATE_URL) || !in_array(strtolower((string) parse_url($projectUrl, PHP_URL_SCHEME)), ['http', 'https'], true))) {
                    throw new RuntimeException('Project URL must be a valid HTTP or HTTPS link.');
                }
                if ($hasSortOrder && ($sortOrder === false || $sortOrder < 0)) {
                    throw new RuntimeException('Sort order must be a non-negative whole number.');
                }

                $existingImage = null;
                if ($id) {
                    $stmt = $pdo->prepare('SELECT image FROM projects WHERE id = ?');
                    $stmt->execute([$id]);
                    $existing = $stmt->fetch();
                    if (!$existing) {
                        throw new RuntimeException('Project not found.');
                    }
                    $existingImage = $existing['image'];
                } elseif (!empty($_POST['id'])) {
                    throw new RuntimeException('Invalid project record.');
                }

                $newImage = null;
                $imageName = $existingImage;
                if (isset($_FILES['image']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
                    $newImage = uploadProjectImage($_FILES['image']);
                    $imageName = $newImage;
                }

                try {
                    $values = [$title, $description, $techStack ?: null];
                    $assignments = ['title = ?', 'description = ?', $techColumn . ' = ?'];
                    if ($hasCategory) {
                        $assignments[] = 'category = ?';
                        $values[] = $category;
                    }
                    $assignments[] = 'image = ?';
                    $values[] = $imageName;
                    if (in_array('project_url', $projectColumns, true)) {
                        $assignments[] = 'project_url = ?';
                        $values[] = $projectUrl ?: null;
                    }
                    if ($hasSortOrder) {
                        $assignments[] = 'sort_order = ?';
                        $values[] = $sortOrder;
                    }

                    if ($id) {
                        $values[] = $id;
                        $stmt = $pdo->prepare('UPDATE projects SET ' . implode(', ', $assignments) . ' WHERE id = ?');
                        $stmt->execute($values);
                    } else {
                        $columns = ['title', 'description', $techColumn];
                        $insertValues = [$title, $description, $techStack ?: null];
                        if ($hasCategory) {
                            $columns[] = 'category';
                            $insertValues[] = $category;
                        }
                        $columns[] = 'image';
                        $insertValues[] = $imageName;
                        if (in_array('project_url', $projectColumns, true)) {
                            $columns[] = 'project_url';
                            $insertValues[] = $projectUrl ?: null;
                        }
                        if ($hasSortOrder) {
                            $columns[] = 'sort_order';
                            $insertValues[] = $sortOrder;
                        }
                        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
                        $stmt = $pdo->prepare('INSERT INTO projects (' . implode(', ', $columns) . ') VALUES (' . $placeholders . ')');
                        $stmt->execute($insertValues);
                    }
                } catch (Throwable $exception) {
                    deleteUploadedProjectImage($newImage);
                    throw $exception;
                }

                if ($newImage && $existingImage !== $newImage) {
                    deleteUploadedProjectImage($existingImage);
                }
                $message = $id ? 'Project updated successfully.' : 'Project added successfully.';
            } else {
                throw new RuntimeException('Invalid project action.');
            }
        } catch (RuntimeException $exception) {
            $error = $exception->getMessage();
        } catch (Throwable $exception) {
            error_log('Project management error: ' . $exception->getMessage());
            $error = 'The project could not be saved. Please try again.';
        }
    }
}

if (isset($_GET['edit'])) {
    $editId = filter_var($_GET['edit'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if ($editId) {
        $stmt = $pdo->prepare('SELECT * FROM projects WHERE id = ?');
        $stmt->execute([$editId]);
        $editingProject = $stmt->fetch() ?: null;
    }
}

$orderBy = $hasSortOrder ? 'sort_order ASC, id DESC' : 'id DESC';
$stmt = $pdo->query('SELECT * FROM projects ORDER BY ' . $orderBy);
$projects = $stmt->fetchAll();
$csrfToken = getCsrfToken();
$escape = static fn ($value): string => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Projects | Admin</title>
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
        input, textarea, select { width: 100%; padding: 10px 12px; margin-bottom: 16px; border: 1px solid #cfc6c1; border-radius: 6px; font: inherit; }
        textarea { min-height: 110px; resize: vertical; }
        button, .button { display: inline-block; border: 0; border-radius: 6px; padding: 10px 15px; background: #3e2723; color: #fff; font: inherit; text-decoration: none; cursor: pointer; }
        .secondary { background: #76645d; }
        .danger { background: #a52a2a; }
        .notice { padding: 12px 14px; background: #e9f5eb; border-radius: 6px; }
        .error { padding: 12px 14px; color: #8e1e1e; background: #fdecec; border-radius: 6px; }
        .table-wrap { overflow-x: auto; }
        table { width: 100%; border-collapse: collapse; min-width: 700px; }
        th, td { text-align: left; vertical-align: top; padding: 12px; border-bottom: 1px solid #eee7e3; }
        th { background: #faf8f6; }
        .thumb { width: 90px; height: 60px; object-fit: cover; border-radius: 5px; }
        .actions { display: flex; gap: 8px; align-items: center; }
        .actions form { margin: 0; }
        @media (max-width: 650px) { body { padding: 14px; } .topbar { align-items: flex-start; flex-direction: column; } .panel { padding: 18px; } .grid { grid-template-columns: 1fr; gap: 0; } }
    </style>
</head>
<body>
<main>
    <header class="topbar">
        <div><h1>Manage Projects</h1><a href="dashboard.php">Back to Dashboard</a></div>
        <a class="button secondary" href="../projects.html" target="_blank" rel="noopener">View Public Projects</a>
    </header>

    <section class="panel" aria-labelledby="form-title">
        <h2 id="form-title"><?= $editingProject ? 'Edit Project' : 'Add Project' ?></h2>
        <?php if ($message !== ''): ?><p class="notice" role="status"><?= $escape($message) ?></p><?php endif; ?>
        <?php if ($error !== ''): ?><p class="error" role="alert"><?= $escape($error) ?></p><?php endif; ?>
        <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>">
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="id" value="<?= (int) ($editingProject['id'] ?? 0) ?>">
            <div class="grid">
                <div><label for="title">Title</label><input id="title" name="title" maxlength="150" required value="<?= $escape($editingProject['title'] ?? '') ?>"></div>
                <?php if ($hasCategory): ?><div><label for="category">Category</label><select id="category" name="category" required><?php foreach ($categories as $value => $label): ?><option value="<?= $escape($value) ?>" <?= ($editingProject['category'] ?? '') === $value ? 'selected' : '' ?>><?= $escape($label) ?></option><?php endforeach; ?></select></div><?php endif; ?>
            </div>
            <label for="description">Description</label><textarea id="description" name="description" required><?= $escape($editingProject['description'] ?? '') ?></textarea>
            <div class="grid">
                <div><label for="tech_stack">Tech stack</label><input id="tech_stack" name="tech_stack" maxlength="255" value="<?= $escape($editingProject[$techColumn] ?? '') ?>"></div>
                <?php if (in_array('project_url', $projectColumns, true)): ?><div><label for="project_url">Project URL</label><input type="url" id="project_url" name="project_url" maxlength="255" value="<?= $escape($editingProject['project_url'] ?? '') ?>" placeholder="https://..."></div><?php endif; ?>
            </div>
            <div class="grid">
                <div><label for="image">Project image (JPG, PNG, WebP; up to 2MB)</label><input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"><?php if (!empty($editingProject['image'])): ?><small>Current image: <?= $escape($editingProject['image']) ?>. Leave empty to keep it.</small><?php endif; ?></div>
                <?php if ($hasSortOrder): ?><div><label for="sort_order">Display order</label><input type="number" id="sort_order" name="sort_order" min="0" step="1" value="<?= $escape($editingProject['sort_order'] ?? '0') ?>"></div><?php endif; ?>
            </div>
            <button type="submit">Save Project</button>
            <?php if ($editingProject): ?> <a class="button secondary" href="projects.php">Cancel</a><?php endif; ?>
        </form>
    </section>

    <section class="panel" aria-labelledby="saved-title">
        <h2 id="saved-title">Saved Projects</h2>
        <?php if (!$projects): ?><p>No projects have been added yet.</p><?php else: ?>
            <div class="table-wrap"><table>
                <thead><tr><th>Image</th><th>Project</th><?php if ($hasCategory): ?><th>Category</th><?php endif; ?><th>Tech</th><?php if ($hasSortOrder): ?><th>Order</th><?php endif; ?><th>Actions</th></tr></thead>
                <tbody><?php foreach ($projects as $project): ?>
                    <tr>
                        <td><?php if (!empty($project['image'])): $imageFile = basename($project['image']); $imagePath = preg_match('/\A[a-f0-9]{32}\.(?:jpg|png|webp)\z/', $imageFile) ? '../uploads/projects/' . $imageFile : '../images/' . $imageFile; ?><img class="thumb" src="<?= $escape($imagePath) ?>" alt=""><?php endif; ?></td>
                        <td><strong><?= $escape($project['title']) ?></strong><br><?= $escape($project['description']) ?></td>
                        <?php if ($hasCategory): ?><td><?= $escape($categories[$project['category']] ?? $project['category']) ?></td><?php endif; ?>
                        <td><?= $escape($project[$techColumn] ?? '') ?></td>
                        <?php if ($hasSortOrder): ?><td><?= (int) $project['sort_order'] ?></td><?php endif; ?>
                        <td><div class="actions"><a class="button secondary" href="projects.php?edit=<?= (int) $project['id'] ?>">Edit</a><form method="post" onsubmit="return confirm('Delete this project?')"><input type="hidden" name="csrf_token" value="<?= $escape($csrfToken) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int) $project['id'] ?>"><button type="submit" class="danger">Delete</button></form></div></td>
                    </tr>
                <?php endforeach; ?></tbody>
            </table></div>
        <?php endif; ?>
    </section>
</main>
</body>
</html>
