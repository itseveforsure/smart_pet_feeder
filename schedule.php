<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$dark_mode = getDarkMode($user_id);
$pets = getUserPets($user_id);
$schedules = getAllSchedules($user_id);

// Add schedule (FIXED PORTION - NO SENSOR)
if (isset($_POST['add_schedule']) && !empty($pets)) {
    $pet_id = $_POST['pet_id'];
    $portion_size = 50; // FIXED portion size - no weight sensor needed
    $feed_time = $_POST['feed_time'];
    $feed_days = $_POST['feed_days'];
    
    $stmt = $pdo->prepare("INSERT INTO feeder_schedule (user_id, pet_id, portion_size, feed_time, feed_days) VALUES (?, ?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $pet_id, $portion_size, $feed_time, $feed_days])) {
        addNotification($user_id, '⏰ Schedule Created', "New feeding schedule added for {$feed_time} (50g fixed portion).", 'success');
        redirect('schedule.php');
    }
}

// Toggle schedule active status
if (isset($_GET['toggle'])) {
    $schedule_id = $_GET['toggle'];
    $stmt = $pdo->prepare("UPDATE feeder_schedule SET is_active = NOT is_active WHERE id = ? AND user_id = ?");
    $stmt->execute([$schedule_id, $user_id]);
    redirect('schedule.php');
}

// Delete schedule
if (isset($_GET['delete'])) {
    $schedule_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM feeder_schedule WHERE id = ? AND user_id = ?");
    $stmt->execute([$schedule_id, $user_id]);
    addNotification($user_id, '⏰ Schedule Removed', 'A feeding schedule has been deleted.', 'system');
    redirect('schedule.php');
}

