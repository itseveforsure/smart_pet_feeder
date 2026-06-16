<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$dark_mode = getDarkMode($user_id);
$user = getUserDetails($user_id);
$pets = getUserPets($user_id);
$total_feeds = $pdo->prepare("SELECT COUNT(*) FROM feeder_history WHERE user_id = ?");
$total_feeds->execute([$user_id]);
$total_feeds = $total_feeds->fetchColumn();

$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        
        $stmt = $pdo->prepare("UPDATE feeder_users SET full_name = ?, phone = ? WHERE id = ?");
        if ($stmt->execute([$full_name, $phone, $user_id])) {
            $_SESSION['full_name'] = $full_name;
            $success = "Profile updated successfully";
            $user = getUserDetails($user_id);
        } else {
            $error = "Failed to update profile";
        }
    }
    
    if (isset($_POST['change_password'])) {
        $current = $_POST['current_password'];
        $new = $_POST['new_password'];
        $confirm = $_POST['confirm_password'];
        
        if (password_verify($current, $user['password_hash'])) {
            if (strlen($new) >= 6) {
                if ($new === $confirm) {
                    $new_hash = password_hash($new, PASSWORD_DEFAULT);
                    $stmt = $pdo->prepare("UPDATE feeder_users SET password_hash = ? WHERE id = ?");
                    if ($stmt->execute([$new_hash, $user_id])) {
                        $success = "Password changed successfully";
                    } else {
                        $error = "Failed to change password";
                    }
                } else {
                    $error = "New passwords do not match";
                }
            } else {
                $error = "Password must be at least 6 characters";
            }
        } else {
            $error = "Current password is incorrect";
        }
    }
    
    if (isset($_POST['toggle_dark_mode'])) {
        $new_mode = $dark_mode ? 0 : 1;
        $stmt = $pdo->prepare("UPDATE feeder_users SET dark_mode = ? WHERE id = ?");
        $stmt->execute([$new_mode, $user_id]);
        redirect('profile.php');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Smart Pet Feeder</title>
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
            max-width: 1000px;
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
            max-width: 1000px;
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
        
        .profile-section {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 28px;
            margin-bottom: 24px;
            border: 1px solid var(--border);
        }
        
        .profile-section h2 {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--border);
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
        }
        
        input {
            width: 100%;
            padding: 12px 16px;
            border: 1px solid var(--border);
            border-radius: 10px;
            background: var(--bg-primary);
            color: var(--text-primary);
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        input[readonly] {
            background: var(--bg-primary);
            opacity: 0.7;
        }
        
        button {
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-top: 20px;
        }
        
        .stat-item {
            text-align: center;
            padding: 16px;
            background: var(--bg-primary);
            border-radius: 12px;
        }
        
        .stat-number {
            font-size: 28px;
            font-weight: 700;
            color: #667eea;
        }
        
        .stat-label {
            font-size: 12px;
            color: var(--text-secondary);
            margin-top: 4px;
        }
        
        @media (max-width: 768px) {
            .nav-container {
                flex-direction: column;
                gap: 12px;
            }
            .container {
                padding: 20px;
            }
            .stats-grid {
                grid-template-columns: 1fr;
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
            <h1>👤 My Profile</h1>
        </div>
        
        <?php if ($success): ?>
            <div class="alert-success">✅ <?php echo $success; ?></div>
        <?php endif; ?>
        
        <?php if ($error): ?>
            <div class="alert-error">❌ <?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="profile-section">
            <h2>Account Information</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?php echo htmlspecialchars($user['email']); ?>" readonly>
                </div>
                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                <button type="submit" name="update_profile">Update Profile</button>
            </form>
        </div>
        
        <div class="profile-section">
            <h2>Change Password</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Current Password</label>
                    <input type="password" name="current_password" required>
                </div>
                <div class="form-group">
                    <label>New Password</label>
                    <input type="password" name="new_password" required>
                </div>
                <div class="form-group">
                    <label>Confirm New Password</label>
                    <input type="password" name="confirm_password" required>
                </div>
                <button type="submit" name="change_password">Change Password</button>
            </form>
        </div>
        
        <div class="profile-section">
            <h2>Statistics</h2>
            <div class="stats-grid">
                <div class="stat-item">
                    <div class="stat-number"><?php echo count($pets); ?></div>
                    <div class="stat-label">Pets</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo $total_feeds; ?></div>
                    <div class="stat-label">Total Feeds</div>
                </div>
                <div class="stat-item">
                    <div class="stat-number"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></div>
                    <div class="stat-label">Member Since</div>
                </div>
            </div>
        </div>
    </div>
</body>
</html>