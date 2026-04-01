<?php
require_once __DIR__ . '/../../auth/middleware.php';
require_admin();

require_once __DIR__ . '/../../config/database.php';

$projectId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$projectId) {
    header('Location: ' . app_url('/admin/project/list_project.php'));
    exit;
}

$sql = "SELECT p.id, p.name, p.location, p.address, p.flat_type, p.project_details, p.rera_no, p.status, b.name AS builder_name
        FROM projects p
        INNER JOIN builders b ON p.builder_id = b.id
        WHERE p.id = ?
        LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo 'Unable to load project details right now.';
    exit;
}

$stmt->bind_param('i', $projectId);
$stmt->execute();
$result = $stmt->get_result();
$project = $result->fetch_assoc();
$stmt->close();

if (!$project) {
    echo 'Project not found.';
    exit;
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Project</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root{--bg:#f4f7fb;--card:#fff;--ink:#102a43;--muted:#486581;--line:#d9e2ec;--brand:#0b7285;--brand-2:#2f9e44;--shadow:0 18px 45px rgba(16,42,67,.12)}*{box-sizing:border-box}
        body{margin:0;font-family:'Manrope',sans-serif;color:var(--ink);background:radial-gradient(circle at right top,rgba(11,114,133,.12),transparent 45%),radial-gradient(circle at left bottom,rgba(47,158,68,.15),transparent 40%),var(--bg);min-height:100vh;padding:24px}
        .layout{width:min(980px,100%);margin:0 auto;display:grid;gap:18px}.topbar,.card{background:var(--card);border:1px solid var(--line);border-radius:16px;box-shadow:var(--shadow)}
        .topbar{padding:16px 18px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}.title{margin:0;font-size:24px;font-weight:800}.subtitle{margin:4px 0 0;color:var(--muted);font-size:14px}
        .btn{text-decoration:none;color:#fff;background:linear-gradient(120deg,var(--brand),var(--brand-2));padding:10px 14px;border-radius:10px;font-weight:700}
        .card{padding:20px;display:grid;gap:12px}.row{display:grid;grid-template-columns:180px 1fr;gap:10px;border-bottom:1px solid #edf2f7;padding-bottom:10px}.row:last-child{border-bottom:none}
        .label{font-weight:800;color:#243b53}.value{color:var(--ink)}
        .badge{display:inline-block;font-size:12px;font-weight:700;border-radius:999px;padding:4px 10px;border:1px solid var(--line);background:#f8fbfe;color:var(--ink)}
        .badge.active{color:#2b8a3e;border-color:#b2f2bb;background:#ebfbee}.badge.inactive{color:#c92a2a;border-color:#ffc9c9;background:#fff5f5}
        @media (max-width:760px){.row{grid-template-columns:1fr}}
    </style>
</head>
<body>
<div class="layout">
    <header class="topbar">
        <div>
            <h1 class="title">Project Details</h1>
            <p class="subtitle">Read-only project profile with linked builder.</p>
        </div>
        <a class="btn" href="<?php echo htmlspecialchars(app_url('/admin/project/list_project.php')); ?>">Back to Projects</a>
    </header>

    <section class="card">
        <div class="row"><div class="label">Project Name</div><div class="value"><?php echo htmlspecialchars($project['name']); ?></div></div>
        <div class="row"><div class="label">Builder Name</div><div class="value"><?php echo htmlspecialchars($project['builder_name']); ?></div></div>
        <div class="row"><div class="label">Location</div><div class="value"><?php echo htmlspecialchars($project['location']); ?></div></div>
        <div class="row"><div class="label">Address</div><div class="value"><?php echo nl2br(htmlspecialchars($project['address'])); ?></div></div>
        <div class="row"><div class="label">Flat Type</div><div class="value"><?php echo htmlspecialchars($project['flat_type']); ?></div></div>
        <div class="row"><div class="label">RERA No</div><div class="value"><?php echo htmlspecialchars($project['rera_no'] ?? ''); ?></div></div>
        <div class="row"><div class="label">Project Details</div><div class="value"><?php echo nl2br(htmlspecialchars($project['project_details'] ?? '')); ?></div></div>
        <div class="row"><div class="label">Status</div><div class="value"><span class="badge <?php echo ($project['status'] ?? '') === 'active' ? 'active' : 'inactive'; ?>"><?php echo htmlspecialchars($project['status']); ?></span></div></div>
    </section>
</div>
</body>
</html>
