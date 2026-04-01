<?php
// Step 1: Restrict this page to admin users only.
require_once __DIR__ . '/../../auth/middleware.php';
require_admin();

// Step 2: Load CSRF helper and database connection.
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../config/database.php';

$errorMessage = '';
$successMessage = '';
$builders = [];

$projectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$projectId) {
    header('Location: ' . app_url('/admin/project/list_project.php'));
    exit;
}

// Step 3: Fetch active builders for dropdown.
$builderSql = "SELECT id, name FROM builders WHERE status = 'active' ORDER BY name ASC";
$builderResult = $conn->query($builderSql);
if ($builderResult) {
    while ($row = $builderResult->fetch_assoc()) {
        $builders[] = $row;
    }
} else {
    $errorMessage = 'Unable to load active builders. Please try again.';
}

// Step 4: Fetch existing project data by ID.
$selectSql = 'SELECT id, name, builder_id, location, address, flat_type, project_details, rera_no, status FROM projects WHERE id = ? LIMIT 1';
$selectStmt = $conn->prepare($selectSql);
if (!$selectStmt) {
    $errorMessage = 'Unable to load project details right now.';
    $projectData = null;
} else {
    $selectStmt->bind_param('i', $projectId);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $projectData = $result->fetch_assoc();
    $selectStmt->close();
}

if (!$projectData) {
    $errorMessage = $errorMessage !== '' ? $errorMessage : 'Project not found.';
}

$formData = [
    'name' => $projectData['name'] ?? '',
    'builder_id' => (string)($projectData['builder_id'] ?? ''),
    'location' => $projectData['location'] ?? '',
    'address' => $projectData['address'] ?? '',
    'flat_type' => $projectData['flat_type'] ?? '',
    'project_details' => $projectData['project_details'] ?? '',
    'rera_no' => $projectData['rera_no'] ?? '',
    'status' => $projectData['status'] ?? 'active',
];

