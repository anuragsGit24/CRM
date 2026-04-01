<?php
// Step 1: Restrict this page to admin users only.
require_once __DIR__ . '/../../auth/middleware.php';
require_admin();

// Load reusable CSRF helper.
require_once __DIR__ . '/../../auth/csrf.php';

// Step 2: Load database connection.
require_once __DIR__ . '/../../config/database.php';

// Step 3: Prepare variables for result handling.
$errorMessage = '';
$successMessage = '';
$builders = [];

// Step 4: Handle delete action securely using POST + CSRF.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
		$errorMessage = 'Invalid request token. Please refresh and try again.';
	} else {
		$deleteBuilderId = filter_input(INPUT_POST, 'delete_builder_id', FILTER_VALIDATE_INT);

		if (!$deleteBuilderId) {
			$errorMessage = 'Invalid builder selected for deletion.';
		} else {
			$deleteSql = 'DELETE FROM builders WHERE id = ? LIMIT 1';
			$deleteStmt = $conn->prepare($deleteSql);

			if (!$deleteStmt) {
				$errorMessage = 'Unable to prepare delete query. Please try again.';
			} else {
				$deleteStmt->bind_param('i', $deleteBuilderId);

				if ($deleteStmt->execute()) {
					if ($deleteStmt->affected_rows > 0) {
						$successMessage = 'Builder deleted successfully.';
					} else {
						$errorMessage = 'Builder not found or already deleted.';
					}
				} else {
					if ((int) $deleteStmt->errno === 1451) {
						$errorMessage = 'Cannot delete this builder because related projects exist.';
					} else {
						$errorMessage = 'Failed to delete builder. Please try again.';
					}
				}

				$deleteStmt->close();
			}
		}
	}
}

// Step 5: Fetch all builders from database.
// No user input is used in this query, so direct query is fine.
$sql = 'SELECT id, name, contact, email, status FROM builders ORDER BY id DESC';
$result = $conn->query($sql);

