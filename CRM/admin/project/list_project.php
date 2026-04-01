<?php
// Step 1: Restrict page access to admin users.
require_once __DIR__ . '/../../auth/middleware.php';
require_admin();

// Step 2: Load CSRF helper and database connection.
require_once __DIR__ . '/../../auth/csrf.php';
require_once __DIR__ . '/../../config/database.php';

$errorMessage = '';
$successMessage = '';
$projects = [];

// Step 3: Read quick-search and status filter from URL query.
$searchQuery = trim((string) ($_GET['q'] ?? ''));
$statusFilter = (string) ($_GET['status'] ?? 'all');
$allowedStatusFilters = ['all', 'active', 'inactive'];
if (!in_array($statusFilter, $allowedStatusFilters, true)) {
    $statusFilter = 'all';
}

// Step 4: Handle delete action securely using POST + CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Invalid request token. Please refresh and try again.';
    } else {
        $deleteProjectId = filter_input(INPUT_POST, 'delete_project_id', FILTER_VALIDATE_INT);
        if (!$deleteProjectId) {
            $errorMessage = 'Invalid project selected for deletion.';
        } else {
            $deleteSql = 'DELETE FROM projects WHERE id = ? LIMIT 1';
            $deleteStmt = $conn->prepare($deleteSql);
            if (!$deleteStmt) {
                $errorMessage = 'Unable to prepare delete query. Please try again.';
            } else {
                $deleteStmt->bind_param('i', $deleteProjectId);
                if ($deleteStmt->execute()) {
                    if ($deleteStmt->affected_rows > 0) {
                        $successMessage = 'Project deleted successfully.';
                    } else {
                        $errorMessage = 'Project not found or already deleted.';
                    }
                } else {
                    if ((int) $deleteStmt->errno === 1451) {
                        $errorMessage = 'Cannot delete this project because related customers exist.';
                    } else {
                        $errorMessage = 'Failed to delete project. Please try again.';
                    }
                }
                $deleteStmt->close();
            }
        }
    }
}

/*
 |--------------------------------------------------------------------------
 | Step 5: Fetch projects with builder name using JOIN + optional filters.
 |--------------------------------------------------------------------------
 | JOIN links projects.builder_id to builders.id, so we can show builder name.
 */
$listSql = "SELECT p.id, p.name, p.location, p.flat_type, p.rera_no, p.status, b.name AS builder_name
            FROM projects p
            INNER JOIN builders b ON p.builder_id = b.id
            WHERE 1 = 1";

$paramTypes = '';
$paramValues = [];

if ($searchQuery !== '') {
    $listSql .= ' AND (p.name LIKE ? OR b.name LIKE ? OR p.location LIKE ? OR p.flat_type LIKE ? OR p.rera_no LIKE ?)';
    $likeSearch = '%' . $searchQuery . '%';
    $paramTypes .= 'sssss';
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
}

if ($statusFilter !== 'all') {
    $listSql .= ' AND p.status = ?';
    $paramTypes .= 's';
    $paramValues[] = $statusFilter;
}

$listSql .= ' ORDER BY p.id DESC';

