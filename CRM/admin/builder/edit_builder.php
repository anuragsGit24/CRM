<?php
// Step 1: Restrict this page to admin users only.
require_once __DIR__ . '/../../auth/middleware.php';
require_admin();

// Load reusable CSRF helper.
require_once __DIR__ . '/../../auth/csrf.php';

// Step 2: Load MySQLi database connection.
require_once __DIR__ . '/../../config/database.php';

$errorMessage = '';
$successMessage = '';

// Step 3: Read builder ID from URL, e.g. edit_builder.php?id=3.
$builderId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// If ID is invalid, redirect to list page.
if (!$builderId) {
	header('Location: ' . app_url('/admin/builder/list_builder.php'));
	exit;
}

/*
 |--------------------------------------------------------------------------
 | Step 4: Fetch existing builder data by ID using prepared statement.
 |--------------------------------------------------------------------------
 | We use this data to pre-fill the form for editing.
 */
$selectSql = 'SELECT id, name, contact, address, email, status FROM builders WHERE id = ? LIMIT 1';
$selectStmt = $conn->prepare($selectSql);

if (!$selectStmt) {
	$errorMessage = 'Unable to load builder details right now.';
	$builderData = null;
} else {
	$selectStmt->bind_param('i', $builderId);
	$selectStmt->execute();
	$selectResult = $selectStmt->get_result();
	$builderData = $selectResult->fetch_assoc();
	$selectStmt->close();
}

if (!$builderData) {
	$errorMessage = $errorMessage !== '' ? $errorMessage : 'Builder not found.';
}

// Step 5: Store current values for form prefill.
$formData = [
	'name' => $builderData['name'] ?? '',
	'contact' => $builderData['contact'] ?? '',
	'address' => $builderData['address'] ?? '',
	'email' => $builderData['email'] ?? '',
	'status' => $builderData['status'] ?? 'active',
];

