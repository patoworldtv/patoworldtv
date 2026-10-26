<?php
declare(strict_types=1);

require __DIR__ . '/../../app/bootstrap.php';

$admin = require_admin($pdo);

// Fetch all users
$users = $pdo->query("
    SELECT id, username, email, is_admin, is_blocked, created_at
    FROM users
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <link rel="stylesheet" href="../assets/css/style.css?v=9999">
  <title>Manage Users • Admin</title>

  <style>
    table {
      width: 100%;
      border-collapse: collapse;
      margin-top: 18px;
      background: #fff;
      border-radius: 10px;
      overflow: hidden;
    }
    th, td {
      padding: 10px 12px;
      border-bottom: 1px solid #eee;
      text-align: left;
    }
    th {
      background: #f5f5f5;
      font-weight: bold;
    }
    tr:last-child td {
      border-bottom: none;
    }

    /* Toggle Switch (Display Only) */
    .switch {
      position: relative;
      display: inline-block;
      width: 46px;
      height: 24px;
    }
    .switch input {
      opacity: 0;
      width: 0;
      height: 0;
    }
    .slider {
      position: absolute;
      cursor: default;
      top: 0;
      left: 0;
      right: 0;
      bottom: 0;
      background-color: #ccc;
      transition: .3s;
      border-radius: 24px;
    }
    .slider:before {
      position: absolute;
      content: "";
      height: 18px;
      width: 18px;
      left: 3px;
      bottom: 3px;
      background-color: white;
      transition: .3s;
      border-radius: 50%;
    }
    input:checked + .slider {
      background-color: #2196F3;
    }
    input:checked + .slider:before {
      transform: translateX(22px);
    }
  </style>
</head>
<body>

<div class="page-content">
  <div class="container">

    <h2>Manage Users</h2>
    <p><a class="btn-link" href="../dashboard.php">← Back to Dashboard</a></p>

    <table>
      <tr>
        <th>ID</th>
        <th>Username</th>
        <th>Email</th>
        <th>Admin</th>
        <th>Blocked</th>
        <th>Actions</th>
      </tr>

      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td><?= e($u['username']) ?></td>
        <td><?= e($u['email']) ?></td>

        <!-- ADMIN TOGGLE (Display Only) -->
        <td>
          <label class="switch">
            <input type="checkbox" disabled <?= $u['is_admin'] ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </td>

        <!-- BLOCKED TOGGLE (Display Only) -->
        <td>
          <label class="switch">
            <input type="checkbox" disabled <?= $u['is_blocked'] ? 'checked' : '' ?>>
            <span class="slider"></span>
          </label>
        </td>

        <td>

          <!-- ADMIN RIGHTS BUTTON -->
          <form method="post" action="user_toggle_admin.php" style="display:inline;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
            <button class="btn-outline">
              <?= $u['is_admin'] ? 'Restrict Rights' : 'Give Rights' ?>
            </button>
          </form>

          <!-- BLOCK / UNBLOCK BUTTON -->
          <form method="post" action="user_block.php" style="display:inline;">
            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
            <input type="hidden" name="block" value="<?= $u['is_blocked'] ? 0 : 1 ?>">
            <button class="btn-outline">
              <?= $u['is_blocked'] ? 'Unblock' : 'Block' ?>
            </button>
          </form>

        </td>
      </tr>
      <?php endforeach; ?>

    </table>

  </div>
</div>

</body>
</html>