// Toggle dark mode
if (isset($_POST['toggle_dark_mode'])) {
    $new_mode = $dark_mode ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE feeder_users SET dark_mode = ? WHERE id = ?");
    $stmt->execute([$new_mode, $user_id]);
    redirect('schedule.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Feeding Schedule - Smart Pet Feeder</title>
    <style>
        :root {
            --bg-primary: <?php echo $dark_mode ? '#1a1a2e' : '#f8f9fa'; ?>;
            --bg-secondary: <?php echo $dark_mode ? '#16213e' : '#ffffff'; ?>;
            --text-primary: <?php echo $dark_mode ? '#ffffff' : '#212529'; ?>;
            --text-secondary: <?php echo $dark_mode ? '#adb5bd' : '#6c757d'; ?>;
            --border: <?php echo $dark_mode ? '#2d3561' : '#e9ecef'; ?>;
            --card-bg: <?php echo $dark_mode ? '#1e2a47' : '#ffffff'; ?>;
        }
        
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: var(--bg-primary);
            color: var(--text-primary);
            transition: all 0.3s;
        }
        
        .navbar {
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border);
            padding: 0 32px;
            position: sticky;
            top: 0;
            z-index: 100;
        }
        
        .nav-container {
            max-width: 1200px;
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
        }
        
        .nav-links a {
            color: var(--text-secondary);
            text-decoration: none;
            font-weight: 500;
        }
        
        .nav-links a:hover { color: #667eea; }
        
        .dark-mode-btn {
            background: none;
            border: 1px solid var(--border);
            padding: 8px 16px;
            border-radius: 20px;
            cursor: pointer;
            color: var(--text-primary);
        }
        
        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px 32px;
        }
        
        .page-header {
            margin-bottom: 32px;
        }
        
        .page-header h1 {
            font-size: 32px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .two-columns {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 32px;
        }
        
        .card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
        }
        
        .card h2 {
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }
        
        .schedule-list {
            max-height: 400px;
            overflow-y: auto;
        }
        
        .schedule-item {
            background: var(--bg-primary);
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
        }
        
        .schedule-info {
            flex: 1;
        }
        
        .schedule-time {
            font-size: 18px;
            font-weight: 700;
            color: #667eea;
        }
        
        .schedule-details {
            font-size: 14px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        .schedule-actions {
            display: flex;
            gap: 8px;
        }
        
        .btn-toggle {
            background: #28a745;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .btn-toggle.inactive {
            background: #6c757d;
        }
        
        .btn-delete {
            background: #dc3545;
            color: white;
            border: none;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
        }
        
        .form-group {
            margin-bottom: 16px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input, select {
            width: 100%;
            padding: 10px 12px;
            border: 1px solid var(--border);
            border-radius: 8px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        
        input[readonly] {
            background: var(--bg-primary);
            opacity: 0.7;
            cursor: not-allowed;
        }
        
        .fixed-portion-info {
            background: var(--bg-primary);
            padding: 10px 12px;
            border-radius: 8px;
            font-size: 13px;
            color: var(--text-secondary);
            margin-bottom: 16px;
            text-align: center;
            border: 1px solid var(--border);
        }
        
        .btn-primary {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            width: 100%;
            font-weight: 600;
        }
        
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--text-secondary);
        }
        
        @media (max-width: 768px) {
            .two-columns {
                grid-template-columns: 1fr;
            }
            .nav-container {
                flex-direction: column;
                gap: 12px;
            }
            .container {
                padding: 20px;
            }
        }
    </style>
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
        <div class="page-header">
            <h1>⏰ Feeding Schedule</h1>
        </div>
        
        <div class="two-columns">
            <!-- Schedule List -->
            <div class="card">
                <h2>📋 Your Schedules</h2>
                <div class="schedule-list">
                    <?php if (count($schedules) > 0): ?>
                        <?php foreach($schedules as $schedule): ?>
                            <div class="schedule-item">
                                <div class="schedule-info">
                                    <div class="schedule-time">🕐 <?php echo date('g:i A', strtotime($schedule['feed_time'])); ?></div>
                                    <div class="schedule-details">
                                        <?php echo htmlspecialchars($schedule['pet_name']); ?> • <strong>Fixed: <?php echo $schedule['portion_size']; ?>g</strong> • <?php echo $schedule['feed_days']; ?>
                                    </div>
                                </div>
                                <div class="schedule-actions">
                                    <a href="?toggle=<?php echo $schedule['id']; ?>" class="btn-toggle <?php echo $schedule['is_active'] ? '' : 'inactive'; ?>">
                                        <?php echo $schedule['is_active'] ? 'Active' : 'Inactive'; ?>
                                    </a>
                                    <a href="?delete=<?php echo $schedule['id']; ?>" class="btn-delete" onclick="return confirm('Delete this schedule?')">Delete</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="empty-state">
                            No schedules yet. Create one below!
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            
            <!-- Add Schedule Form -->
            <div class="card">
                <h2>➕ Create New Schedule</h2>
                <?php if (empty($pets)): ?>
                    <div class="empty-state">
                        <p>No pets found. <a href="pet_profile.php">Add a pet first</a></p>
                    </div>
                <?php else: ?>
                    <form method="POST">
                        <div class="form-group">
                            <label>Select Pet</label>
                            <select name="pet_id" required>
                                <?php foreach($pets as $pet): ?>
                                    <option value="<?php echo $pet['id']; ?>"><?php echo htmlspecialchars($pet['pet_name']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <!-- FIXED PORTION - NO SENSOR -->
                        <div class="fixed-portion-info">
                            ⚡ <strong>Fixed Portion: 50 grams</strong> per feeding<br>
                            <small>(Weight sensor not available - fixed amount)</small>
                        </div>
                        <input type="hidden" name="portion_size" value="50">
                        
                        <div class="form-group">
                            <label>Feed Time</label>
                            <input type="time" name="feed_time" value="08:00" required>
                        </div>
                        <div class="form-group">
                            <label>Feed Days</label>
                            <select name="feed_days" required>
                                <option value="Daily">Every Day</option>
                                <option value="Weekdays">Monday - Friday</option>
                                <option value="Weekends">Saturday - Sunday</option>
                                <option value="Monday,Wednesday,Friday">Mon, Wed, Fri</option>
                                <option value="Tuesday,Thursday,Saturday">Tue, Thu, Sat</option>
                            </select>
                        </div>
                        <button type="submit" name="add_schedule" class="btn-primary">Create Schedule (50g fixed)</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
</body>
</html>