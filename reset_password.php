<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';
$token = isset($_GET['token']) ? $_GET['token'] : '';

// Verify token
if (empty($token)) {
    $error = "Invalid password reset link.";
} else {
    // Check if token exists and is valid
    $stmt = $pdo->prepare("SELECT id, username, full_name, email, reset_token, reset_expires FROM feeder_users WHERE reset_token = ?");
    $stmt->execute([$token]);
    $user = $stmt->fetch();
    
    if (!$user) {
        $error = "Invalid or expired reset token.";
    } elseif (strtotime($user['reset_expires']) < time()) {
        $error = "This reset link has expired. Please request a new one.";
        // Clear expired token
        $stmt = $pdo->prepare("UPDATE feeder_users SET reset_token = NULL, reset_expires = NULL WHERE id = ?");
        $stmt->execute([$user['id']]);
    }
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$error) {
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    
    // Validate password strength
    if (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long.";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match.";
    } elseif (!preg_match('/[A-Z]/', $password)) {
        $error = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $password)) {
        $error = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $password)) {
        $error = "Password must contain at least one number.";
    } else {
        // Update password
        $password_hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE feeder_users SET password_hash = ?, reset_token = NULL, reset_expires = NULL WHERE id = ?");
        
        if ($stmt->execute([$password_hash, $user['id']])) {
            $success = "Your password has been successfully reset!";
            // Clear the token from URL after 3 seconds redirect
            echo "<script>
                    setTimeout(function() {
                        window.location.href = 'login.php';
                    }, 3000);
                  </script>";
        } else {
            $error = "Failed to reset password. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Smart Pet Feeder</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            max-width: 420px;
            width: 100%;
        }
        
        .card {
            background: white;
            border-radius: 20px;
            padding: 40px;
            box-shadow: 0 20px 35px rgba(0,0,0,0.1);
        }
        
        .logo {
            text-align: center;
            margin-bottom: 32px;
        }
        
        .logo h1 {
            font-size: 32px;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .logo p {
            color: #6c757d;
            margin-top: 8px;
        }
        
        .form-group {
            margin-bottom: 24px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #495057;
        }
        
        input {
            width: 100%;
            padding: 12px 16px;
            border: 2px solid #e9ecef;
            border-radius: 12px;
            font-size: 15px;
            transition: all 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102,126,234,0.1);
        }
        
        button {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
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
        
        .back-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e9ecef;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .password-strength {
            margin-top: 8px;
            font-size: 12px;
        }
        
        .strength-weak { color: #dc3545; }
        .strength-medium { color: #ffc107; }
        .strength-strong { color: #28a745; }
        
        .info-text {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 20px;
        }
    </style>
    <script>
        function checkPasswordStrength(password) {
            let strength = 0;
            if (password.length >= 8) strength++;
            if (password.match(/[A-Z]/)) strength++;
            if (password.match(/[a-z]/)) strength++;
            if (password.match(/[0-9]/)) strength++;
            if (password.match(/[^A-Za-z0-9]/)) strength++;
            
            let strengthText = '';
            let strengthClass = '';
            
            if (strength <= 2) {
                strengthText = 'Weak';
                strengthClass = 'strength-weak';
            } else if (strength <= 4) {
                strengthText = 'Medium';
                strengthClass = 'strength-medium';
            } else {
                strengthText = 'Strong';
                strengthClass = 'strength-strong';
            }
            
            document.getElementById('strengthText').textContent = strengthText;
            document.getElementById('strengthText').className = strengthClass;
        }
        
        function validateForm() {
            let password = document.getElementById('password').value;
            let confirm = document.getElementById('confirm_password').value;
            
            if (password !== confirm) {
                document.getElementById('confirmError').textContent = 'Passwords do not match';
                return false;
            } else {
                document.getElementById('confirmError').textContent = '';
                return true;
            }
        }
    </script>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <h1>🐾 Smart PetFeeder</h1>
                <p>Create new password</p>
            </div>
            
            <?php if ($error): ?>
                <div class="alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert-success">✓ <?php echo $success; ?></div>
                <div class="back-link">
                    <a href="login.php">Click here to login</a>
                </div>
            <?php endif; ?>
            
            <?php if (!$error && !$success && isset($user)): ?>
                <div class="info-text">
                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong>, please enter your new password.
                </div>
                
                <form method="POST" onsubmit="return validateForm()">
                    <div class="form-group">
                        <label>New Password</label>
                        <input type="password" name="password" id="password" required 
                               onkeyup="checkPasswordStrength(this.value)">
                        <div class="password-strength">
                            Strength: <span id="strengthText"></span>
                        </div>
                        <small>Password must be at least 8 characters with uppercase, lowercase, and number</small>
                    </div>
                    
                    <div class="form-group">
                        <label>Confirm New Password</label>
                        <input type="password" name="confirm_password" id="confirm_password" required 
                               onkeyup="validateForm()">
                        <div class="password-strength">
                            <span id="confirmError" style="color: #dc3545;"></span>
                        </div>
                    </div>
                    
                    <button type="submit">Reset Password</button>
                </form>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>