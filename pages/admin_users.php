<?php
session_start();
include '../config/config.php';

// Only admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header("Location: login.php");
    exit();
}

$result = $conn->query("SELECT id, username, email, role, zone, created_at FROM users ORDER BY id ASC");
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Manage Users | EmoNeighbour</title>

    <!-- Use your main dashboard styling -->
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <!-- Table/page-specific styling -->
    <link rel="stylesheet" href="../assets/css/admin_users.css">
</head>
<body>

<div class="dash-container">

    <!-- Top header card -->
    <div class="welcome-box compact page-head">
        <div class="page-head-row">
            <div>
                <h2 class="page-title">User Management</h2>
                <p class="page-subtitle">Create, edit, reset passwords, and remove users.</p>
            </div>

            <a href="admin_dashboard.php" class="action-btn back-btn">← Back</a>
        </div>

        <div class="action-bar">
            <a href="admin_add_user.php" class="action-btn primary">+ Add New User</a>
        </div>
    </div>

    <!-- Table card -->
    <div class="card">
        <h3 style="margin-top:0;">All Users</h3>

        <div class="table-wrap">
            <table class="data-table">
                <thead>
                    <tr>
                        <th style="width:70px;">ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th style="width:140px;">Role</th>
                        <th style="width:110px;">Block</th>
                        <th style="width:210px;">Created At</th>
                        <th style="width:260px;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php if ($result && $result->num_rows > 0): ?>
                    <?php while ($row = $result->fetch_assoc()) : ?>
                        <?php
                            $role = $row['role'];
                            $zoneText = ($role === 'resident') ? $row['zone'] : '-';

                            $roleClass = 'pill';
                            if ($role === 'admin') $roleClass .= ' pill-admin';
                            elseif ($role === 'leader') $roleClass .= ' pill-leader';
                            else $roleClass .= ' pill-resident';
                        ?>
                        <tr>
                            <td><?= (int)$row['id'] ?></td>
                            <td class="strong"><?= htmlspecialchars($row['username']) ?></td>
                            <td><?= htmlspecialchars($row['email']) ?></td>
                            <td><span class="<?= $roleClass ?>"><?= htmlspecialchars($role) ?></span></td>
                            <td><?= htmlspecialchars($zoneText) ?></td>
                            <td><?= htmlspecialchars($row['created_at']) ?></td>
                            <td>
                                <div class="row-actions">
                                    <a class="mini-btn" href="admin_edit_user.php?id=<?= (int)$row['id'] ?>">Edit</a>
                                    <a class="mini-btn" href="admin_reset_password.php?id=<?= (int)$row['id'] ?>">Reset PW</a>

                                    <?php if ((int)$_SESSION['user_id'] !== (int)$row['id']) : ?>
                                        <a class="mini-btn danger"
                                           href="admin_delete_user.php?id=<?= (int)$row['id'] ?>"
                                           onclick="return confirm('Delete this user?');">
                                            Delete
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endwhile; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" class="empty-row">No users found.</td>
                    </tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <p class="table-hint">Tip: on smaller screens, scroll horizontally to view all columns.</p>
    </div>

</div>

</body>
</html>
