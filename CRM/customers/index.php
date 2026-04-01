<?php
session_start();

require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/db.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: ' . app_url('/auth/login.php'));
    exit;
}

$userId = (int) $_SESSION['user_id'];

$customers = [];
$errorMessage = '';

$searchQuery = trim((string) ($_GET['q'] ?? ''));
$projectFilter = (string) ($_GET['project'] ?? 'all');
$projectFilterId = ($projectFilter !== 'all') ? filter_var($projectFilter, FILTER_VALIDATE_INT) : null;
if ($projectFilter !== 'all' && !$projectFilterId) {
    $projectFilter = 'all';
}

$projectOptions = [];
$projectSql = "SELECT DISTINCT p.id, p.name
               FROM projects p
               INNER JOIN customers c ON c.project_id = p.id
               WHERE c.user_id = ?
               ORDER BY p.name ASC";
$projectStmt = $conn->prepare($projectSql);
if ($projectStmt) {
    $projectStmt->bind_param('i', $userId);
    $projectStmt->execute();
    $projectResult = $projectStmt->get_result();
    while ($projectRow = $projectResult->fetch_assoc()) {
        $projectOptions[] = $projectRow;
    }
    $projectStmt->close();
}

$sql = "SELECT c.id, c.name, c.contact, c.flat_type, c.location, c.visited_date, p.name AS project_name
        FROM customers c
        INNER JOIN projects p ON c.project_id = p.id
        WHERE c.user_id = ?";

$paramTypes = 'i';
$paramValues = [$userId];

if ($searchQuery !== '') {
    $sql .= ' AND (c.name LIKE ? OR c.contact LIKE ? OR c.flat_type LIKE ? OR c.location LIKE ? OR p.name LIKE ?)';
    $likeSearch = '%' . $searchQuery . '%';
    $paramTypes .= 'sssss';
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
    $paramValues[] = $likeSearch;
}

if ($projectFilter !== 'all' && $projectFilterId) {
    $sql .= ' AND c.project_id = ?';
    $paramTypes .= 'i';
    $paramValues[] = $projectFilterId;
}

$sql .= ' ORDER BY c.id DESC';
$stmt = $conn->prepare($sql);

if (!$stmt) {
    $errorMessage = 'Unable to fetch customers right now.';
} else {
    $bindValues = array_merge([$paramTypes], $paramValues);
    $bindRefs = [];
    foreach ($bindValues as $key => $value) {
        $bindRefs[$key] = &$bindValues[$key];
    }
    call_user_func_array([$stmt, 'bind_param'], $bindRefs);

    $stmt->execute();
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $customers[] = $row;
    }
    $stmt->close();
}
?>
<!doctype html>
<html lang="en">

<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>My Customers</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --bg: #f7faf7;
      --card: #fff;
      --ink: #1f2933;
      --muted: #52606d;
      --line: #d9e2ec;
      --brand: #2f9e44;
      --brand-2: #0b7285;
      --danger-bg: #ffe3e3;
      --danger-text: #c92a2a;
      --shadow: 0 18px 45px rgba(16, 42, 67, .12);
    }

    * {
      box-sizing: border-box
    }

    body {
      margin: 0;
      font-family: 'Manrope', sans-serif;
      color: var(--ink);
      background: radial-gradient(circle at left top, rgba(47, 158, 68, .13), transparent 45%), var(--bg);
      min-height: 100vh;
      padding: 24px
    }

    .layout {
      width: min(1150px, 100%);
      margin: 0 auto;
      display: grid;
      gap: 18px
    }

    .topbar,
    .card {
      background: var(--card);
      border: 1px solid var(--line);
      border-radius: 16px;
      box-shadow: var(--shadow)
    }

    .topbar {
      padding: 16px 18px;
      display: flex;
      justify-content: space-between;
      align-items: center;
      gap: 10px;
      flex-wrap: wrap
    }

    .title {
      margin: 0;
      font-size: 24px;
      font-weight: 800
    }

    .subtitle {
      margin: 4px 0 0;
      color: var(--muted);
      font-size: 14px
    }

    .actions {
      display: flex;
      gap: 10px;
      flex-wrap: wrap
    }

    .btn {
      text-decoration: none;
      border-radius: 10px;
      padding: 10px 14px;
      font-weight: 700;
      font-size: 14px;
      transition: transform 120ms ease
    }

    .btn:hover {
      transform: translateY(-1px)
    }

    .btn-primary {
      color: #fff;
      background: linear-gradient(120deg, var(--brand), var(--brand-2))
    }

    .btn-ghost {
      color: var(--ink);
      background: #fff;
      border: 1px solid var(--line)
    }

    .filters {
      display: grid;
      grid-template-columns: 2fr 1fr auto;
      gap: 10px;
      align-items: end;
      margin-bottom: 12px
    }

    .filter-field {
      display: grid;
      gap: 6px
    }

    .filter-label {
      font-size: 12px;
      font-weight: 700;
      color: var(--muted)
    }

    .filter-input {
      width: 100%;
      border: 1px solid var(--line);
      border-radius: 10px;
      padding: 10px 12px;
      font-size: 14px;
      outline: none;
      background: #fff;
      font-family: inherit
    }

    .filter-input:focus {
      border-color: var(--brand);
      box-shadow: 0 0 0 4px rgba(11, 114, 133, .14)
    }

    .filter-actions {
      display: flex;
      gap: 8px;
      align-items: center
    }

    .card {
      padding: 16px
    }

    .table-wrap {
      overflow-x: auto;
      border-radius: 12px;
      border: 1px solid var(--line)
    }

    table {
      width: 100%;
      border-collapse: collapse;
      min-width: 920px;
      background: #fff
    }

    thead th {
      text-align: left;
      font-size: 13px;
      color: var(--muted);
      font-weight: 800;
      background: #f8fbfe;
      border-bottom: 1px solid var(--line);
      padding: 12px 14px
    }

    tbody td {
      padding: 12px 14px;
      border-bottom: 1px solid #edf2f7;
      font-size: 14px
    }

    tbody tr:hover {
      background: #f9fcff
    }

    .action-inline {
      display: flex;
      gap: 8px;
      align-items: center;
      flex-wrap: wrap
    }

    .action-btn {
      text-decoration: none;
      font-size: 13px;
      font-weight: 700;
      color: #fff;
      background: linear-gradient(120deg, var(--brand), var(--brand-2));
      border-radius: 8px;
      padding: 7px 11px;
      display: inline-block
    }

    .delete-btn {
      text-decoration: none;
      font-size: 13px;
      font-weight: 700;
      color: #fff;
      background: #c92a2a;
      border-radius: 8px;
      padding: 7px 11px;
      display: inline-block
    }

    .empty {
      margin: 0;
      color: var(--muted);
      font-size: 14px;
      padding: 14px;
      border: 1px dashed var(--line);
      border-radius: 12px;
      background: #f8fbfe
    }

    .error {
      margin: 0;
      padding: 10px 12px;
      border-radius: 10px;
      background: var(--danger-bg);
      color: var(--danger-text);
      border: 1px solid #ffc9c9;
      font-size: 14px;
      font-weight: 700
    }

    @media (max-width:900px) {
      .filters {
        grid-template-columns: 1fr
      }

      .filter-actions {
        justify-content: flex-start
      }
    }
  </style>
  <link rel="stylesheet" href="<?php echo htmlspecialchars(app_url('/assets/css/crm-theme.css')); ?>">
