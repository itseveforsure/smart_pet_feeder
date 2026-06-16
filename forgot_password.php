<?php
// Add at the beginning of forgot_password.php
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
$requests = isset($_SESSION['reset_requests'][$ip]) ? $_SESSION['reset_requests'][$ip] : 0;
$last_request = isset($_SESSION['reset_last_request'][$ip]) ? $_SESSION['reset_last_request'][$ip] : 0;

if ($requests >= 3 && (time() - $last_request) < 3600) {
    $error = "Too many password reset requests. Please try again later.";
    // Don't process further
}

error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config.php';

if (isLoggedIn()) {
    redirect('dashboard.php');
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = sanitize($_POST['email']);
    
    // Check if email exists
    $stmt = $pdo->prepare("SELECT id, username, full_name, email FROM feeder_users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    if ($user) {
        // Generate reset token
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+' . RESET_TOKEN_EXPIRY . ' hours'));
        
        // Store token in database (you need to add this column)
        $stmt = $pdo->prepare("UPDATE feeder_users SET reset_token = ?, reset_expires = ? WHERE id = ?");
        $stmt->execute([$token, $expires, $user['id']]);
        
        // Send reset email
        $resetLink = SITE_URL . '/reset_password.php?token=' . $token;
        
        $subject = "Password Reset Request - Smart Pet Feeder";
        $message = "
        <html>
        <head>
            <style>
                body { font-family: Arial, sans-serif; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f8f9fa; }
                .button { display: inline-block; padding: 12px 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; font-size: 12px; color: #6c757d; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h2>🐾 Smart Pet Feeder</h2>
                    <p>Password Reset Request</p>
                </div>
                <div class='content'>
                    <p>Hello <strong>{$user['full_name']}</strong>,</p>
                    <p>We received a request to reset your password. Click the button below to create a new password:</p>
                    <p style='text-align: center;'>
                        <a href='{$resetLink}' class='button'>Reset Password</a>
                    </p>
                    <p>Or copy and paste this link into your browser:</p>
                    <p><code>{$resetLink}</code></p>
                    <p>This link will expire in " . RESET_TOKEN_EXPIRY . " hours.</p>
                    <p>If you didn't request this, please ignore this email.</p>
                </div>
                <div class='footer'>
                    <p>&copy; " . date('Y') . " Smart Pet Feeder. All rights reserved.</p>
                </div>
            </div>
        </body>
        </html>
        ";
        
        $headers = "MIME-Version: 1.0\r\n";
        $headers .= "Content-type: text/html; charset=UTF-8\r\n";
        $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">\r\n";
        
        // Using mail() function (for production, consider using PHPMailer or SwiftMailer)
        if (mail($user['email'], $subject, $message, $headers)) {
            $success = "Password reset instructions have been sent to your email address.";
        } else {
            // Log error but don't show to user for security
            error_log("Failed to send password reset email to: " . $user['email']);
            $success = "If your email exists in our system, you will receive reset instructions.";
        }
    } else {
        // Don't reveal if email exists or not for security
        $success = "If your email exists in our system, you will receive reset instructions.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Smart Pet Feeder</title>
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
        
        .info-text {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="logo">
                <h1>🐾 Smart PetFeeder</h1>
                <p>Reset your password</p>
            </div>
            
            <div class="info-text">
                Enter your email address and we'll send you a link to reset your password.
            </div>
            
            <?php if ($error): ?>
                <div class="alert-error">❌ <?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert-success">✓ <?php echo $success; ?></div>
            <?php endif; ?>
            
            <?php if (!$success): ?>
            <form method="POST">
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" required autofocus placeholder="your@email.com">
                </div>
                <button type="submit">Send Reset Link</button>
            </form>
            <?php endif; ?>
            
            <div class="back-link">
                <a href="login.php">← Back to Login</a>
            </div>
        </div>
    </div>
</body>
</html>