// Step 5: Handle update submission.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $projectData) {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Invalid request token. Please refresh and try again.';
    }

    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['builder_id'] = trim($_POST['builder_id'] ?? '');
    $formData['location'] = trim($_POST['location'] ?? '');
    $formData['address'] = trim($_POST['address'] ?? '');
    $formData['flat_type'] = trim($_POST['flat_type'] ?? '');
    $formData['project_details'] = trim($_POST['project_details'] ?? '');
    $formData['rera_no'] = trim($_POST['rera_no'] ?? '');
    $formData['status'] = $_POST['status'] ?? 'active';

    $allowedStatuses = ['active', 'inactive'];
    $builderId = filter_var($formData['builder_id'], FILTER_VALIDATE_INT);

    if ($errorMessage === '' && (
        $formData['name'] === '' ||
        !$builderId ||
        $formData['location'] === '' ||
        $formData['address'] === '' ||
        $formData['flat_type'] === ''
    )) {
        $errorMessage = 'Please fill all required fields.';
    } elseif ($errorMessage === '' && !in_array($formData['status'], $allowedStatuses, true)) {
        $errorMessage = 'Invalid status selected.';
    } elseif ($errorMessage === '') {
        $updateSql = 'UPDATE projects SET name = ?, builder_id = ?, location = ?, address = ?, flat_type = ?, project_details = ?, rera_no = ?, status = ? WHERE id = ? LIMIT 1';
        $updateStmt = $conn->prepare($updateSql);

        if (!$updateStmt) {
            $errorMessage = 'Unable to prepare update query. Please try again.';
        } else {
            $updateStmt->bind_param(
                'sissssssi',
                $formData['name'],
                $builderId,
                $formData['location'],
                $formData['address'],
                $formData['flat_type'],
                $formData['project_details'],
                $formData['rera_no'],
                $formData['status'],
                $projectId
            );

            if ($updateStmt->execute()) {
                $successMessage = 'Project updated successfully.';
            } else {
                if ((int) $updateStmt->errno === 1452) {
                    $errorMessage = 'Selected builder is invalid.';
                } else {
                    $errorMessage = 'Failed to update project. Please try again.';
                }
            }
            $updateStmt->close();
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Project</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#f4f7fb; --card:#fff; --ink:#102a43; --muted:#486581; --line:#d9e2ec; --brand:#0b7285; --brand-2:#2f9e44; --danger-bg:#ffe3e3; --danger-text:#c92a2a; --success-bg:#d3f9d8; --success-text:#2b8a3e; --shadow:0 18px 45px rgba(16,42,67,.12);}*{box-sizing:border-box}
        body{margin:0;font-family:'Manrope',sans-serif;color:var(--ink);background:radial-gradient(circle at right top,rgba(11,114,133,.12),transparent 45%),radial-gradient(circle at left bottom,rgba(47,158,68,.15),transparent 40%),var(--bg);min-height:100vh;padding:24px}
        .layout{width:min(1000px,100%);margin:0 auto;display:grid;gap:20px}.topbar{background:var(--card);border:1px solid var(--line);border-radius:16px;padding:16px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px;box-shadow:var(--shadow)}
        .title{margin:0;font-size:24px;font-weight:800}.subtitle{margin:4px 0 0;color:var(--muted);font-size:14px}.btn-link{text-decoration:none;color:#fff;background:linear-gradient(120deg,var(--brand),var(--brand-2));padding:10px 14px;border-radius:10px;font-weight:700}
        .card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow);padding:20px}.messages{display:grid;gap:10px;margin-bottom:14px}.msg{margin:0;padding:10px 12px;border-radius:10px;font-size:14px;font-weight:700}.msg.error{background:var(--danger-bg);color:var(--danger-text);border:1px solid #ffc9c9}.msg.success{background:var(--success-bg);color:var(--success-text);border:1px solid #b2f2bb}
        form{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:14px}.field{display:grid;gap:7px}.field.full{grid-column:1 / -1}label{font-size:14px;font-weight:700;color:#243b53}
        input,textarea,select{width:100%;border:1px solid var(--line);border-radius:12px;padding:12px 14px;font-size:15px;outline:none;transition:border-color 150ms ease,box-shadow 150ms ease;background:#fff;font-family:inherit}
        textarea{resize:vertical;min-height:110px}input:focus,textarea:focus,select:focus{border-color:var(--brand);box-shadow:0 0 0 4px rgba(11,114,133,.14)}
        .submit{grid-column:1 / -1;border:none;border-radius:12px;padding:13px 16px;font-size:15px;font-weight:800;color:#fff;background:linear-gradient(120deg,var(--brand),var(--brand-2));cursor:pointer;box-shadow:0 10px 24px rgba(11,114,133,.28)}
        .note{grid-column:1 / -1;margin:0;color:var(--muted);font-size:13px;line-height:1.5}
        @media (max-width:760px){form{grid-template-columns:1fr}.topbar{flex-direction:column;align-items:flex-start}}
    </style>
</head>
<body>
<div class="layout">
    <header class="topbar">
        <div>
            <h1 class="title">Edit Project</h1>
            <p class="subtitle">Update project details and linked builder.</p>
        </div>
        <a class="btn-link" href="<?php echo htmlspecialchars(app_url('/admin/project/list_project.php')); ?>">Back to Projects</a>
    </header>

    <section class="card">
        <?php if ($errorMessage !== '' || $successMessage !== ''): ?>
            <div class="messages">
                <?php if ($errorMessage !== ''): ?><p class="msg error"><?php echo htmlspecialchars($errorMessage); ?></p><?php endif; ?>
                <?php if ($successMessage !== ''): ?><p class="msg success"><?php echo htmlspecialchars($successMessage); ?></p><?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if ($projectData): ?>
            <form method="post" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">

                <div class="field"><label for="name">Project Name *</label><input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($formData['name']); ?>"></div>
                <div class="field">
                    <label for="builder_id">Builder (Active) *</label>
                    <select id="builder_id" name="builder_id" required>
                        <option value="">Select active builder</option>
                        <?php foreach ($builders as $builder): ?>
                            <option value="<?php echo (int) $builder['id']; ?>" <?php echo ((string)$builder['id'] === (string)$formData['builder_id']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($builder['name']); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="field"><label for="location">Location *</label><input id="location" name="location" type="text" required value="<?php echo htmlspecialchars($formData['location']); ?>"></div>
                <div class="field"><label for="flat_type">Flat Type *</label><input id="flat_type" name="flat_type" type="text" required value="<?php echo htmlspecialchars($formData['flat_type']); ?>"></div>
                <div class="field full"><label for="address">Address *</label><textarea id="address" name="address" required><?php echo htmlspecialchars($formData['address']); ?></textarea></div>
                <div class="field full"><label for="project_details">Project Details</label><textarea id="project_details" name="project_details"><?php echo htmlspecialchars($formData['project_details']); ?></textarea></div>
                <div class="field"><label for="rera_no">RERA No</label><input id="rera_no" name="rera_no" type="text" value="<?php echo htmlspecialchars($formData['rera_no']); ?>"></div>
                <div class="field"><label for="status">Status *</label><select id="status" name="status" required><option value="active" <?php echo $formData['status']==='active'?'selected':''; ?>>Active</option><option value="inactive" <?php echo $formData['status']==='inactive'?'selected':''; ?>>Inactive</option></select></div>

                <p class="note">The builder dropdown shows only active builders. Project is linked through builder_id foreign key.</p>
                <button class="submit" type="submit">Save Changes</button>
            </form>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