if ($result === false) {
	$errorMessage = 'Unable to fetch builder data right now. Please try again.';
} else {
	while ($row = $result->fetch_assoc()) {
		$builders[] = $row;
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>All Builders</title>
	<link rel="preconnect" href="https://fonts.googleapis.com">
	<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
	<link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
	<style>
		:root {
			--bg: #f4f7fb;
			--card: #ffffff;
			--ink: #102a43;
			--muted: #486581;
			--line: #d9e2ec;
			--brand: #0b7285;
			--brand-2: #2f9e44;
			--danger-bg: #ffe3e3;
			--danger-text: #c92a2a;
			--shadow: 0 18px 45px rgba(16, 42, 67, 0.12);
		}

		* { box-sizing: border-box; }

		body {
			margin: 0;
			font-family: 'Manrope', sans-serif;
			color: var(--ink);
			background:
				radial-gradient(circle at right top, rgba(11, 114, 133, 0.12), transparent 45%),
				radial-gradient(circle at left bottom, rgba(47, 158, 68, 0.15), transparent 40%),
				var(--bg);
			min-height: 100vh;
			padding: 24px;
		}

		.layout {
			width: min(1100px, 100%);
			margin: 0 auto;
			display: grid;
			gap: 18px;
		}

		.topbar,
		.card {
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
			gap: 10px;
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

		.actions {
			display: flex;
			gap: 10px;
			flex-wrap: wrap;
		}

		.btn {
			text-decoration: none;
			border-radius: 10px;
			padding: 10px 14px;
			font-weight: 700;
			font-size: 14px;
			transition: transform 120ms ease;
		}

		.btn:hover {
			transform: translateY(-1px);
		}

		.btn-primary {
			color: #fff;
			background: linear-gradient(120deg, var(--brand), var(--brand-2));
		}

		.btn-ghost {
			color: var(--ink);
			background: #fff;
			border: 1px solid var(--line);
		}

		.card {
			padding: 16px;
		}

		.table-wrap {
			overflow-x: auto;
			border-radius: 12px;
			border: 1px solid var(--line);
		}

		table {
			width: 100%;
			border-collapse: collapse;
			min-width: 700px;
			background: #fff;
		}

		thead th {
			text-align: left;
			font-size: 13px;
			color: var(--muted);
			font-weight: 800;
			background: #f8fbfe;
			border-bottom: 1px solid var(--line);
			padding: 12px 14px;
		}

		tbody td {
			padding: 12px 14px;
			border-bottom: 1px solid #edf2f7;
			font-size: 14px;
		}

		tbody tr:hover {
			background: #f9fcff;
		}

		.badge {
			display: inline-block;
			font-size: 12px;
			font-weight: 700;
			border-radius: 999px;
			padding: 4px 10px;
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

		.edit-btn {
			text-decoration: none;
			font-size: 13px;
			font-weight: 700;
			color: #fff;
			background: linear-gradient(120deg, var(--brand), var(--brand-2));
			border-radius: 8px;
			padding: 7px 11px;
			display: inline-block;
		}

		.delete-btn {
			border: none;
			cursor: pointer;
			font-size: 13px;
			font-weight: 700;
			color: #fff;
			background: #c92a2a;
			border-radius: 8px;
			padding: 7px 11px;
		}

		.action-inline {
			display: flex;
			gap: 8px;
			align-items: center;
			flex-wrap: wrap;
		}

		.inline-form {
			margin: 0;
		}

		.success {
			margin: 0;
			padding: 10px 12px;
			border-radius: 10px;
			background: #d3f9d8;
			color: #2b8a3e;
			border: 1px solid #b2f2bb;
			font-size: 14px;
			font-weight: 700;
		}

		.empty {
			margin: 0;
			color: var(--muted);
			font-size: 14px;
			padding: 14px;
			border: 1px dashed var(--line);
			border-radius: 12px;
			background: #f8fbfe;
		}

		.error {
			margin: 0;
			padding: 10px 12px;
			border-radius: 10px;
			background: var(--danger-bg);
			color: var(--danger-text);
			border: 1px solid #ffc9c9;
			font-size: 14px;
			font-weight: 700;
		}
	</style>
</head>
<body>
	<div class="layout">
		<header class="topbar">
			<div>
				<h1 class="title">Builders</h1>
				<p class="subtitle">Manage all builder records from one simple table.</p>
			</div>
			<div class="actions">
				<a class="btn btn-primary" href="<?php echo htmlspecialchars(app_url('/admin/builder/create_builder.php')); ?>">Create Builder</a>
				<a class="btn btn-ghost" href="<?php echo htmlspecialchars(app_url('/admin/dashboard.php')); ?>">Back to Dashboard</a>
			</div>
		</header>

		<section class="card">
			<?php if ($errorMessage !== ''): ?>
				<p class="error"><?php echo htmlspecialchars($errorMessage); ?></p>
			<?php elseif ($successMessage !== ''): ?>
				<p class="success"><?php echo htmlspecialchars($successMessage); ?></p>
			<?php elseif (count($builders) === 0): ?>
				<p class="empty">No builders found. Click "Create Builder" to add your first builder.</p>
			<?php else: ?>
				<div class="table-wrap">
					<table>
						<thead>
							<tr>
								<th>ID</th>
								<th>Name</th>
								<th>Contact</th>
								<th>Email</th>
								<th>Status</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($builders as $builder): ?>
								<tr>
									<td><?php echo (int) $builder['id']; ?></td>
									<td><?php echo htmlspecialchars($builder['name']); ?></td>
									<td><?php echo htmlspecialchars($builder['contact']); ?></td>
									<td><?php echo htmlspecialchars($builder['email']); ?></td>
									<td>
										<span class="badge <?php echo $builder['status'] === 'active' ? 'active' : 'inactive'; ?>">
											<?php echo htmlspecialchars($builder['status']); ?>
										</span>
									</td>
									<td>
										<div class="action-inline">
											<a class="edit-btn" href="<?php echo htmlspecialchars(app_url('/admin/builder/view_builder.php?id=' . (int) $builder['id'])); ?>">View</a>
											<a class="edit-btn" href="<?php echo htmlspecialchars(app_url('/admin/builder/edit_builder.php?id=' . (int) $builder['id'])); ?>">Edit</a>
											<form class="inline-form" method="post" action="" onsubmit="return confirm('Delete this builder? This action cannot be undone.');">
												<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
												<input type="hidden" name="delete_builder_id" value="<?php echo (int) $builder['id']; ?>">
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