</head>

<body>
  <div class="layout">
    <header class="topbar">
      <div>
        <h1 class="title">My Customers</h1>
        <p class="subtitle">Only customers created by your account are shown here.</p>
      </div>
      <div class="actions">
        <a class="btn btn-primary" href="<?php echo htmlspecialchars(app_url('/customers/create.php')); ?>">Create
          Customer</a>
        <a class="btn btn-ghost" href="<?php echo htmlspecialchars(app_url('/user/dashboard.php')); ?>">Back to
          Dashboard</a>
      </div>
    </header>

    <section class="card">
      <form class="filters" method="get" action="">
        <div class="filter-field">
          <label class="filter-label" for="q">Quick Search</label>
          <input class="filter-input" id="q" name="q" type="text" value="<?php echo htmlspecialchars($searchQuery); ?>"
            placeholder="Search name, contact, flat type, location, project">
        </div>

        <div class="filter-field">
          <label class="filter-label" for="project">Project Filter</label>
          <select class="filter-input" id="project" name="project">
            <option value="all" <?php echo $projectFilter === 'all' ? 'selected' : ''; ?>>All Projects</option>
            <?php foreach ($projectOptions as $projectOption): ?>
              <?php $projectOptionId = (int) $projectOption['id']; ?>
              <option value="<?php echo $projectOptionId; ?>" <?php echo ((string) $projectOptionId === (string) $projectFilter) ? 'selected' : ''; ?>>
                <?php echo htmlspecialchars($projectOption['name']); ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="filter-actions">
          <button class="btn btn-primary" type="submit">Apply</button>
          <a class="btn btn-ghost" href="<?php echo htmlspecialchars(app_url('/customers/index.php')); ?>">Reset</a>
        </div>
      </form>

      <?php if ($errorMessage !== ''): ?>
        <p class="error"><?php echo htmlspecialchars($errorMessage); ?></p>
      <?php elseif (count($customers) === 0): ?>
        <p class="empty">No customers found. Click Create Customer to add your first customer.</p>
      <?php else: ?>
        <div class="table-wrap">
          <table>
            <thead>
              <tr>
                <th>Name</th>
                <th>Contact</th>
                <th>Flat Type</th>
                <th>Location</th>
                <th>Project Name</th>
                <th>Visited Date</th>
                <th>Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($customers as $customer): ?>
                <tr>
                  <td><?php echo htmlspecialchars($customer['name']); ?></td>
                  <td><?php echo htmlspecialchars($customer['contact']); ?></td>
                  <td><?php echo htmlspecialchars($customer['flat_type']); ?></td>
                  <td><?php echo htmlspecialchars($customer['location']); ?></td>
                  <td><?php echo htmlspecialchars($customer['project_name']); ?></td>
                  <td><?php echo htmlspecialchars($customer['visited_date']); ?></td>
                  <td>
                    <div class="action-inline">
                      <a class="action-btn"
                        href="<?php echo htmlspecialchars(app_url('/customers/view.php?id=' . (int) $customer['id'])); ?>">View</a>
                      <a class="action-btn"
                        href="<?php echo htmlspecialchars(app_url('/customers/edit.php?id=' . (int) $customer['id'])); ?>">Edit</a>
                      <a class="delete-btn"
                        href="<?php echo htmlspecialchars(app_url('/customers/delete.php?id=' . (int) $customer['id'])); ?>"
                        onclick="return confirm('Delete this customer?');">Delete</a>
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