$listStmt = $conn->prepare($listSql);
if ($listStmt === false) {
    $errorMessage = 'Unable to fetch projects right now. Please try again.';
} else {
    if ($paramTypes !== '') {
        $bindValues = array_merge([$paramTypes], $paramValues);
        $bindRefs = [];
        foreach ($bindValues as $key => $value) {
            $bindRefs[$key] = &$bindValues[$key];
        }
        call_user_func_array([$listStmt, 'bind_param'], $bindRefs);
    }

    $listStmt->execute();
    $listResult = $listStmt->get_result();
    while ($row = $listResult->fetch_assoc()) {
        $projects[] = $row;
    }
    $listStmt->close();
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Projects List</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root { --bg:#f4f7fb; --card:#fff; --ink:#102a43; --muted:#486581; --line:#d9e2ec; --brand:#0b7285; --brand-2:#2f9e44; --danger-bg:#ffe3e3; --danger-text:#c92a2a; --shadow:0 18px 45px rgba(16,42,67,.12);}*{box-sizing:border-box}
        body{margin:0;font-family:'Manrope',sans-serif;color:var(--ink);background:radial-gradient(circle at right top,rgba(11,114,133,.12),transparent 45%),radial-gradient(circle at left bottom,rgba(47,158,68,.15),transparent 40%),var(--bg);min-height:100vh;padding:24px}
        .layout{width:min(1150px,100%);margin:0 auto;display:grid;gap:18px}.topbar,.card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow)}
        .topbar{padding:16px 18px;display:flex;justify-content:space-between;align-items:center;gap:10px;flex-wrap:wrap}.title{margin:0;font-size:24px;font-weight:800}.subtitle{margin:4px 0 0;color:var(--muted);font-size:14px}
        .actions{display:flex;gap:10px;flex-wrap:wrap}.btn{text-decoration:none;border-radius:10px;padding:10px 14px;font-weight:700;font-size:14px;transition:transform 120ms ease}.btn:hover{transform:translateY(-1px)}
        .btn-primary{color:#fff;background:linear-gradient(120deg,var(--brand),var(--brand-2))}.btn-ghost{color:var(--ink);background:#fff;border:1px solid var(--line)}
        .card{padding:16px}.table-wrap{overflow-x:auto;border-radius:12px;border:1px solid var(--line)}table{width:100%;border-collapse:collapse;min-width:980px;background:#fff}
        .filters{display:grid;grid-template-columns:2fr 1fr auto;gap:10px;align-items:end;margin-bottom:12px}
        .filter-field{display:grid;gap:6px}.filter-label{font-size:12px;font-weight:700;color:var(--muted)}
        .filter-input{width:100%;border:1px solid var(--line);border-radius:10px;padding:10px 12px;font-size:14px;outline:none;background:#fff;font-family:inherit}
        .filter-input:focus{border-color:var(--brand);box-shadow:0 0 0 4px rgba(11,114,133,.14)}
        .filter-actions{display:flex;gap:8px;align-items:center}
        thead th{text-align:left;font-size:13px;color:var(--muted);font-weight:800;background:#f8fbfe;border-bottom:1px solid var(--line);padding:12px 14px}
        tbody td{padding:12px 14px;border-bottom:1px solid #edf2f7;font-size:14px}tbody tr:hover{background:#f9fcff}
        .badge{display:inline-block;font-size:12px;font-weight:700;border-radius:999px;padding:4px 10px;border:1px solid var(--line);background:#f8fbfe;color:var(--ink)}
        .badge.active{color:#2b8a3e;border-color:#b2f2bb;background:#ebfbee}.badge.inactive{color:#c92a2a;border-color:#ffc9c9;background:#fff5f5}
        .edit-btn{text-decoration:none;font-size:13px;font-weight:700;color:#fff;background:linear-gradient(120deg,var(--brand),var(--brand-2));border-radius:8px;padding:7px 11px;display:inline-block}
        .delete-btn{border:none;cursor:pointer;font-size:13px;font-weight:700;color:#fff;background:#c92a2a;border-radius:8px;padding:7px 11px}
        .action-inline{display:flex;gap:8px;align-items:center;flex-wrap:wrap}.inline-form{margin:0}
        .empty{margin:0;color:var(--muted);font-size:14px;padding:14px;border:1px dashed var(--line);border-radius:12px;background:#f8fbfe}
        .error{margin:0;padding:10px 12px;border-radius:10px;background:var(--danger-bg);color:var(--danger-text);border:1px solid #ffc9c9;font-size:14px;font-weight:700}
        .success{margin:0;padding:10px 12px;border-radius:10px;background:#d3f9d8;color:#2b8a3e;border:1px solid #b2f2bb;font-size:14px;font-weight:700}
        @media (max-width:900px){.filters{grid-template-columns:1fr}.filter-actions{justify-content:flex-start}}
    </style>
</head>
<body>
<div class="layout">
    <header class="topbar">
        <div>
            <h1 class="title">Projects</h1>
            <p class="subtitle">Projects with linked builder names using SQL JOIN.</p>
        </div>
        <div class="actions">
            <a class="btn btn-primary" href="<?php echo htmlspecialchars(app_url('/admin/project/create_project.php')); ?>">Create Project</a>
            <a class="btn btn-ghost" href="<?php echo htmlspecialchars(app_url('/admin/dashboard.php')); ?>">Back to Dashboard</a>
        </div>
    </header>

    <section class="card">
        <form class="filters" method="get" action="">
            <div class="filter-field">
                <label class="filter-label" for="q">Quick Search</label>
                <input class="filter-input" id="q" name="q" type="text" value="<?php echo htmlspecialchars($searchQuery); ?>" placeholder="Search project, builder, location, flat type, RERA">
            </div>

            <div class="filter-field">
                <label class="filter-label" for="status">Status Filter</label>
                <select class="filter-input" id="status" name="status">
                    <option value="all" <?php echo $statusFilter === 'all' ? 'selected' : ''; ?>>All</option>
                    <option value="active" <?php echo $statusFilter === 'active' ? 'selected' : ''; ?>>Active</option>
                    <option value="inactive" <?php echo $statusFilter === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                </select>
            </div>

            <div class="filter-actions">
                <button class="btn btn-primary" type="submit">Apply</button>
                <a class="btn btn-ghost" href="<?php echo htmlspecialchars(app_url('/admin/project/list_project.php')); ?>">Reset</a>
            </div>
        </form>

        <?php if ($errorMessage !== ''): ?>
            <p class="error"><?php echo htmlspecialchars($errorMessage); ?></p>
        <?php elseif ($successMessage !== ''): ?>
            <p class="success"><?php echo htmlspecialchars($successMessage); ?></p>
        <?php elseif (count($projects) === 0): ?>
            <p class="empty">No projects found. Click Create Project to add your first project.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Project Name</th>
                            <th>Builder Name</th>
                            <th>Location</th>
                            <th>Flat Type</th>
                            <th>RERA No</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($projects as $project): ?>
                            <tr>
                                <td><?php echo (int) $project['id']; ?></td>
                                <td><?php echo htmlspecialchars($project['name']); ?></td>
                                <td><?php echo htmlspecialchars($project['builder_name']); ?></td>
                                <td><?php echo htmlspecialchars($project['location']); ?></td>
                                <td><?php echo htmlspecialchars($project['flat_type']); ?></td>
                                <td><?php echo htmlspecialchars($project['rera_no'] ?? ''); ?></td>
                                <td><span class="badge <?php echo $project['status']==='active' ? 'active' : 'inactive'; ?>"><?php echo htmlspecialchars($project['status']); ?></span></td>
                                <td>
                                    <div class="action-inline">
                                        <a class="edit-btn" href="<?php echo htmlspecialchars(app_url('/admin/project/view_project.php?id=' . (int) $project['id'])); ?>">View</a>
                                        <a class="edit-btn" href="<?php echo htmlspecialchars(app_url('/admin/project/edit_project.php?id=' . (int) $project['id'])); ?>">Edit</a>
                                        <form class="inline-form" method="post" action="" onsubmit="return confirm('Delete this project? This action cannot be undone.');">
                                            <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                                            <input type="hidden" name="delete_project_id" value="<?php echo (int) $project['id']; ?>">
                                            <button class="delete-btn" type="submit">Delete</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</div>
</body>
</html>