// Step 6: Process update when admin submits the form.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $builderData) {
	// Validate CSRF token before accepting updates.
	if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
		$errorMessage = 'Invalid request token. Please refresh and try again.';
	}

	// Read updated values.
	$formData['name'] = trim($_POST['name'] ?? '');
	$formData['contact'] = trim($_POST['contact'] ?? '');
	$formData['address'] = trim($_POST['address'] ?? '');
	$formData['email'] = trim($_POST['email'] ?? '');
	$formData['status'] = $_POST['status'] ?? 'active';

	$allowedStatuses = ['active', 'inactive'];

	// Step 7: Validate required fields and formats.
	if ($errorMessage === '' && (
		$formData['name'] === '' ||
		$formData['contact'] === '' ||
		$formData['address'] === '' ||
		$formData['email'] === ''
	)) {
		$errorMessage = 'Please fill all required fields.';
	} elseif ($errorMessage === '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
		$errorMessage = 'Please enter a valid email address.';
	} elseif ($errorMessage === '' && !in_array($formData['status'], $allowedStatuses, true)) {
		$errorMessage = 'Invalid status selected.';
	} elseif ($errorMessage === '') {
		// Step 8: Update builder using MySQLi prepared statement.
		$updateSql = 'UPDATE builders SET name = ?, contact = ?, address = ?, email = ?, status = ? WHERE id = ? LIMIT 1';
		$updateStmt = $conn->prepare($updateSql);

		if (!$updateStmt) {
			$errorMessage = 'Unable to prepare update query. Please try again.';
		} else {
			$updateStmt->bind_param(
				'sssssi',
				$formData['name'],
				$formData['contact'],
				$formData['address'],
				$formData['email'],
				$formData['status'],
				$builderId
			);

			if ($updateStmt->execute()) {
				$successMessage = 'Builder updated successfully.';
			} else {
				$errorMessage = 'Failed to update builder. Please try again.';
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
	<title>Edit Builder</title>
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
			--success-bg: #d3f9d8;
			--success-text: #2b8a3e;
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
			width: min(980px, 100%);
			margin: 0 auto;
			display: grid;
			gap: 20px;
		}

		.topbar {
			background: var(--card);
			border: 1px solid var(--line);
			border-radius: 16px;
			padding: 16px 18px;
			display: flex;
			justify-content: space-between;
			align-items: center;
			gap: 12px;
			box-shadow: var(--shadow);
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

		.btn-link {
			text-decoration: none;
			color: #fff;
			background: linear-gradient(120deg, var(--brand), var(--brand-2));
			padding: 10px 14px;
			border-radius: 10px;
			font-weight: 700;
		}

		.card {
			background: var(--card);
			border: 1px solid var(--line);
			border-radius: 16px;
			box-shadow: var(--shadow);
			padding: 20px;
		}

		.messages {
			display: grid;
			gap: 10px;
			margin-bottom: 14px;
		}

		.msg {
			margin: 0;
			padding: 10px 12px;
			border-radius: 10px;
			font-size: 14px;
			font-weight: 700;
		}

		.msg.error {
			background: var(--danger-bg);
			color: var(--danger-text);
			border: 1px solid #ffc9c9;
		}

		.msg.success {
			background: var(--success-bg);
			color: var(--success-text);
			border: 1px solid #b2f2bb;
		}

		form {
			display: grid;
			grid-template-columns: repeat(2, minmax(0, 1fr));
			gap: 14px;
		}

		.field {
			display: grid;
			gap: 7px;
		}

		.field.full {
			grid-column: 1 / -1;
		}

		label {
			font-size: 14px;
			font-weight: 700;
			color: #243b53;
		}

		input,
		textarea,
		select {
			width: 100%;
			border: 1px solid var(--line);
			border-radius: 12px;
			padding: 12px 14px;
			font-size: 15px;
			outline: none;
			transition: border-color 150ms ease, box-shadow 150ms ease;
			background: #fff;
			font-family: inherit;
		}

		textarea {
			resize: vertical;
			min-height: 110px;
		}

		input:focus,
		textarea:focus,
		select:focus {
			border-color: var(--brand);
			box-shadow: 0 0 0 4px rgba(11, 114, 133, 0.14);
		}

		.submit {
			grid-column: 1 / -1;
			border: none;
			border-radius: 12px;
			padding: 13px 16px;
			font-size: 15px;
			font-weight: 800;
			color: #fff;
			background: linear-gradient(120deg, var(--brand), var(--brand-2));
			cursor: pointer;
			box-shadow: 0 10px 24px rgba(11, 114, 133, 0.28);
		}

		.note {
			grid-column: 1 / -1;
			margin: 0;
			color: var(--muted);
			font-size: 13px;
			line-height: 1.5;
		}

		@media (max-width: 760px) {
			form {
				grid-template-columns: 1fr;
			}

			.topbar {
				flex-direction: column;
				align-items: flex-start;
			}
		}
	</style>
</head>
<body>
	<div class="layout">
		<header class="topbar">
			<div>
				<h1 class="title">Edit Builder</h1>
				<p class="subtitle">Update builder details with secure validation and update logic.</p>
			</div>
			<a class="btn-link" href="<?php echo htmlspecialchars(app_url('/admin/builder/list_builder.php')); ?>">Back to Builders</a>
		</header>

		<section class="card">
			<?php if ($errorMessage !== '' || $successMessage !== ''): ?>
				<div class="messages">
					<?php if ($errorMessage !== ''): ?>
						<p class="msg error"><?php echo htmlspecialchars($errorMessage); ?></p>
					<?php endif; ?>

					<?php if ($successMessage !== ''): ?>
						<p class="msg success"><?php echo htmlspecialchars($successMessage); ?></p>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<?php if ($builderData): ?>
				<form method="post" action="">
					<input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
					<div class="field">
						<label for="name">Builder Name *</label>
						<input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($formData['name']); ?>" placeholder="Enter builder name">
					</div>

					<div class="field">
						<label for="contact">Contact *</label>
						<input id="contact" name="contact" type="text" required value="<?php echo htmlspecialchars($formData['contact']); ?>" placeholder="Enter contact number">
					</div>

					<div class="field full">
						<label for="address">Address *</label>
						<textarea id="address" name="address" required placeholder="Enter complete address"><?php echo htmlspecialchars($formData['address']); ?></textarea>
					</div>

					<div class="field">
						<label for="email">Email *</label>
						<input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($formData['email']); ?>" placeholder="Enter email address">
					</div>

					<div class="field">
						<label for="status">Status *</label>
						<select id="status" name="status" required>
							<option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
							<option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
						</select>
					</div>

					<p class="note">
						Update logic: this page loads builder data by ID from URL, pre-fills the form, validates the submitted values, and updates the builder row with a MySQLi prepared statement.
					</p>

					<button class="submit" type="submit">Save Changes</button>
				</form>
			<?php endif; ?>
		</section>
	</div>
</body>
</html>

