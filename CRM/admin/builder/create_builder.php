<?php
// Step 1: Restrict this page to admin users only.
require_once __DIR__ . '/../../auth/middleware.php';
require_admin();

// Load reusable CSRF helper.
require_once __DIR__ . '/../../auth/csrf.php';

// Step 2: Load MySQLi database connection.
require_once __DIR__ . '/../../config/database.php';

// Step 3: Prepare variables for messages and form state.
$successMessage = '';
$errorMessage = '';

$formData = [
	'name' => '',
	'contact' => '',
	'address' => '',
	'email' => '',
	'status' => 'active',
];

// Step 4: Run insert logic only when form is submitted.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	// Block form submission if CSRF token is missing/invalid.
	if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
		$errorMessage = 'Invalid request token. Please refresh and try again.';
	}

	// Read and trim submitted values.
	$formData['name'] = trim($_POST['name'] ?? '');
	$formData['contact'] = trim($_POST['contact'] ?? '');
	$formData['address'] = trim($_POST['address'] ?? '');
	$formData['email'] = trim($_POST['email'] ?? '');
	$formData['status'] = $_POST['status'] ?? 'active';

	$allowedStatuses = ['active', 'inactive'];

	// Step 5: Validate required fields and format.
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
		// Step 6: Insert builder record using MySQLi prepared statement.
		$sql = 'INSERT INTO builders (name, contact, address, email, status) VALUES (?, ?, ?, ?, ?)';
		$stmt = $conn->prepare($sql);

		if (!$stmt) {
			$errorMessage = 'Unable to prepare database query. Please try again.';
		} else {
			$stmt->bind_param(
				'sssss',
				$formData['name'],
				$formData['contact'],
				$formData['address'],
				$formData['email'],
				$formData['status']
			);

			if ($stmt->execute()) {
				// Step 7: Show success and clear form values.
				$successMessage = 'Builder created successfully.';
				$formData = [
					'name' => '',
					'contact' => '',
					'address' => '',
					'email' => '',
					'status' => 'active',
				];
			} else {
				$errorMessage = 'Failed to create builder. Please try again.';
			}

			$stmt->close();
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="UTF-8">
	<meta name="viewport" content="width=device-width, initial-scale=1.0">
	<title>Create Builder</title>
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
				<h1 class="title">Create Builder</h1>
				<p class="subtitle">Add a builder record with clear and simple input fields.</p>
			</div>
			<a class="btn-link" href="<?php echo htmlspecialchars(app_url('/admin/dashboard.php')); ?>">Back to Dashboard</a>
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

				<p class="note">Fields marked with * are required. Data is saved using MySQLi prepared statements for security.</p>
				<button class="submit" type="submit">Create Builder</button>
			</form>
		</section>
	</div>
</body>
</html>

