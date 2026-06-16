<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $username = sanitize($_POST['username']);
    $password = $_POST['password'];
    $remember = isset($_POST['remember']) ? true : false;
    
    if (empty($username) || empty($password)) {
        $error = "Please enter both username/email and password.";
    } else {
        // Prepare statement to prevent SQL injection
        $stmt = $pdo->prepare("SELECT * FROM feeder_users WHERE username = ? OR email = ?");
        $stmt->execute([$username, $username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Set session variables
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['full_name'] = $user['full_name'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['login_time'] = time();
            
            // Remember me functionality (30 days)
            if ($remember) {
                $token = bin2hex(random_bytes(32));
                $expires = date('Y-m-d H:i:s', strtotime('+30 days'));
                
                $stmt = $pdo->prepare("UPDATE feeder_users SET remember_token = ?, remember_expires = ? WHERE id = ?");
                $stmt->execute([$token, $expires, $user['id']]);
                
                setcookie('remember_token', $token, time() + (86400 * 30), "/", "", false, true);
            }
            
            // Update last login
            $stmt = $pdo->prepare("UPDATE feeder_users SET last_login = NOW() WHERE id = ?");
            $stmt->execute([$user['id']]);
            
            // Add welcome back notification
            addNotification($user['id'], 'Welcome Back!', "Welcome back to Smart Pet Feeder, {$user['full_name']}!", 'success');
            
            redirect('dashboard.php');
        } else {
            $error = "Invalid username/email or password.";
        }
    }
}

// Handle forgot password request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['forgot_password'])) {
    $email = sanitize($_POST['email']);
    
    if (empty($email)) {
        $error = "Please enter your email address.";
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = "Please enter a valid email address.";
    } else {
        // Check if email exists
        $stmt = $pdo->prepare("SELECT id, username, full_name, email FROM feeder_users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        
        // Always show success message even if email doesn't exist (security)
        $success = "If an account exists with that email, you will receive password reset instructions.";
        
        if ($user) {
            // Generate reset token
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            
            $stmt = $pdo->prepare("UPDATE feeder_users SET reset_token = ?, reset_expires = ? WHERE id = ?");
            if ($stmt->execute([$token, $expires, $user['id']])) {
                // Send reset email
                $resetLink = SITE_URL . '/reset_password.php?token=' . $token;
                $subject = "Password Reset Request - Smart Pet Feeder";
                $message = "
                <html>
                <body>
                    <h2>Password Reset Request</h2>
                    <p>Hello {$user['full_name']},</p>
                    <p>Click the link below to reset your password:</p>
                    <p><a href='{$resetLink}'>$resetLink</a></p>
                    <p>This link expires in 24 hours.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                </body>
                </html>
                ";
                
                $headers = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type:text/html;charset=UTF-8\r\n";
                $headers .= "From: Smart Pet Feeder <" . SMTP_FROM . ">\r\n";
                
                @mail($email, $subject, $message, $headers);
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Smart Pet Feeder</title>
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
            max-width: 450px;
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
            background-clip: text;
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
        
        input[type="text"],
        input[type="email"],
        input[type="password"] {
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
        
        .checkbox-group {
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        
        .checkbox-group label {
            margin-bottom: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            cursor: pointer;
        }
        
        .checkbox-group input[type="checkbox"] {
            width: auto;
            width: 18px;
            height: 18px;
            cursor: pointer;
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
            transition: transform 0.3s, box-shadow 0.3s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102,126,234,0.4);
        }
        
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #dc3545;
        }
        
        .alert-success {
            background: #d4edda;
            color: #155724;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            border-left: 4px solid #28a745;
        }
        
        .forgot-password {
            text-align: right;
            margin-top: 8px;
        }
        
        .forgot-password a {
            color: #667eea;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            cursor: pointer;
        }
        
        .forgot-password a:hover {
            text-decoration: underline;
        }
        
        .register-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid #e9ecef;
        }
        
        .register-link a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        
        .register-link a:hover {
            text-decoration: underline;
        }
        
        .demo-info {
            background: linear-gradient(135deg, #f8f9fa, #e9ecef);
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-size: 13px;
            color: #495057;
            text-align: center;
            border: 1px solid #dee2e6;
        }
        
        .back-link {
            text-align: center;
            margin-top: 20px;
        }
        
        .back-link a {
            color: #667eea;
            text-decoration: none;
            font-size: 14px;
            cursor: pointer;
        }
        
        .hidden {
            display: none;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <h1>🐾 Smart PetFeeder</h1>
                <p>Welcome back!</p>
            </div>
            
            <!-- Login Form -->
            <div id="loginForm">
                <div class="demo-info">
                    🔐 Demo Credentials:<br>
                    Username: petlover | Email: petlover@example.com<br>
                    Password: password123
                </div>
                
                <?php if ($error): ?>
                    <div class="alert-error">
                        <strong>❌ Error!</strong><br>
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>
                
                <?php if ($success): ?>
                    <div class="alert-success">
                        <strong>✓ Success!</strong><br>
                        <?php echo htmlspecialchars($success); ?>
                    </div>
                <?php endif; ?>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Username or Email</label>
                        <input type="text" name="username" required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label>Password</label>
                        <input type="password" name="password" required>
                        <div class="forgot-password">
                            <a onclick="showForgotForm()">Forgot Password?</a>
                        </div>
                    </div>
                    
                    <div class="form-group checkbox-group">
                        <label>
                            <input type="checkbox" name="remember"> 
                            Remember me for 30 days
                        </label>
                    </div>
                    
                    <button type="submit" name="login">Login</button>
                </form>
                
                <div class="register-link">
                    <a href="register.php">Don't have an account? Register</a>
                </div>
            </div>
            
            <!-- Forgot Password Form (hidden by default) -->
            <div id="forgotForm" style="display: none;">
                <div class="logo">
                    <p>Reset your password</p>
                </div>
                
                <form method="POST" action="">
                    <div class="form-group">
                        <label>Email Address</label>
                        <input type="email" name="email" required placeholder="Enter your registered email">
                        <small style="color: #6c757d; display: block; margin-top: 5px;">
                            We'll send you a link to reset your password.
                        </small>
                    </div>
                    
                    <button type="submit" name="forgot_password">Send Reset Link</button>
                    
                    <div class="back-link">
                        <a onclick="showLoginForm()">← Back to Login</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script>
        function showForgotForm() {
            document.getElementById('loginForm').style.display = 'none';
            document.getElementById('forgotForm').style.display = 'block';
        }
        
        function showLoginForm() {
            document.getElementById('loginForm').style.display = 'block';
            document.getElementById('forgotForm').style.display = 'none';
        }
        
        // Auto-hide alerts after 5 seconds
        setTimeout(() => {
            const alerts = document.querySelectorAll('.alert-error, .alert-success');
            alerts.forEach(alert => {
                if (alert) {
                    alert.style.transition = 'opacity 0.5s';
                    alert.style.opacity = '0';
                    setTimeout(() => {
                        if (alert && alert.remove) alert.remove();
                    }, 500);
                }
            });
        }, 5000);
    </script>
</body>
</html>