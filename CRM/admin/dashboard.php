<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/database.php';

require_admin();

$displayName = $_SESSION['username'] ?? 'Admin';

$stats = [
    'users' => 0,
    'builders' => 0,
    'projects' => 0,
    'customers' => 0,
];

$recentUsers = [];
$recentBuilders = [];
$recentProjects = [];

$countMap = [
    'users' => 'SELECT COUNT(*) AS total FROM users',
    'builders' => 'SELECT COUNT(*) AS total FROM builders',
    'projects' => 'SELECT COUNT(*) AS total FROM projects',
    'customers' => 'SELECT COUNT(*) AS total FROM customers',
];

foreach ($countMap as $key => $sql) {
    $countResult = $conn->query($sql);
    if ($countResult && ($countRow = $countResult->fetch_assoc())) {
        $stats[$key] = (int) ($countRow['total'] ?? 0);
    }
}

$usersResult = $conn->query("SELECT id, name, last_name, role, status FROM users ORDER BY id DESC LIMIT 5");
if ($usersResult) {
    while ($row = $usersResult->fetch_assoc()) {
        $recentUsers[] = $row;
    }
}

$buildersResult = $conn->query("SELECT id, name, status FROM builders ORDER BY id DESC LIMIT 5");
if ($buildersResult) {
    while ($row = $buildersResult->fetch_assoc()) {
        $recentBuilders[] = $row;
    }
}

