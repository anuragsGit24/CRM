<?php
// Step 1: Allow only admin users to access this page.
require_once __DIR__ . '/../auth/middleware.php';
require_admin();

// Step 2: Load database connection for select/update queries.
require_once __DIR__ . '/../config/database.php';

$errorMessage = '';
$successMessage = '';

// Step 3: Read user ID from URL (GET parameter), e.g. edit_user.php?id=5.
$userId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// If ID is missing/invalid, send admin back to users list.
if (!$userId) {
    header('Location: ' . app_url('/admin/users.php'));
    exit;
}

/*
 |--------------------------------------------------------------------------
 | Step 4: Fetch existing user data by ID using prepared statement
 |--------------------------------------------------------------------------
 | This is required to pre-fill form fields with current database values.
 */
$selectSql = 'SELECT id, name, last_name, username, email, contact, role, status FROM users WHERE id = ? LIMIT 1';
$selectStmt = $conn->prepare($selectSql);

if (!$selectStmt) {
    $errorMessage = 'Unable to load user details right now.';
    $userData = null;
} else {
    $selectStmt->bind_param('i', $userId);
    $selectStmt->execute();
    $result = $selectStmt->get_result();
    $userData = $result->fetch_assoc();
    $selectStmt->close();
}

// If no user found for this ID, show message and stop form processing.
if (!$userData) {
    $errorMessage = $errorMessage !== '' ? $errorMessage : 'User not found.';
}

// This array powers form values (prefill) and keeps entered values after validation errors.
$formData = [
    'name' => $userData['name'] ?? '',
    'last_name' => $userData['last_name'] ?? '',
    'username' => $userData['username'] ?? '',
    'email' => $userData['email'] ?? '',
    'contact' => $userData['contact'] ?? '',
    'role' => $userData['role'] ?? 'user',
    'status' => $userData['status'] ?? 'active',
];

// Step 5: Process update only when admin submits the form.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $userData) {
    // Collect updated input.
    $formData['name'] = trim($_POST['name'] ?? '');
    $formData['last_name'] = trim($_POST['last_name'] ?? '');
    $formData['username'] = trim($_POST['username'] ?? '');
    $formData['email'] = trim($_POST['email'] ?? '');
    $formData['contact'] = trim($_POST['contact'] ?? '');
    $formData['role'] = $_POST['role'] ?? 'user';
    $formData['status'] = $_POST['status'] ?? 'active';

    // Password is optional here. Leave it blank to keep current password unchanged.
    $newPassword = $_POST['password'] ?? '';

    $allowedRoles = ['admin', 'user'];
    $allowedStatuses = ['active', 'inactive'];

    // Basic validation.
    if (
        $formData['name'] === '' ||
        $formData['last_name'] === '' ||
        $formData['username'] === '' ||
        $formData['email'] === ''
    ) {
        $errorMessage = 'Please fill all required fields.';
    } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
        $errorMessage = 'Please enter a valid email address.';
    } elseif (!in_array($formData['role'], $allowedRoles, true)) {
        $errorMessage = 'Invalid role selected.';
    } elseif (!in_array($formData['status'], $allowedStatuses, true)) {
        $errorMessage = 'Invalid status selected.';
    } elseif ($newPassword !== '' && strlen($newPassword) < 6) {
        // Validate password only if admin entered a new one.
        $errorMessage = 'New password must be at least 6 characters.';
    } else {
        /*
         |----------------------------------------------------------------------
         | Step 6: Update user using prepared statements
         |----------------------------------------------------------------------
         | Two update paths:
         | 1) Without password update (default when password input is empty)
         | 2) With password update (when admin enters a new password)
         */
        if ($newPassword === '') {
            // Keep current password untouched.
            $updateSql = 'UPDATE users SET name = ?, last_name = ?, username = ?, email = ?, contact = ?, role = ?, status = ? WHERE id = ? LIMIT 1';
            $updateStmt = $conn->prepare($updateSql);

            if (!$updateStmt) {
                $errorMessage = 'Unable to prepare update query. Please try again.';
            } else {
                $updateStmt->bind_param(
                    'sssssssi',
                    $formData['name'],
                    $formData['last_name'],
                    $formData['username'],
                    $formData['email'],
                    $formData['contact'],
                    $formData['role'],
                    $formData['status'],
                    $userId
                );

                if ($updateStmt->execute()) {
                    $successMessage = 'User updated successfully.';
                } else {
                    if ((int) $updateStmt->errno === 1062) {
                        $errorMessage = 'Username already exists. Please choose another.';
                    } else {
                        $errorMessage = 'Failed to update user. Please try again.';
                    }
                }

                $updateStmt->close();
            }
        } else {
            // Explicit password change: hash new password and update it.
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);

            $updateSql = 'UPDATE users SET name = ?, last_name = ?, username = ?, email = ?, password = ?, contact = ?, role = ?, status = ? WHERE id = ? LIMIT 1';
            $updateStmt = $conn->prepare($updateSql);

            if (!$updateStmt) {
                $errorMessage = 'Unable to prepare update query. Please try again.';
            } else {
                $updateStmt->bind_param(
                    'ssssssssi',
                    $formData['name'],
                    $formData['last_name'],
                    $formData['username'],
                    $formData['email'],
                    $hashedPassword,
                    $formData['contact'],
                    $formData['role'],
                    $formData['status'],
                    $userId
                );

                if ($updateStmt->execute()) {
                    $successMessage = 'User updated successfully.';
                } else {
                    if ((int) $updateStmt->errno === 1062) {
                        $errorMessage = 'Username already exists. Please choose another.';
                    } else {
                        $errorMessage = 'Failed to update user. Please try again.';
                    }
                }

                $updateStmt->close();
            }
        }

        // Reload latest values from database after successful update.
        if ($successMessage !== '') {
            $refreshStmt = $conn->prepare($selectSql);
            if ($refreshStmt) {
                $refreshStmt->bind_param('i', $userId);
                $refreshStmt->execute();
                $refreshResult = $refreshStmt->get_result();
                $freshData = $refreshResult->fetch_assoc();
                $refreshStmt->close();

                if ($freshData) {
                    $formData = [
                        'name' => $freshData['name'],
                        'last_name' => $freshData['last_name'],
                        'username' => $freshData['username'],
                        'email' => $freshData['email'],
                        'contact' => $freshData['contact'],
                        'role' => $freshData['role'],
                        'status' => $freshData['status'],
                    ];
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit User</title>
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
                <h1 class="title">Edit User</h1>
                <p class="subtitle">Update user details. Password stays unchanged unless a new password is entered.</p>
            </div>
            <a class="btn-link" href="<?php echo htmlspecialchars(app_url('/admin/users.php')); ?>">Back to Users</a>
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

            <?php if ($userData): ?>
                <form method="post" action="">
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
                        <label for="contact">Contact</label>
                        <input id="contact" name="contact" type="text" value="<?php echo htmlspecialchars($formData['contact']); ?>" placeholder="Enter contact number">
                    </div>

                    <div class="field">
                        <label for="password">New Password (Optional)</label>
                        <input id="password" name="password" type="password" placeholder="Leave blank to keep current password" autocomplete="new-password">
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

                    <p class="note">
                        Note: To reset the user's password, enter a new password above. If you want to keep the current password unchanged, simply leave the password field blank.
                    </p>

                    <button class="submit" type="submit">Save Changes</button>
                </form>
            <?php endif; ?>
        </section>
    </div>
</body>
</html>
