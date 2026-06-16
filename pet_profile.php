<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('login.php');
}

$user_id = $_SESSION['user_id'];
$dark_mode = getDarkMode($user_id);
$pets = getUserPets($user_id);

// Add new pet
if (isset($_POST['add_pet'])) {
    $pet_name = sanitize($_POST['pet_name']);
    $pet_type = sanitize($_POST['pet_type']);
    $breed = sanitize($_POST['breed']);
    $age = (int)$_POST['age'];
    $weight = (float)$_POST['weight'];
    
    $stmt = $pdo->prepare("INSERT INTO feeder_pets (user_id, pet_name, pet_type, breed, age, weight) VALUES (?, ?, ?, ?, ?, ?)");
    if ($stmt->execute([$user_id, $pet_name, $pet_type, $breed, $age, $weight])) {
        addNotification($user_id, '🐾 New Pet Added', "{$pet_name} has been added to your profile.", 'system');
        redirect('pet_profile.php');
    }
}

// Delete pet
if (isset($_GET['delete'])) {
    $pet_id = $_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM feeder_pets WHERE id = ? AND user_id = ?");
    $stmt->execute([$pet_id, $user_id]);
    addNotification($user_id, '🐾 Pet Removed', 'A pet has been removed from your profile.', 'system');
    redirect('pet_profile.php');
}

// Toggle dark mode
if (isset($_POST['toggle_dark_mode'])) {
    $new_mode = $dark_mode ? 0 : 1;
    $stmt = $pdo->prepare("UPDATE feeder_users SET dark_mode = ? WHERE id = ?");
    $stmt->execute([$new_mode, $user_id]);
    redirect('pet_profile.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Pets - Smart Pet Feeder</title>
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
        
        .pets-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }
        
        .pet-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
            transition: transform 0.3s;
        }
        
        .pet-card:hover {
            transform: translateY(-5px);
        }
        
        .pet-avatar {
            font-size: 64px;
            text-align: center;
            margin-bottom: 16px;
        }
        
        .pet-name {
            font-size: 24px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 16px;
        }
        
        .pet-details {
            border-top: 1px solid var(--border);
            padding-top: 16px;
        }
        
        .pet-detail-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 8px;
        }
        
        .delete-btn {
            background: #dc3545;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            cursor: pointer;
            width: 100%;
            margin-top: 16px;
        }
        
        .delete-btn:hover {
            background: #c82333;
        }
        
        .add-pet-card {
            background: var(--card-bg);
            border-radius: 20px;
            padding: 24px;
            border: 1px solid var(--border);
        }
        
        .add-pet-card h2 {
            margin-bottom: 20px;
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
            padding: 60px;
            background: var(--card-bg);
            border-radius: 20px;
            border: 1px solid var(--border);
        }
        
        @media (max-width: 768px) {
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
            <h1>🐕 My Pets</h1>
        </div>
        
        <?php if (count($pets) > 0): ?>
            <div class="pets-grid">
                <?php foreach($pets as $pet): ?>
                    <div class="pet-card">
                        <div class="pet-avatar">
                            <?php echo $pet['pet_type'] == 'Dog' ? '🐕' : ($pet['pet_type'] == 'Cat' ? '🐱' : '🐾'); ?>
                        </div>
                        <div class="pet-name"><?php echo htmlspecialchars($pet['pet_name']); ?></div>
                        <div class="pet-details">
                            <div class="pet-detail-item">
                                <span>Type:</span>
                                <strong><?php echo htmlspecialchars($pet['pet_type']); ?></strong>
                            </div>
                            <div class="pet-detail-item">
                                <span>Breed:</span>
                                <strong><?php echo htmlspecialchars($pet['breed'] ?: 'Not specified'); ?></strong>
                            </div>
                            <div class="pet-detail-item">
                                <span>Age:</span>
                                <strong><?php echo $pet['age'] ? $pet['age'] . ' years' : 'Not specified'; ?></strong>
                            </div>
                            <div class="pet-detail-item">
                                <span>Weight:</span>
                                <strong><?php echo $pet['weight'] ? $pet['weight'] . ' kg' : 'Not specified'; ?></strong>
                            </div>
                        </div>
                        <a href="?delete=<?php echo $pet['id']; ?>" class="delete-btn" onclick="return confirm('Remove this pet?')">Remove Pet</a>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="empty-state">
                <div style="font-size: 64px;">🐾</div>
                <h3>No pets yet</h3>
                <p style="margin-top: 8px; color: var(--text-secondary);">Add your first pet to get started!</p>
            </div>
        <?php endif; ?>
        
        <div class="add-pet-card">
            <h2>➕ Add New Pet</h2>
            <form method="POST">
                <div class="form-group">
                    <label>Pet Name *</label>
                    <input type="text" name="pet_name" required>
                </div>
                <div class="form-group">
                    <label>Pet Type *</label>
                    <select name="pet_type" required>
                        <option value="Dog">🐕 Dog</option>
                        <option value="Cat">🐱 Cat</option>
                        <option value="Bird">🐦 Bird</option>
                        <option value="Rabbit">🐰 Rabbit</option>
                        <option value="Other">🐾 Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Breed</label>
                    <input type="text" name="breed" placeholder="e.g., Golden Retriever">
                </div>
                <div class="form-group">
                    <label>Age (years)</label>
                    <input type="number" name="age" step="0.5" placeholder="e.g., 3">
                </div>
                <div class="form-group">
                    <label>Weight (kg)</label>
                    <input type="number" name="weight" step="0.1" placeholder="e.g., 25.5">
                </div>
                <button type="submit" name="add_pet" class="btn-primary">Add Pet</button>
            </form>
        </div>
    </div>
</body>
</html>