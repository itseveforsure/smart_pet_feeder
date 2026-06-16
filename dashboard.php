<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$dark_mode = getDarkMode($user_id);

// Get data with error handling
$pets = [];
$feeding_history = [];
$water_level = null;
$schedules = [];
$notifications = [];
$unread_count = 0;
$device_status = null;

try {
    $pets = getUserPets($user_id);
} catch(Exception $e) { echo "Error getting pets: " . $e->getMessage(); }

try {
    $feeding_history = getFeedingHistory($user_id, 10);
} catch(Exception $e) { echo "Error getting history: " . $e->getMessage(); }

try {
    $water_level = getWaterLevel($user_id);
} catch(Exception $e) { echo "Error getting water level: " . $e->getMessage(); }

try {
    $schedules = getActiveSchedules($user_id);
} catch(Exception $e) { echo "Error getting schedules: " . $e->getMessage(); }

try {
    $notifications = getNotifications($user_id, 5);
    $unread_count = getUnreadNotificationsCount($user_id);
} catch(Exception $e) { echo "Error getting notifications: " . $e->getMessage(); }

try {
    $device_status = getDeviceStatus();
} catch(Exception $e) { echo "Error getting device status: " . $e->getMessage(); }

// ========== FEEDING ANALYTICS & INSIGHTS ==========
$analytics = [];
$feeding_trends = [];
$hourly_distribution = [];
$weekly_distribution = [];
$source_ratio = [];
$time_range_stats = [];

