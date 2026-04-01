<?php
// Load middleware to protect this page (admin only).
require_once __DIR__ . '/../auth/middleware.php';

// Load reusable CSRF helper.
require_once __DIR__ . '/../auth/csrf.php';

// Load database connection for insert query.
require_once __DIR__ . '/../config/database.php';

// Enforce access control: only admin can open this page.
require_admin();

// Default messages and form values.
$successMessage = '';
$errorMessage = '';
$formData = [
    'name' => '',
    'last_name' => '',
    'username' => '',
    'email' => '',
    'contact' => '',
    'role' => 'user',
    'status' => 'active',
];

// Process the form only when submitted with POST.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Block form submission if CSRF token is missing/invalid.
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        $errorMessage = 'Invalid request token. Please refresh and try again.';
    }

    // Read and clean inputs.
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['last_name'] = trim($_POST['last_name'] ?? '');
    $formData['username'] = trim($_POST['username'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $plainPassword = $_POST['password'] ?? '';
    $formData['contact'] = trim($_POST['contact'] ?? '');
    $formData['role'] = $_POST['role'] ?? 'user';
    $formData['status'] = $_POST['status'] ?? 'active';

    // Allow only known values for role and status.
    $allowedRoles = ['admin', 'user'];
    $allowedStatuses = ['active', 'inactive'];

    // Basic beginner-friendly validation.
    if ($errorMessage === '' && (
        $formData['name'] === '' ||
        $formData['last_name'] === '' ||
        $formData['username'] === '' ||
        $formData['email'] === '' ||
        $plainPassword === ''
    )) {
        $errorMessage = 'Please fill all required fields.';
    } elseif ($errorMessage === '' && !filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif ($errorMessage === '' && strlen($plainPassword) < 6) {
        $errorMessage = 'Password must be at least 6 characters.';
    } elseif ($errorMessage === '' && !in_array($formData['role'], $allowedRoles, true)) {
        $errorMessage = 'Invalid role selected.';
    } elseif ($errorMessage === '' && !in_array($formData['status'], $allowedStatuses, true)) {
        $errorMessage = 'Invalid status selected.';
    } elseif ($errorMessage === '') {
        // Hash password before saving it to database.
        $hashedPassword = password_hash($plainPassword, PASSWORD_DEFAULT);

        // Prepared statement protects this insert from SQL injection.
        $sql = 'INSERT INTO users (name, last_name, username, email, password, contact, role, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)';
        $stmt = $conn->prepare($sql);

        if (!$stmt) {
            $errorMessage = 'Unable to prepare database query. Please try again.';
        } else {
            $stmt->bind_param(
                'ssssssss',
                $formData['name'],
                $formData['last_name'],
                $formData['username'],
                $formData['email'],
                $hashedPassword,
                $formData['contact'],
                $formData['role'],
                $formData['status']
            );

            // Execute query and show useful feedback.
            if ($stmt->execute()) {
                $successMessage = 'User created successfully.';

                // Reset form after successful insert.
                $formData = [
                    'name' => '',
                    'last_name' => '',
                    'username' => '',
                    'email' => '',
                    'contact' => '',
                    'role' => 'user',
                    'status' => 'active',
                ];
            } else {
                // MySQL duplicate key code for unique username conflicts.
                if ((int) $stmt->errno === 1062) {
                    $errorMessage = 'Username already exists. Please choose another.';
                } else {
                    $errorMessage = 'Failed to create user. Please try again.';
                }
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
    <title>Create User</title>
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
        select {
            width: 100%;
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 12px 14px;
            font-size: 15px;
            outline: none;
            transition: border-color 150ms ease, box-shadow 150ms ease;
            background: #fff;
        }

        input:focus,
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
    <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/assets/css/crm-theme.css')); ?>">
</head>
<body>
    <div class="layout">
        <header class="topbar">
            <div>
                <h1 class="title">Create New User</h1>
                <p class="subtitle">Add a CRM user with secure password storage and role access.</p>
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
                    <label for="name">First Name *</label>
                    <input id="name" name="name" type="text" required value="<?php echo htmlspecialchars($formData['name']); ?>" placeholder="Enter first name">
                </div>

                <div class="field">
                    <label for="last_name">Last Name *</label>
                    <input id="last_name" name="last_name" type="text" required value="<?php echo htmlspecialchars($formData['last_name']); ?>" placeholder="Enter last name">
                </div>

                <div class="field">
                    <label for="username">Username *</label>
                    <input id="username" name="username" type="text" required value="<?php echo htmlspecialchars($formData['username']); ?>" placeholder="Choose a username">
                </div>

                <div class="field">
                    <label for="email">Email *</label>
                    <input id="email" name="email" type="email" required value="<?php echo htmlspecialchars($formData['email']); ?>" placeholder="Enter email address">
                </div>

                <div class="field">
                    <label for="password">Password *</label>
                    <input id="password" name="password" type="password" required placeholder="Minimum 6 characters" autocomplete="new-password">
                </div>

                <div class="field">
                    <label for="contact">Contact</label>
                    <input id="contact" name="contact" type="text" value="<?php echo htmlspecialchars($formData['contact']); ?>" placeholder="Enter contact number">
                </div>

                <div class="field">
                    <label for="role">Role *</label>
                    <select id="role" name="role" required>
                        <option value="user" <?php echo $formData['role'] === 'user' ? 'selected' : ''; ?>>User</option>
                        <option value="admin" <?php echo $formData['role'] === 'admin' ? 'selected' : ''; ?>>Admin</option>
                    </select>
                </div>

                <div class="field">
                    <label for="status">Status *</label>
                    <select id="status" name="status" required>
                        <option value="active" <?php echo $formData['status'] === 'active' ? 'selected' : ''; ?>>Active</option>
                        <option value="inactive" <?php echo $formData['status'] === 'inactive' ? 'selected' : ''; ?>>Inactive</option>
                    </select>
                </div>

                <p class="note">Fields marked with * are required. Password is stored using secure hashing.</p>
                <button class="submit" type="submit">Create User</button>
            </form>
        </section>
    </div>
</body>
</html>
