<?php
require_once 'config.php';

if (!isAdminLoggedIn()) {
    redirect('admin_login.php');
}

// Get statistics
$total_users = $pdo->query("SELECT COUNT(*) FROM feeder_users WHERE role = 'user'")->fetchColumn();
$total_pets = $pdo->query("SELECT COUNT(*) FROM feeder_pets")->fetchColumn();
$total_feeds = $pdo->query("SELECT COUNT(*) FROM feeder_history")->fetchColumn();
$total_schedules = $pdo->query("SELECT COUNT(*) FROM feeder_schedule")->fetchColumn();
$online_devices = $pdo->query("SELECT COUNT(*) FROM feeder_device_status WHERE is_online = 1")->fetchColumn();

// Get recent users
$stmt = $pdo->prepare("SELECT * FROM feeder_users WHERE role = 'user' ORDER BY created_at DESC LIMIT 10");
$stmt->execute();
$recent_users = $stmt->fetchAll();

// Get recent device activity (optional - can add last 5 feeding records quickly)
try {
    $stmt = $pdo->prepare("SELECT fh.*, u.username, p.pet_name 
                           FROM feeder_history fh 
                           JOIN feeder_users u ON fh.user_id = u.id 
                           JOIN feeder_pets p ON fh.pet_id = p.id 
                           ORDER BY fh.feed_time DESC LIMIT 5");
    $stmt->execute();
    $recent_feeds_quick = $stmt->fetchAll();
} catch (PDOException $e) {
    $recent_feeds_quick = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Smart Pet Feeder</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f8f9fa;
            color: #212529;
        }
        .sidebar {
            position: fixed;
            left: 0;
            top: 0;
            width: 260px;
            height: 100%;
            background: linear-gradient(135deg, #1a1a2e, #16213e);
            color: white;
            padding: 20px 0;
        }
        .sidebar-header { text-align: center; padding: 20px; border-bottom: 1px solid rgba(255,255,255,0.1); margin-bottom: 20px; }
        .sidebar-header h2 { font-size: 22px; }
        .nav-menu { list-style: none; padding: 0 15px; }
        .nav-menu a {
            display: block;
            padding: 12px 15px;
            color: rgba(255,255,255,0.8);
            text-decoration: none;
            border-radius: 10px;
            margin-bottom: 5px;
        }
        .nav-menu a:hover { background: rgba(255,255,255,0.1); color: white; }
        .nav-menu a.active { background: linear-gradient(135deg, #667eea, #764ba2); color: white; }
        .main-content { margin-left: 260px; padding: 20px; }
        .top-bar {
            background: white;
            padding: 15px 25px;
            border-radius: 12px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .page-title h1 {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        .logout-btn {
            padding: 8px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 8px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }
        .stat-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            border: 1px solid #e9ecef;
        }
        .stat-card .icon { font-size: 32px; margin-bottom: 12px; }
        .stat-card .number { font-size: 32px; font-weight: 700; color: #667eea; }
        .stat-card .label { color: #6c757d; font-size: 14px; margin-top: 5px; }
        .section-card {
            background: white;
            border-radius: 16px;
            padding: 24px;
            margin-bottom: 24px;
            border: 1px solid #e9ecef;
        }
        .section-title {
            font-size: 18px;
            font-weight: 700;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e9ecef; }
        th { background: #f8f9fa; font-weight: 600; }
        .recent-feed-item {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .recent-feed-pet { font-weight: 600; }
        .recent-feed-time { font-size: 12px; color: #6c757d; }
        .view-all-link {
            display: inline-block;
            margin-top: 15px;
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        @media (max-width: 768px) {
            .sidebar { width: 70px; }
            .sidebar-header h2 { display: none; }
            .main-content { margin-left: 70px; }
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header"><h2>Smart Feeder</h2></div>
        <ul class="nav-menu">
            <li><a href="admin_dashboard.php" class="active">📊 Dashboard</a></li>
            <li><a href="admin_users.php">👥 Users</a></li>
            <li><a href="admin_pets.php">🐾 Pets</a></li>
            <li><a href="admin_schedules.php">⏰ Schedules</a></li>
            <li><a href="admin_feeding_history.php">📋 Feeding History</a></li>
        </ul>
    </div>
    <div class="main-content">
        <div class="top-bar">
            <div class="page-title"><h1>Admin Dashboard</h1></div>
            <div><span>Welcome, <?php echo $_SESSION['admin_username']; ?></span> <a href="admin_logout.php" class="logout-btn">Logout</a></div>
        </div>
        <div class="stats-grid">
            <div class="stat-card"><div class="icon">👥</div><div class="number"><?php echo $total_users; ?></div><div class="label">Total Users</div></div>
            <div class="stat-card"><div class="icon">🐾</div><div class="number"><?php echo $total_pets; ?></div><div class="label">Total Pets</div></div>
            <div class="stat-card"><div class="icon">🍖</div><div class="number"><?php echo $total_feeds; ?></div><div class="label">Total Feeds</div></div>
            <div class="stat-card"><div class="icon">⏰</div><div class="number"><?php echo $total_schedules; ?></div><div class="label">Schedules</div></div>
            <div class="stat-card"><div class="icon">📡</div><div class="number"><?php echo $online_devices; ?></div><div class="label">Online Devices</div></div>
        </div>
        <div class="section-card">
            <div class="section-title">Recent Users</div>
            <table>
                <thead><tr><th>ID</th><th>Username</th><th>Full Name</th><th>Email</th><th>Registered</th></tr></thead>
                <tbody>
                    <?php foreach($recent_users as $user): ?>
                    <tr><td><?php echo $user['id']; ?></td><td><?php echo htmlspecialchars($user['username']); ?></td><td><?php echo htmlspecialchars($user['full_name']); ?></td><td><?php echo htmlspecialchars($user['email']); ?></td><td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td></tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Quick Recent Activity Preview -->
        <div class="section-card">
            <div class="section-title">📋 Recent Feeding Activity</div>
            <?php if (count($recent_feeds_quick) > 0): ?>
                <?php foreach($recent_feeds_quick as $feed): ?>
                <div class="recent-feed-item">
                    <div>
                        <span class="recent-feed-pet"><?php echo htmlspecialchars($feed['pet_name']); ?></span>
                        <span class="recent-feed-time">• <?php echo htmlspecialchars($feed['username']); ?></span>
                        <div class="recent-feed-time"><?php echo date('M j, g:i A', strtotime($feed['feed_time'])); ?></div>
                    </div>
                    <div>
                        <span style="background:#e9ecef; padding:4px 8px; border-radius:12px; font-size:12px;"><?php echo $feed['portion_size']; ?>g</span>
                        <span style="margin-left:8px;"><?php echo $feed['status'] == 'success' ? '✅' : '❌'; ?></span>
                    </div>
                </div>
                <?php endforeach; ?>
                <a href="admin_feeding_history.php" class="view-all-link">View All Feeding History →</a>
            <?php else: ?>
                <div class="empty-state" style="text-align:center; padding:20px; color:#6c757d;">No feeding records yet.</div>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>