try {
    // Basic 30-day stats
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(*) as total_feeds,
            SUM(portion_size) as total_food_dispensed,
            AVG(portion_size) as avg_portion,
            COUNT(DISTINCT DATE(feed_time)) as active_days
        FROM feeder_history 
        WHERE user_id = ? AND feed_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute([$user_id]);
    $analytics = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Daily feeding trend (last 7 days)
    $stmt = $pdo->prepare("
        SELECT 
            DATE(feed_time) as date,
            COUNT(*) as feed_count
        FROM feeder_history 
        WHERE user_id = ? AND feed_time >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY DATE(feed_time)
        ORDER BY date ASC
    ");
    $stmt->execute([$user_id]);
    $feeding_trends = $stmt->fetchAll();
    
    // Hourly distribution
    $stmt = $pdo->prepare("
        SELECT 
            HOUR(feed_time) as hour,
            COUNT(*) as feed_count
        FROM feeder_history 
        WHERE user_id = ? AND feed_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY HOUR(feed_time)
        ORDER BY feed_count DESC
        LIMIT 5
    ");
    $stmt->execute([$user_id]);
    $hourly_distribution = $stmt->fetchAll();
    
    // Weekly distribution
    $stmt = $pdo->prepare("
        SELECT 
            DAYNAME(feed_time) as day_name,
            COUNT(*) as feed_count
        FROM feeder_history 
        WHERE user_id = ? AND feed_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DAYNAME(feed_time), DAYOFWEEK(feed_time)
        ORDER BY DAYOFWEEK(feed_time)
    ");
    $stmt->execute([$user_id]);
    $weekly_distribution = $stmt->fetchAll();
    
    // Manual vs Scheduled ratio
    $stmt = $pdo->prepare("
        SELECT 
            source,
            COUNT(*) as count
        FROM feeder_history 
        WHERE user_id = ? AND feed_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY source
    ");
    $stmt->execute([$user_id]);
    $source_ratio = $stmt->fetchAll();
    
    // Time range stats
    $stmt = $pdo->prepare("
        SELECT 
            CASE 
                WHEN HOUR(feed_time) BETWEEN 0 AND 5 THEN 'Late Night (12am-6am)'
                WHEN HOUR(feed_time) BETWEEN 6 AND 11 THEN 'Morning (6am-12pm)'
                WHEN HOUR(feed_time) BETWEEN 12 AND 17 THEN 'Afternoon (12pm-6pm)'
                ELSE 'Evening (6pm-12am)'
            END as time_range,
            COUNT(*) as feed_count
        FROM feeder_history 
        WHERE user_id = ? AND feed_time >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY time_range
        ORDER BY feed_count DESC
    ");
    $stmt->execute([$user_id]);
    $time_range_stats = $stmt->fetchAll();
    
} catch(Exception $e) {}

// Handle manual feeding
$feed_success = null;
if (isset($_POST['manual_feed']) && !empty($pets)) {
    $pet_id = $_POST['pet_id'];
    $fixed_portion = 50;
    
    try {
        $stmt = $pdo->prepare("INSERT INTO feeder_history (user_id, pet_id, portion_size, status, source) VALUES (?, ?, ?, 'success', 'manual')");
        if ($stmt->execute([$user_id, $pet_id, $fixed_portion])) {
            addNotification($user_id, '✅ Manual Feeding', "Dispensed {$fixed_portion}g of food for your pet.", 'success');
            $feed_success = true;
            redirect('dashboard.php');
        } else {
            addNotification($user_id, '❌ Feeding Failed', 'Manual feeding failed. Please check the device.', 'warning');
            $feed_success = false;
        }
    } catch(Exception $e) {
        $feed_success = false;
    }
}

// Toggle dark mode
if (isset($_POST['toggle_dark_mode'])) {
    $new_mode = $dark_mode ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE feeder_users SET dark_mode = ? WHERE id = ?");
    $stmt->execute([$new_mode, $user_id]);
    redirect('dashboard.php');
}

// Mark notification as read
if (isset($_GET['read_notification'])) {
    $stmt = $pdo->prepare("UPDATE feeder_notifications SET is_read = 1 WHERE id = ? AND user_id = ?");
    $stmt->execute([$_GET['read_notification'], $user_id]);
    redirect('dashboard.php');
}

// Mark all as read
if (isset($_GET['read_all'])) {
    $stmt = $pdo->prepare("UPDATE feeder_notifications SET is_read = 1 WHERE user_id = ?");
    $stmt->execute([$user_id]);
    redirect('dashboard.php');
}

// Get today's feed count
$today_feeds = 0;
try {
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feeder_history WHERE user_id = ? AND DATE(feed_time) = ?");
    $stmt->execute([$user_id, $today]);
    $today_feeds = $stmt->fetchColumn();
} catch(Exception $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Smart Pet Feeder</title>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
            background: #f8f9fa;
            color: #212529;
        }
        body.dark-mode {
            background: #1a1a2e;
            color: #e0e0e0;
        }
        body.dark-mode .card,
        body.dark-mode .stat-card,
        body.dark-mode .navbar {
            background: #16213e;
            border-color: #2d3748;
        }
        body.dark-mode .schedule-item,
        body.dark-mode .chart-container,
        body.dark-mode .fixed-portion-info {
            background: #1a1a2e;
        }
        .navbar {
            background: white;
            border-bottom: 1px solid #e9ecef;
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        .nav-container {
            max-width: 1400px;
            margin: 0 auto;
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 16px 0;
        }
        .logo {
            font-size: 24px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-decoration: none;
        }
        .nav-links {
            display: flex;
            gap: 24px;
            align-items: center;
            flex-wrap: wrap;
        }
        .nav-links a {
            color: #6c757d;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }
        .nav-links a:hover { color: #667eea; }
        .dark-mode-btn {
            background: none;
            border: 1px solid #e9ecef;
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
        }
        .notification-icon {
            position: relative;
            cursor: pointer;
        }
        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #dc3545;
            color: white;
            font-size: 10px;
            padding: 2px 6px;
            border-radius: 10px;
        }
        .notification-dropdown {
            display: none;
            position: absolute;
            right: 0;
            top: 35px;
            width: 320px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            z-index: 1000;
        }
        body.dark-mode .notification-dropdown {
            background: #16213e;
        }
        .notification-dropdown.show {
            display: block;
        }
        .notification-header {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            font-weight: 600;
        }
        .notification-item {
            padding: 12px;
            border-bottom: 1px solid #e9ecef;
            cursor: pointer;
            transition: background 0.2s;
        }
        .notification-item:hover {
            background: #f8f9fa;
        }
        body.dark-mode .notification-item:hover {
            background: #1a1a2e;
        }
        .notification-title { font-weight: 600; font-size: 14px; }
        .notification-message { font-size: 12px; color: #6c757d; margin-top: 4px; }
        .notification-time { font-size: 10px; color: #adb5bd; margin-top: 4px; }
        .notification-empty { padding: 20px; text-align: center; color: #6c757d; }
        .container { max-width: 1400px; margin: 0 auto; padding: 30px 32px; }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }
        .stat-card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #e9ecef;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .stat-card h3 { font-size: 14px; color: #6c757d; margin-bottom: 12px; }
        .stat-value { font-size: 36px; font-weight: 700; margin-bottom: 12px; }
        .progress-bar { background: #e9ecef; border-radius: 10px; height: 8px; overflow: hidden; }
        .progress-fill {
            background: linear-gradient(90deg, #667eea, #764ba2);
            height: 100%;
            border-radius: 10px;
            transition: width 0.5s;
        }
        .danger { color: #dc3545; }
        .success { color: #28a745; }
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 24px;
            margin-bottom: 32px;
        }
        .card {
            background: white;
            border-radius: 20px;
            padding: 24px;
            border: 1px solid #e9ecef;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .card h2 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f0f0;
        }
        .feed-btn {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 14px 24px;
            border-radius: 50px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            width: 100%;
        }
        .feed-btn:hover { opacity: 0.9; }
        .schedule-item {
            background: #f8f9fa;
            padding: 12px 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        .schedule-time { font-weight: 700; color: #667eea; }
        .fixed-portion-info {
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 8px;
            font-size: 12px;
            color: #6c757d;
            text-align: center;
            margin-bottom: 16px;
        }
        .history-table { width: 100%; border-collapse: collapse; }
        .history-table th, .history-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        .history-table th { background: #f8f9fa; font-weight: 600; }
        select {
            padding: 10px 12px;
            border-radius: 10px;
            border: 1px solid #e9ecef;
            width: 100%;
            margin-top: 8px;
            font-size: 14px;
        }
        label { font-weight: 500; font-size: 14px; }
        .empty-state { text-align: center; padding: 40px; color: #6c757d; }
        .analytics-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-bottom: 24px;
        }
        .analytics-card {
            background: linear-gradient(135deg, #667eea15, #764ba215);
            border-radius: 16px;
            padding: 16px;
            text-align: center;
        }
        .analytics-number { font-size: 28px; font-weight: 800; color: #667eea; }
        .analytics-label { font-size: 12px; color: #6c757d; margin-top: 5px; }
        .insight-badge {
            background: #28a745;
            color: white;
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 11px;
            display: inline-block;
        }
        .chart-container {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 12px;
        }
        canvas { max-height: 200px; width: 100%; }
        .insights-list { list-style: none; padding: 0; }
        .insights-list li {
            padding: 10px 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            flex-wrap: wrap;
        }
        @media (max-width: 768px) {
            .two-columns { grid-template-columns: 1fr; }
            .nav-container { flex-direction: column; gap: 12px; }
            .container { padding: 20px; }
            .analytics-grid { grid-template-columns: repeat(2, 1fr); }
        }
    </style>
    <?php if ($dark_mode): ?>
    <script>document.body.classList.add('dark-mode');</script>
    <?php endif; ?>
</head>
<body>
    <nav class="navbar">
        <div class="nav-container">
            <a href="dashboard.php" class="logo">🐾 Smart PetFeeder</a>
            <div class="nav-links">
                <a href="dashboard.php">Dashboard</a>
                <a href="pet_profile.php">My Pets</a>
                <a href="schedule.php">Schedule</a>
                <a href="profile.php">Profile</a>
                <div class="notification-icon" onclick="toggleNotifications()">
                    🔔
                    <?php if ($unread_count > 0): ?>
                        <span class="notification-badge"><?php echo $unread_count; ?></span>
                    <?php endif; ?>
                    <div id="notificationDropdown" class="notification-dropdown">
                        <div class="notification-header">
                            Notifications
                            <?php if ($unread_count > 0): ?>
                                <a href="?read_all=1" style="float: right; font-size: 11px; color: #667eea; text-decoration: none;">Mark all read</a>
                            <?php endif; ?>
                        </div>
                        <div style="max-height: 300px; overflow-y: auto;">
                            <?php if (count($notifications) > 0): ?>
                                <?php foreach($notifications as $notif): ?>
                                    <div class="notification-item" onclick="location.href='?read_notification=<?php echo $notif['id']; ?>'">
                                        <div class="notification-title"><?php echo htmlspecialchars($notif['title']); ?></div>
                                        <div class="notification-message"><?php echo htmlspecialchars($notif['message']); ?></div>
                                        <div class="notification-time"><?php echo date('M j, g:i A', strtotime($notif['created_at'])); ?></div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="notification-empty">No notifications</div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <form method="POST" style="display: inline;">
                    <button type="submit" name="toggle_dark_mode" class="dark-mode-btn">
                        <?php echo $dark_mode ? '☀️ Light' : '🌙 Dark'; ?>
                    </button>
                </form>
                <a href="logout.php">🚪 Logout</a>
            </div>
        </div>
    </nav>
    
    <div class="container">
        <!-- Stats Cards -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Device Status</h3>
                <div class="stat-value"><?php echo ($device_status && $device_status['is_online']) ? '🟢 Online' : '🔴 Offline'; ?></div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo ($device_status && $device_status['is_online']) ? '100%' : '0%'; ?>"></div></div>
            </div>
            <div class="stat-card">
                <h3>Food Level</h3>
                <div class="stat-value <?php echo ($device_status['food_level'] ?? 100) < 20 ? 'danger' : ''; ?>"><?php echo $device_status['food_level'] ?? 100; ?>%</div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $device_status['food_level'] ?? 100; ?>%"></div></div>
                <?php if (($device_status['food_level'] ?? 100) < 20): ?>
                    <p style="color: #dc3545; font-size: 12px; margin-top: 8px;">⚠️ Low food! Please refill.</p>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <h3>Water Level</h3>
                <div class="stat-value <?php echo ($water_level['water_level_percent'] ?? 100) < 20 ? 'danger' : ''; ?>"><?php echo $water_level['water_level_percent'] ?? 100; ?>%</div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo $water_level['water_level_percent'] ?? 100; ?>%"></div></div>
                <?php if (($water_level['water_level_percent'] ?? 100) < 20): ?>
                    <p style="color: #dc3545; font-size: 12px; margin-top: 8px;">⚠️ Low water! Please refill.</p>
                <?php endif; ?>
            </div>
            <div class="stat-card">
                <h3>Today's Feeds</h3>
                <div class="stat-value"><?php echo $today_feeds; ?></div>
                <div class="progress-bar"><div class="progress-fill" style="width: <?php echo min(($today_feeds / 4) * 100, 100); ?>%"></div></div>
            </div>
        </div>

        <!-- FEEDING ANALYTICS SECTION -->
        <?php if ($analytics && $analytics['total_feeds'] > 0): ?>
        <div class="card">
            <h2>📊 30-Day Feeding Analytics & Insights</h2>
            <div class="analytics-grid">
                <div class="analytics-card"><div class="analytics-number"><?php echo $analytics['total_feeds']; ?></div><div class="analytics-label">Total Feeds (30 days)</div></div>
                <div class="analytics-card"><div class="analytics-number"><?php echo round($analytics['total_food_dispensed']); ?>g</div><div class="analytics-label">Total Food Dispensed</div></div>
                <div class="analytics-card"><div class="analytics-number"><?php echo round($analytics['avg_portion']); ?>g</div><div class="analytics-label">Average Portion Size</div></div>
                <div class="analytics-card"><div class="analytics-number"><?php echo round($analytics['total_feeds'] / max($analytics['active_days'], 1), 1); ?></div><div class="analytics-label">Avg Feeds Per Day</div></div>
            </div>
            
            <div class="two-columns" style="margin-bottom: 0;">
                <div class="chart-container">
                    <h3 style="font-size: 14px; margin-bottom: 12px;">📈 Daily Feeding Trend (Last 7 Days)</h3>
                    <canvas id="dailyTrendChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3 style="font-size: 14px; margin-bottom: 12px;">🥩 Manual vs Scheduled Feeding</h3>
                    <canvas id="sourceRatioChart"></canvas>
                </div>
            </div>
            
            <div class="two-columns" style="margin-bottom: 0;">
                <div class="chart-container">
                    <h3 style="font-size: 14px; margin-bottom: 12px;">⭐ Peak Feeding Hours</h3>
                    <canvas id="hourlyChart"></canvas>
                </div>
                <div class="chart-container">
                    <h3 style="font-size: 14px; margin-bottom: 12px;">📅 Weekly Feeding Distribution</h3>
                    <canvas id="weeklyChart"></canvas>
                </div>
            </div>
            
            <div style="margin-top: 20px;">
                <h3 style="font-size: 16px; margin-bottom: 12px;">💡 Key Insights</h3>
                <ul class="insights-list">
                    <?php if (!empty($time_range_stats) && isset($time_range_stats[0])): ?>
                        <li><span>🌙 Most Active Time Range:</span> <strong class="insight-badge"><?php echo $time_range_stats[0]['time_range']; ?> (<?php echo $time_range_stats[0]['feed_count']; ?> feeds)</strong></li>
                    <?php endif; ?>
                    <?php if (!empty($hourly_distribution) && isset($hourly_distribution[0])): ?>
                        <?php $peak_hour = $hourly_distribution[0]['hour']; $ampm = ($peak_hour >= 12) ? 'PM' : 'AM'; $display_hour = ($peak_hour % 12 == 0) ? 12 : $peak_hour % 12; ?>
                        <li><span>⭐ Peak Feeding Hour:</span> <strong><?php echo $display_hour . ':00 ' . $ampm; ?> (<?php echo $hourly_distribution[0]['feed_count']; ?> feeds)</strong></li>
                    <?php endif; ?>
                    <li><span>📊 Daily Average:</span> <strong><?php echo round($analytics['total_feeds'] / max($analytics['active_days'], 1), 1); ?> feeds per active day</strong></li>
                    <?php if (!empty($source_ratio)): ?>
                        <?php $manual_count = 0; $scheduled_count = 0; foreach($source_ratio as $src) { if ($src['source'] == 'manual') $manual_count = $src['count']; else $scheduled_count = $src['count']; } $total = $manual_count + $scheduled_count; $manual_percent = $total > 0 ? round(($manual_count / $total) * 100) : 0; ?>
                        <li><span>🎮 Manual vs Automated:</span> <strong><?php echo $manual_percent; ?>% Manual / <?php echo 100 - $manual_percent; ?>% Scheduled</strong></li>
                    <?php endif; ?>
                    <li><span>🍖 Total Food Dispensed (30 days):</span> <strong><?php echo round($analytics['total_food_dispensed']); ?> grams (<?php echo round($analytics['total_food_dispensed'] / 1000, 1); ?> kg)</strong></li>
                </ul>
            </div>
        </div>
        <?php else: ?>
        <div class="card">
            <h2>📊 Feeding Analytics</h2>
            <div class="empty-state">Not enough feeding data yet.<br>Start using the feeder to see analytics and insights!</div>
        </div>
        <?php endif; ?>
        
        <!-- Two Columns -->
        <div class="two-columns">
            <div class="card">
                <h2>🍖 Manual Feeding</h2>
                <div class="fixed-portion-info">⚡ Fixed portion: <strong>50 grams</strong> per feeding (No weight sensor required)</div>
                <?php if (empty($pets)): ?>
                    <div class="empty-state">No pets added yet.<br><a href="pet_profile.php">Add a pet first</a></div>
                <?php else: ?>
                    <form method="POST">
                        <div style="margin-bottom: 16px;">
                            <label>Select Pet:</label>
                            <select name="pet_id" required>
                                <?php foreach($pets as $pet): ?>
                                    <option value="<?php echo $pet['id']; ?>"><?php echo htmlspecialchars($pet['pet_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" name="manual_feed" class="feed-btn">🍽️ Dispense Food (50g)</button>
                    </form>
                    <?php if ($feed_success === true): ?>
                        <p style="color: #28a745; margin-top: 12px; text-align: center;">✅ Food dispensed successfully!</p>
                    <?php elseif ($feed_success === false): ?>
                        <p style="color: #dc3545; margin-top: 12px; text-align: center;">❌ Failed to dispense. Check device.</p>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
            
            <div class="card">
                <h2>⏰ Upcoming Schedules</h2>
                <?php if (count($schedules) > 0): ?>
                    <?php foreach($schedules as $schedule): ?>
                        <div class="schedule-item">
                            <div>
                                <strong><?php echo htmlspecialchars($schedule['pet_name']); ?></strong><br>
                                <?php echo date('g:i A', strtotime($schedule['feed_time'])); ?> • Fixed: <?php echo $schedule['portion_size']; ?>g
                            </div>
                            <div class="schedule-time"><?php echo $schedule['feed_days']; ?></div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <div class="empty-state">No schedules set.<br><a href="schedule.php">Create a schedule</a></div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Feeding History -->
        <div class="card">
            <h2>📋 Recent Feeding History</h2>
            <?php if (count($feeding_history) > 0): ?>
                <table class="history-table">
                    <thead><tr><th>Pet</th><th>Portion</th><th>Time</th><th>Source</th><th>Status</th></tr></thead>
                    <tbody>
                        <?php foreach($feeding_history as $history): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($history['pet_name']); ?></td>
                            <td><?php echo $history['portion_size']; ?>g</td>
                            <td><?php echo date('M j, g:i A', strtotime($history['feed_time'])); ?></td>
                            <td><?php echo ucfirst($history['source']); ?></td>
                            <td class="<?php echo $history['status'] == 'success' ? 'success' : 'danger'; ?>"><?php echo $history['status'] == 'success' ? '✅ Success' : '❌ Failed'; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="empty-state">No feeding history yet.<br>Try manual feeding!</div>
            <?php endif; ?>
        </div>
    </div>
    
    <script>
        function toggleNotifications() {
            document.getElementById('notificationDropdown').classList.toggle('show');
        }
        window.onclick = function(event) {
            if (!event.target.closest('.notification-icon')) {
                document.getElementById('notificationDropdown').classList.remove('show');
            }
        }
        
        <?php if ($analytics && $analytics['total_feeds'] > 0): ?>
        // Daily Trend Chart
        new Chart(document.getElementById('dailyTrendChart'), {
            type: 'line',
            data: {
                labels: [<?php foreach($feeding_trends as $trend) echo "'" . date('M d', strtotime($trend['date'])) . "',"; ?>],
                datasets: [{ label: 'Number of Feeds', data: [<?php foreach($feeding_trends as $trend) echo $trend['feed_count'] . ","; ?>], borderColor: '#667eea', backgroundColor: 'rgba(102,126,234,0.1)', tension: 0.4, fill: true }]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
        });
        
        // Source Ratio Chart
        new Chart(document.getElementById('sourceRatioChart'), {
            type: 'doughnut',
            data: {
                labels: ['Manual Feeding', 'Scheduled Feeding'],
                datasets: [{ data: [<?php echo $manual_count ?? 0; ?>, <?php echo $scheduled_count ?? 0; ?>], backgroundColor: ['#17a2b8', '#28a745'], borderWidth: 0 }]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
        });
        
        // Hourly Chart
        new Chart(document.getElementById('hourlyChart'), {
            type: 'bar',
            data: {
                labels: [<?php foreach($hourly_distribution as $hour) { $h = $hour['hour']; $ampm = ($h >= 12) ? 'PM' : 'AM'; $display = ($h % 12 == 0) ? 12 : $h % 12; echo "'" . $display . ':00 ' . $ampm . "',"; } ?>],
                datasets: [{ label: 'Feeds', data: [<?php foreach($hourly_distribution as $hour) echo $hour['feed_count'] . ","; ?>], backgroundColor: '#764ba2', borderRadius: 8 }]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
        });
        
        // Weekly Chart
        new Chart(document.getElementById('weeklyChart'), {
            type: 'bar',
            data: {
                labels: [<?php foreach($weekly_distribution as $day) echo "'" . $day['day_name'] . "',"; ?>],
                datasets: [{ label: 'Feeds', data: [<?php foreach($weekly_distribution as $day) echo $day['feed_count'] . ","; ?>], backgroundColor: '#667eea', borderRadius: 8 }]
            },
            options: { responsive: true, maintainAspectRatio: true, plugins: { legend: { position: 'bottom' } } }
        });
        <?php endif; ?>
    </script>
</body>
</html>