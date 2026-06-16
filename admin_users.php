<?php
require_once 'config.php';

if (!isAdminLoggedIn()) {
    redirect('admin_login.php');
}

// Delete user
if (isset($_GET['delete'])) {
    $stmt = $pdo->prepare("DELETE FROM feeder_users WHERE id = ? AND role != 'admin'");
    $stmt->execute([$_GET['delete']]);
    redirect('admin_users.php');
}

$users = $pdo->query("SELECT * FROM feeder_users ORDER BY created_at DESC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users - Admin</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; background: #f8f9fa; }
        .sidebar {
            position: fixed; left: 0; top: 0; width: 260px; height: 100%;
            background: linear-gradient(135deg, #1a1a2e, #16213e); color: white; padding: 20px 0;
        }
        .sidebar-header { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-header h2 { font-size: 22px; }
        .nav-menu { list-style: none; padding: 0 15px; }
        .nav-menu a { display: block; padding: 12px 15px; color: rgba(255,255,255,0.8); text-decoration: none; border-radius: 10px; margin-bottom: 5px; }
        .nav-menu a:hover { background: rgba(255,255,255,0.1); }
        .nav-menu a.active { background: linear-gradient(135deg, #667eea, #764ba2); }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-bar { background: white; padding: 15px 25px; border-radius: 12px; margin-bottom: 25px; display: flex; justify-content: space-between; align-items: center; }
        .page-title h1 { font-size: 24px; background: linear-gradient(135deg, #667eea, #764ba2); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .logout-btn { padding: 8px 20px; background: #dc3545; color: white; text-decoration: none; border-radius: 8px; }
        .section-card { background: white; border-radius: 16px; padding: 24px; border: 1px solid #e9ecef; }
        .section-title { font-size: 18px; font-weight: 700; margin-bottom: 20px; padding-bottom: 12px; border-bottom: 2px solid #f0f0f0; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e9ecef; }
        th { background: #f8f9fa; font-weight: 600; }
        .btn-sm { padding: 4px 10px; background: #dc3545; color: white; text-decoration: none; border-radius: 6px; font-size: 12px; }
        @media (max-width: 768px) { .sidebar { width: 70px; } .sidebar-header h2 { display: none; } .main-content { margin-left: 70px; } }
    </style>
</head>
<body>
    <div class="sidebar">
    <div class="sidebar-header"><h2>Smart Feeder</h2></div>
    <ul class="nav-menu">
        <li><a href="admin_dashboard.php">📊 Dashboard</a></li>
        <li><a href="admin_users.php" class="active">👥 Users</a></li>
        <li><a href="admin_pets.php">🐾 Pets</a></li>
        <li><a href="admin_schedules.php">⏰ Schedules</a></li>
        <li><a href="admin_feeding_history.php">📋 Feeding History</a></li>
    </ul>
</div>
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title"><h1>Manage Users</h1></div>
            <div><span>Welcome, <?php echo $_SESSION['admin_username']; ?></span> <a href="admin_logout.php" class="logout-btn">Logout</a></div>
        </div>
        <div class="section-card">
            <div class="section-title">All Users</div>
            <table>
                <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Phone</th><th>Role</th><th>Registered</th><th>Action</th></tr></thead>
                <tbody>
                    <?php foreach($users as $user): ?>
                    <tr>
                        <td><?php echo $user['id']; ?></td>
                        <td><?php echo htmlspecialchars($user['username']); ?></td>
                        <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                        <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                        <td><?php echo $user['role']; ?></td>
                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                        <td><?php if ($user['role'] !== 'admin'): ?><a href="?delete=<?php echo $user['id']; ?>" class="btn-sm" onclick="return confirm('Delete this user?')">Delete</a><?php else: ?>Protected<?php endif; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>