$projectsResult = $conn->query(
    "SELECT p.id, p.name, p.status, b.name AS builder_name
     FROM projects p
     INNER JOIN builders b ON p.builder_id = b.id
     ORDER BY p.id DESC
     LIMIT 5"
);
if ($projectsResult) {
    while ($row = $projectsResult->fetch_assoc()) {
        $recentProjects[] = $row;
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f2f7f5;
            --card: #ffffff;
            --ink: #1f2933;
            --muted: #52606d;
            --line: #d9e2ec;
            --brand: #2f9e44;
            --brand-2: #0b7285;
            --accent: #102a43;
            --shadow: 0 16px 38px rgba(16, 42, 67, 0.10);
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            font-family: 'Manrope', sans-serif;
            background:
                radial-gradient(circle at right top, rgba(11, 114, 133, 0.12), transparent 42%),
                radial-gradient(circle at left top, rgba(47, 158, 68, 0.13), transparent 40%),
                var(--bg);
            color: var(--ink);
            min-height: 100vh;
            line-height: 1.5;
        }

        .layout {
            width: min(1320px, 100%);
            margin: 0 auto;
            padding: 24px;
            display: grid;
            gap: 18px;
        }

        .topbar,
        .hero,
        .panel,
        .recent-panel {
            background: var(--card);
            border: 1px solid var(--line);
            border-radius: 16px;
            box-shadow: var(--shadow);
        }

        .topbar {
            padding: 16px 18px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .title {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
        }

        .subtitle {
            margin: 4px 0 0;
            color: var(--muted);
            font-size: 14px;
        }

        .btn,
        .action-btn,
        .quick-btn,
        .table-btn {
            text-decoration: none;
            border-radius: 10px;
            font-weight: 700;
            border: 1px solid transparent;
            transition: transform 120ms ease;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn,
        .action-btn,
        .quick-btn,
        .table-btn {
            padding: 10px 14px;
            font-size: 14px;
        }

        .btn:hover,
        .action-btn:hover,
        .quick-btn:hover,
        .table-btn:hover {
            transform: translateY(-1px);
        }

        .btn:focus-visible,
        .action-btn:focus-visible,
        .quick-btn:focus-visible,
        .table-btn:focus-visible {
            outline: 3px solid rgba(11, 114, 133, 0.32);
            outline-offset: 2px;
        }

        .btn-ghost {
            color: #ffffff;
            background: linear-gradient(120deg, var(--brand), var(--brand-2));
        }

        .hero {
            padding: 20px;
            background: linear-gradient(135deg, #eaf7ee, #e8f7fa);
            display: grid;
            gap: 14px;
        }

        .hero h2 {
            margin: 0;
            color: var(--accent);
            font-size: 28px;
            font-weight: 800;
        }

        .hero p {
            margin: 0;
            color: #486581;
            font-size: 15px;
        }

        .quick-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .quick-btn {
            color: #334e68;
            background: #ffffff;
            border-color: #cbd5e0;
        }

        .quick-btn.primary {
            color: #ffffff;
            border-color: transparent;
            background: linear-gradient(120deg, var(--brand), var(--brand-2));
        }

        .stats {
            display: grid;
            grid-template-columns: repeat(4, minmax(0, 1fr));
            gap: 12px;
        }

        .stat {
            border: 1px solid var(--line);
            border-radius: 12px;
            background: #fff;
            padding: 14px;
        }

        .stat-label {
            margin: 0;
            font-size: 12px;
            font-weight: 800;
            color: var(--muted);
            text-transform: uppercase;
            letter-spacing: .35px;
        }

        .stat-value {
            margin: 5px 0 0;
            font-size: 30px;
            font-weight: 800;
            color: #102a43;
        }

        .workspace {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 16px;
        }

        .panel,
        .recent-panel {
            padding: 18px;
        }

        .panel-title {
            margin: 0;
            font-size: 21px;
            font-weight: 800;
        }

        .panel-subtitle {
            margin: 0;
            font-size: 14px;
            color: var(--muted);
        }

        .action-grid {
            margin: 0;
            margin-top: 12px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .action-card {
            border: 1px solid var(--line);
            background: #fff;
            border-radius: 12px;
            padding: 14px;
            display: grid;
            gap: 8px;
        }

        .action-card h3 {
            margin: 0;
            font-size: 16px;
        }

        .action-card p {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .action-btn {
            padding: 8px 10px;
            font-size: 13px;
            color: #334e68;
            background: #f8fafc;
            border-color: var(--line);
            width: fit-content;
        }

        .action-btn.primary {
            color: #fff;
            border-color: transparent;
            background: linear-gradient(120deg, var(--brand), var(--brand-2));
        }

        .helper-list {
            margin: 14px 0 0;
            padding: 0;
            list-style: none;
            display: grid;
            gap: 10px;
        }

        .helper-list li {
            border: 1px solid var(--line);
            border-radius: 10px;
            padding: 10px 12px;
            border: 1px solid var(--line);
            background: #f8fbfe;
        }

        .helper-list strong {
            display: block;
            margin: 0;
            font-size: 14px;
        }

        .helper-list span {
            margin: 0;
            color: var(--muted);
            font-size: 13px;
        }

        .side-actions {
            margin-top: 14px;
            display: grid;
            gap: 8px;
        }

        .recent-grid {
            margin-top: 12px;
            display: grid;
            grid-template-columns: repeat(3, minmax(0, 1fr));
            gap: 12px;
        }

        .mini-table {
            border: 1px solid var(--line);
            border-radius: 12px;
            overflow: hidden;
            background: #fff;
        }

        .mini-head {
            padding: 10px 12px;
            background: #f8fbfe;
            border-bottom: 1px solid var(--line);
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 8px;
        }

        .mini-head h3 {
            margin: 0;
            font-size: 15px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        thead th {
            text-align: left;
            padding: 9px 10px;
            font-size: 12px;
            color: var(--muted);
            border-bottom: 1px solid var(--line);
        }

        tbody td {
            padding: 9px 10px;
            border-bottom: 1px solid #edf2f7;
            font-size: 13px;
        }

        tbody tr:last-child td {
            border-bottom: none;
        }

        .table-btn {
            padding: 6px 9px;
            border-radius: 7px;
            color: #334e68;
            background: #f8fafc;
            border-color: var(--line);
            font-size: 12px;
        }

        .badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 800;
            border-radius: 999px;
            padding: 3px 8px;
            border: 1px solid var(--line);
            background: #f8fbfe;
            color: var(--ink);
        }

        .badge.active {
            color: #2b8a3e;
            border-color: #b2f2bb;
            background: #ebfbee;
        }

        .badge.inactive {
            color: #c92a2a;
            border-color: #ffc9c9;
            background: #fff5f5;
        }

        .empty {
            margin: 0;
            padding: 10px;
            font-size: 13px;
            color: var(--muted);
        }

        @media (max-width: 1100px) {
            .workspace,
            .recent-grid,
            .action-grid,
            .stats {
                grid-template-columns: 1fr;
            }
        }
    </style>
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/assets/css/crm-theme.css')); ?>">
</head>
<body>
    <div class="layout">
        <header class="topbar">
            <div>
                <h1 class="title">Admin Dashboard</h1>
                <p class="subtitle">Welcome, <?php echo htmlspecialchars($displayName); ?>. You are logged in as admin.</p>
            </div>
            <a class="btn btn-ghost" href="<?php echo htmlspecialchars(app_url('/auth/logout.php')); ?>">Logout</a>
        </header>

        <!-- <section class="hero" aria-label="Admin overview and shortcuts">
            <h2>Admin Control Center</h2>
            <p>Full access dashboard for users, builders, projects, and customer footprint. Every critical action is available in one place.</p>

            <div class="quick-actions">
                <a class="quick-btn primary" href="<?php echo htmlspecialchars(app_url('/admin/create_user.php')); ?>">Create User</a>
                <a class="quick-btn primary" href="<?php echo htmlspecialchars(app_url('/admin/builder/create_builder.php')); ?>">Create Builder</a>
                <a class="quick-btn primary" href="<?php echo htmlspecialchars(app_url('/admin/project/create_project.php')); ?>">Create Project</a>
                <a class="quick-btn" href="<?php echo htmlspecialchars(app_url('/admin/users.php')); ?>">Open Users</a>
                <a class="quick-btn" href="<?php echo htmlspecialchars(app_url('/admin/builder/list_builder.php')); ?>">Open Builders</a>
                <a class="quick-btn" href="<?php echo htmlspecialchars(app_url('/admin/project/list_project.php')); ?>">Open Projects</a>
            </div>
        </section> -->

        <section class="stats" aria-label="System summary stats">
            <article class="stat"><p class="stat-label">Total Users</p><p class="stat-value"><?php echo (int) $stats['users']; ?></p></article>
            <article class="stat"><p class="stat-label">Total Builders</p><p class="stat-value"><?php echo (int) $stats['builders']; ?></p></article>
            <article class="stat"><p class="stat-label">Total Projects</p><p class="stat-value"><?php echo (int) $stats['projects']; ?></p></article>
            <article class="stat"><p class="stat-label">Total Customers</p><p class="stat-value"><?php echo (int) $stats['customers']; ?></p></article>
        </section>

        <section class="workspace">
            <article class="panel">
                <h2 class="panel-title">Module Management</h2>
                <p class="panel-subtitle">Action-focused cards for core admin operations. This replaces redundant dashboard blocks with direct utility.</p>

                <div class="action-grid">
                    <article class="action-card">
                        <h3>User Accounts</h3>
                        <p>Create admins/users and maintain status and roles.</p>
                        <a class="action-btn primary" href="<?php echo htmlspecialchars(app_url('/admin/users.php')); ?>">Manage Users</a>
                    </article>
                    <article class="action-card">
                        <h3>Builder Records</h3>
                        <p>Maintain builder profiles, contact data, and statuses.</p>
                        <a class="action-btn primary" href="<?php echo htmlspecialchars(app_url('/admin/builder/list_builder.php')); ?>">Manage Builders</a>
                    </article>
                    <article class="action-card">
                        <h3>Project Inventory</h3>
                        <p>Control project status and linked builder mappings.</p>
                        <a class="action-btn primary" href="<?php echo htmlspecialchars(app_url('/admin/project/list_project.php')); ?>">Manage Projects</a>
                    </article>
                    <article class="action-card">
                        <h3>Quick Create User</h3>
                        <p>Add login access for team members immediately.</p>
                        <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/admin/create_user.php')); ?>">Open Form</a>
                    </article>
                    <article class="action-card">
                        <h3>Quick Create Builder</h3>
                        <p>Add a builder first before assigning projects.</p>
                        <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/admin/builder/create_builder.php')); ?>">Open Form</a>
                    </article>
                    <article class="action-card">
                        <h3>Quick Create Project</h3>
                        <p>Add a project with active builder linkage.</p>
                        <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/admin/project/create_project.php')); ?>">Open Form</a>
                    </article>
                </div>
            </article>

            <aside class="panel">
                <h2 class="panel-title">Admin Workflow Guide</h2>
                <p class="panel-subtitle">Clean sequence for daily operations.</p>

                <ul class="helper-list">
                    <li><strong>Step 1: Maintain Users</strong><span>Ensure correct roles and active status for access control.</span></li>
                    <li><strong>Step 2: Maintain Builders</strong><span>Keep builder records complete before creating projects.</span></li>
                    <li><strong>Step 3: Maintain Projects</strong><span>Use search + filters to audit active and inactive projects fast.</span></li>
                </ul>

                <div class="side-actions">
                    <a class="action-btn primary" href="<?php echo htmlspecialchars(app_url('/admin/users.php')); ?>">Users Table</a>
                    <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/admin/builder/list_builder.php')); ?>">Builders Table</a>
                    <a class="action-btn" href="<?php echo htmlspecialchars(app_url('/admin/project/list_project.php')); ?>">Projects Table</a>
                </div>
            </aside>
        </section>

        <section class="recent-panel" aria-label="Recent administrative records">
            <h2 class="panel-title">Recent Activity</h2>
            <p class="panel-subtitle">Latest records from each module for quick verification.</p>

            <div class="recent-grid">
                <article class="action-card">
                    <div class="mini-head">
                        <h3>Recent Users</h3>
                        <a class="table-btn" href="<?php echo htmlspecialchars(app_url('/admin/users.php')); ?>">All</a>
                    </div>
                    <?php if (count($recentUsers) === 0): ?>
                        <p class="empty">No users found.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Name</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentUsers as $user): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars(trim(($user['name'] ?? '') . ' ' . ($user['last_name'] ?? ''))); ?></td>
                                    <td><span class="badge <?php echo ($user['status'] ?? '') === 'active' ? 'active' : 'inactive'; ?>"><?php echo htmlspecialchars($user['status']); ?></span></td>
                                    <td><a class="table-btn" href="<?php echo htmlspecialchars(app_url('/admin/view_user.php?id=' . (int) $user['id'])); ?>">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </article>

                <article class="action-card">
                    <div class="mini-head">
                        <h3>Recent Builders</h3>
                        <a class="table-btn" href="<?php echo htmlspecialchars(app_url('/admin/builder/list_builder.php')); ?>">All</a>
                    </div>
                    <?php if (count($recentBuilders) === 0): ?>
                        <p class="empty">No builders found.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Name</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentBuilders as $builder): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($builder['name']); ?></td>
                                    <td><span class="badge <?php echo ($builder['status'] ?? '') === 'active' ? 'active' : 'inactive'; ?>"><?php echo htmlspecialchars($builder['status']); ?></span></td>
                                    <td><a class="table-btn" href="<?php echo htmlspecialchars(app_url('/admin/builder/view_builder.php?id=' . (int) $builder['id'])); ?>">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </article>

                <article class="action-card">
                    <div class="mini-head">
                        <h3>Recent Projects</h3>
                        <a class="table-btn" href="<?php echo htmlspecialchars(app_url('/admin/project/list_project.php')); ?>">All</a>
                    </div>
                    <?php if (count($recentProjects) === 0): ?>
                        <p class="empty">No projects found.</p>
                    <?php else: ?>
                        <table>
                            <thead><tr><th>Name</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                            <?php foreach ($recentProjects as $project): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($project['name']); ?></td>
                                    <td><span class="badge <?php echo ($project['status'] ?? '') === 'active' ? 'active' : 'inactive'; ?>"><?php echo htmlspecialchars($project['status']); ?></span></td>
                                    <td><a class="table-btn" href="<?php echo htmlspecialchars(app_url('/admin/project/view_project.php?id=' . (int) $project['id'])); ?>">View</a></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    <?php endif; ?>
                </article>
            </div>
        </section>
    </div>
</body>
</html>
