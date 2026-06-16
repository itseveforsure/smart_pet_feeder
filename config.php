<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'smart_pet_feeder';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// ========== SITE CONFIGURATION ==========
// Site URL (update with your actual domain)
define('SITE_URL', 'http://localhost'); // Change to your actual URL

// Password reset token expiry (hours)
define('RESET_TOKEN_EXPIRY', 24);

// Rate limiting settings
define('MAX_RESET_REQUESTS_PER_HOUR', 3);
define('RESET_REQUEST_WINDOW', 3600); // 1 hour in seconds

// ========== EMAIL CONFIGURATION ==========
// Email sending method: 'mail' or 'smtp'
define('EMAIL_METHOD', 'smtp'); // Change to 'smtp' for production

// SMTP Configuration (Update these with your credentials)
define('SMTP_HOST', 'smtp.gmail.com'); // or your SMTP server
define('SMTP_PORT', 587);
define('SMTP_USER', 'your-email@gmail.com'); // YOUR GMAIL ADDRESS
define('SMTP_PASS', 'your-app-password'); // YOUR GMAIL APP PASSWORD
define('SMTP_FROM', 'your-email@gmail.com'); // Same as SMTP_USER
define('SMTP_FROM_NAME', 'Smart Pet Feeder');

// ========== HELPER FUNCTIONS ==========

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if admin is logged in
function isAdminLoggedIn() {
    return isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

// Sanitize input
function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

// Get user details
function getUserDetails($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM feeder_users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Get user by email
function getUserByEmail($email) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM feeder_users WHERE email = ?");
    $stmt->execute([$email]);
    return $stmt->fetch();
}

// Get user by reset token
function getUserByResetToken($token) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM feeder_users WHERE reset_token = ? AND reset_expires > NOW()");
    $stmt->execute([$token]);
    return $stmt->fetch();
}

// Generate password reset token
function generateResetToken($user_id) {
    global $pdo;
    $token = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', strtotime('+' . RESET_TOKEN_EXPIRY . ' hours'));
    
    $stmt = $pdo->prepare("UPDATE feeder_users SET reset_token = ?, reset_expires = ? WHERE id = ?");
    if ($stmt->execute([$token, $expires, $user_id])) {
        return $token;
    }
    return false;
}

// Clear reset token
function clearResetToken($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("UPDATE feeder_users SET reset_token = NULL, reset_expires = NULL WHERE id = ?");
    return $stmt->execute([$user_id]);
}

// Check rate limit for password reset requests
function checkResetRateLimit($email) {
    $ip = $_SERVER['REMOTE_ADDR'];
    $key = "reset_attempts_{$ip}_" . md5($email);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = [
            'count' => 1,
            'first_attempt' => time()
        ];
        return true;
    }
    
    $attempts = $_SESSION[$key];
    $timeSinceFirst = time() - $attempts['first_attempt'];
    
    if ($timeSinceFirst > RESET_REQUEST_WINDOW) {
        // Reset window
        $_SESSION[$key] = [
            'count' => 1,
            'first_attempt' => time()
        ];
        return true;
    }
    
    if ($attempts['count'] >= MAX_RESET_REQUESTS_PER_HOUR) {
        return false;
    }
    
    $_SESSION[$key]['count']++;
    return true;
}

// ========== EMAIL FUNCTIONS WITH PHPMailer ==========

// Send email using PHP mail() function (fallback)
function sendMailMail($to, $subject, $message) {
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_FROM . ">" . "\r\n";
    $headers .= "Reply-To: " . SMTP_FROM . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();
    
    return mail($to, $subject, $message, $headers);
}

// Send email using SMTP with PHPMailer
function sendSmtpMail($to, $subject, $message) {
    // Try to find PHPMailer in different locations
    $phpmailerFound = false;
    
    // Check for PHPMailer in phpmailer/src directory
    $phpmailerPath = __DIR__ . '/phpmailer/src/PHPMailer.php';
    $smtpPath = __DIR__ . '/phpmailer/src/SMTP.php';
    $exceptionPath = __DIR__ . '/phpmailer/src/Exception.php';
    
    // Check for PHPMailer in vendor directory (Composer)
    if (!file_exists($phpmailerPath)) {
        $phpmailerPath = __DIR__ . '/vendor/phpmailer/phpmailer/src/PHPMailer.php';
        $smtpPath = __DIR__ . '/vendor/phpmailer/phpmailer/src/SMTP.php';
        $exceptionPath = __DIR__ . '/vendor/phpmailer/phpmailer/src/Exception.php';
    }
    
    // Check for PHPMailer in PHPMailer directory
    if (!file_exists($phpmailerPath)) {
        $phpmailerPath = __DIR__ . '/PHPMailer/src/PHPMailer.php';
        $smtpPath = __DIR__ . '/PHPMailer/src/SMTP.php';
        $exceptionPath = __DIR__ . '/PHPMailer/src/Exception.php';
    }
    
    if (file_exists($phpmailerPath)) {
        require_once $phpmailerPath;
        require_once $smtpPath;
        require_once $exceptionPath;
        $phpmailerFound = true;
    }
    
    if ($phpmailerFound) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            // Server settings
            $mail->isSMTP();
            $mail->Host       = SMTP_HOST;
            $mail->SMTPAuth   = true;
            $mail->Username   = SMTP_USER;
            $mail->Password   = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = SMTP_PORT;
            $mail->CharSet    = 'UTF-8';
            
            // Recipients
            $mail->setFrom(SMTP_FROM, SMTP_FROM_NAME);
            $mail->addAddress($to);
            
            // Content
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags($message);
            
            return $mail->send();
        } catch (Exception $e) {
            error_log("SMTP Error: " . $mail->ErrorInfo);
            return false;
        }
    } else {
        error_log("PHPMailer not found. Please download PHPMailer from https://github.com/PHPMailer/PHPMailer");
        error_log("Checked paths: " . $phpmailerPath);
        return false;
    }
}

// Main send email function
function sendEmail($to, $subject, $message) {
    if (EMAIL_METHOD === 'smtp') {
        return sendSmtpMail($to, $subject, $message);
    } else {
        return sendMailMail($to, $subject, $message);
    }
}

// Send password reset email
function sendPasswordResetEmail($email, $resetToken, $userName) {
    $resetLink = SITE_URL . '/reset_password.php?token=' . $resetToken;
    
    $subject = "🔐 Password Reset Request - Smart Pet Feeder";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
            .button:hover { opacity: 0.9; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #6c757d; }
            .warning { background: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 20px 0; }
            .link-box { background: white; border: 1px solid #dee2e6; padding: 10px; border-radius: 5px; word-break: break-all; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🐾 Smart Pet Feeder</h2>
                <p>Password Reset Request</p>
            </div>
            <div class='content'>
                <p>Hello <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                <p>We received a request to reset your password. Click the button below to create a new password:</p>
                
                <div style='text-align: center;'>
                    <a href='{$resetLink}' class='button'>Reset Password</a>
                </div>
                
                <p>Or copy and paste this link into your browser:</p>
                <div class='link-box'>
                    <code>{$resetLink}</code>
                </div>
                
                <div class='warning'>
                    <strong>⚠️ Important:</strong>
                    <ul style='margin: 10px 0 0 20px;'>
                        <li>This link will expire in " . RESET_TOKEN_EXPIRY . " hours</li>
                        <li>If you didn't request this, please ignore this email</li>
                        <li>Your password will remain unchanged until you reset it</li>
                    </ul>
                </div>
                
                <p>Best regards,<br><strong>Smart Pet Feeder Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " Smart Pet Feeder. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

// Send password reset success email
function sendPasswordResetSuccessEmail($email, $userName) {
    $subject = "✅ Password Changed Successfully - Smart Pet Feeder";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; }
            .container { max-width: 600px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 30px; border-radius: 0 0 10px 10px; }
            .button { display: inline-block; padding: 12px 30px; background: linear-gradient(135deg, #28a745, #20c997); color: white; text-decoration: none; border-radius: 6px; margin: 20px 0; }
            .footer { text-align: center; padding: 20px; font-size: 12px; color: #6c757d; }
            .security-box { background: #d4edda; border-left: 4px solid #28a745; padding: 15px; margin: 20px 0; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🐾 Smart Pet Feeder</h2>
                <p>Password Changed Successfully</p>
            </div>
            <div class='content'>
                <p>Hello <strong>" . htmlspecialchars($userName) . "</strong>,</p>
                <p>Your password has been successfully changed.</p>
                
                <div class='security-box'>
                    <strong>🔒 Security Notice:</strong>
                    <p style='margin-top: 10px;'>If you did not make this change, please <a href='" . SITE_URL . "/forgot_password.php' style='color:#28a745;'>reset your password immediately</a> and contact support.</p>
                </div>
                
                <div style='text-align: center;'>
                    <a href='" . SITE_URL . "/login.php' class='button'>Login to Your Account</a>
                </div>
                
                <p>Best regards,<br><strong>Smart Pet Feeder Team</strong></p>
            </div>
            <div class='footer'>
                <p>This is an automated message, please do not reply to this email.</p>
                <p>&copy; " . date('Y') . " Smart Pet Feeder. All rights reserved.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($email, $subject, $message);
}

// ========== PET FEEDER FUNCTIONS ==========

// Get user's pets
function getUserPets($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM feeder_pets WHERE user_id = ? ORDER BY created_at DESC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Get feeding history
function getFeedingHistory($user_id, $limit = 20) {
    global $pdo;
    $limit = intval($limit);
    $stmt = $pdo->prepare("SELECT fh.*, p.pet_name 
                           FROM feeder_history fh 
                           JOIN feeder_pets p ON fh.pet_id = p.id 
                           WHERE fh.user_id = ? 
                           ORDER BY fh.feed_time DESC LIMIT $limit");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Get water level
function getWaterLevel($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM feeder_water_level WHERE user_id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetch();
}

// Get active schedules
function getActiveSchedules($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT fs.*, p.pet_name 
                           FROM feeder_schedule fs 
                           JOIN feeder_pets p ON fs.pet_id = p.id 
                           WHERE fs.user_id = ? AND fs.is_active = 1 
                           ORDER BY fs.feed_time ASC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Get all schedules
function getAllSchedules($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT fs.*, p.pet_name 
                           FROM feeder_schedule fs 
                           JOIN feeder_pets p ON fs.pet_id = p.id 
                           WHERE fs.user_id = ? 
                           ORDER BY fs.feed_time ASC");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Add notification
function addNotification($user_id, $title, $message, $type = 'system') {
    global $pdo;
    $stmt = $pdo->prepare("INSERT INTO feeder_notifications (user_id, title, message, type) VALUES (?, ?, ?, ?)");
    return $stmt->execute([$user_id, $title, $message, $type]);
}

// Get unread notifications count
function getUnreadNotificationsCount($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM feeder_notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Get notifications
function getNotifications($user_id, $limit = 10) {
    global $pdo;
    $limit = intval($limit);
    $stmt = $pdo->prepare("SELECT * FROM feeder_notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT $limit");
    $stmt->execute([$user_id]);
    return $stmt->fetchAll();
}

// Get user's dark mode preference
function getDarkMode($user_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT dark_mode FROM feeder_users WHERE id = ?");
    $stmt->execute([$user_id]);
    return $stmt->fetchColumn();
}

// Get device status
function getDeviceStatus() {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM feeder_device_status ORDER BY last_heartbeat DESC LIMIT 1");
    $stmt->execute();
    return $stmt->fetch();
}

// Update water level
function updateWaterLevel($user_id, $level) {
    global $pdo;
    $is_low = $level < 20 ? 1 : 0;
    $stmt = $pdo->prepare("UPDATE feeder_water_level SET water_level_percent = ?, is_low = ? WHERE user_id = ?");
    $stmt->execute([$level, $is_low, $user_id]);
    
    if ($is_low) {
        addNotification($user_id, '⚠️ Low Water Alert', 'Water level is below 20%. Please refill the water tank.', 'water');
    }
}

// ========== EMAIL ALERT FUNCTIONS ==========

// Send low food alert email
function sendLowFoodAlert($userEmail, $petName, $foodLevel) {
    $subject = "⚠️ LOW FOOD ALERT - Smart Pet Feeder";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #dc3545, #c82333); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; }
            .alert-box { background: #dc3545; color: white; padding: 15px; border-radius: 10px; text-align: center; margin: 15px 0; }
            .button { background: #667eea; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; display: inline-block; }
            .footer { text-align: center; padding: 15px; font-size: 12px; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>🐾 Smart Pet Feeder</h2>
            </div>
            <div class='content'>
                <div class='alert-box'>
                    <h3>⚠️ LOW FOOD ALERT!</h3>
                </div>
                <p>Dear Pet Owner,</p>
                <p>Your pet <strong>{$petName}</strong> has only <strong style='color:#dc3545;'>{$foodLevel}%</strong> food remaining in the feeder!</p>
                <p>Please refill the feeder soon to ensure your pet doesn't go hungry.</p>
                <br>
                <div style='text-align: center;'>
                    <a href='" . SITE_URL . "/dashboard.php' class='button'>Open Dashboard</a>
                </div>
                <br>
                <p>Best regards,<br>Smart Pet Feeder System</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from your Smart Pet Feeder system.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($userEmail, $subject, $message);
}

// Send feeding confirmation email
function sendFeedingConfirmation($userEmail, $petName, $portion, $source) {
    $subject = "✅ Feeding Confirmation - Smart Pet Feeder";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #28a745, #20c997); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; }
            .info-box { background: white; padding: 15px; border-radius: 10px; margin: 15px 0; }
            .footer { text-align: center; padding: 15px; font-size: 12px; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>✅ Feeding Completed</h2>
            </div>
            <div class='content'>
                <p>Dear Pet Owner,</p>
                <div class='info-box'>
                    <p><strong>🐾 Pet:</strong> {$petName}</p>
                    <p><strong>🍖 Portion:</strong> {$portion}g</p>
                    <p><strong>⏰ Time:</strong> " . date('F j, Y g:i A') . "</p>
                    <p><strong>🎮 Source:</strong> " . ucfirst($source) . "</p>
                </div>
                <p>Your pet has been fed successfully!</p>
                <br>
                <div style='text-align: center;'>
                    <a href='" . SITE_URL . "/dashboard.php' class='button' style='background:#28a745; color:white; padding:12px 24px; text-decoration:none; border-radius:5px;'>View Dashboard</a>
                </div>
                <br>
                <p>Best regards,<br>Smart Pet Feeder System</p>
            </div>
            <div class='footer'>
                <p>This is an automated message from your Smart Pet Feeder system.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($userEmail, $subject, $message);
}

// Send daily summary email
function sendDailySummary($userEmail, $userName, $dailyFeeds, $totalPortion, $avgPortion) {
    $subject = "📊 Daily Feeding Summary - Smart Pet Feeder";
    
    $message = "
    <!DOCTYPE html>
    <html>
    <head>
        <style>
            body { font-family: Arial, sans-serif; }
            .container { max-width: 600px; margin: 0 auto; }
            .header { background: linear-gradient(135deg, #667eea, #764ba2); color: white; padding: 20px; text-align: center; border-radius: 10px 10px 0 0; }
            .content { background: #f8f9fa; padding: 20px; border-radius: 0 0 10px 10px; }
            .stats { display: flex; justify-content: space-around; margin: 20px 0; }
            .stat-card { background: white; padding: 15px; border-radius: 10px; text-align: center; flex: 1; margin: 0 5px; }
            .stat-number { font-size: 24px; font-weight: bold; color: #667eea; }
            .footer { text-align: center; padding: 15px; font-size: 12px; color: #6c757d; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h2>📊 Daily Feeding Summary</h2>
            </div>
            <div class='content'>
                <p>Hello {$userName},</p>
                <p>Here's your daily feeding summary for " . date('F j, Y') . ":</p>
                
                <div class='stats'>
                    <div class='stat-card'>
                        <div class='stat-number'>{$dailyFeeds}</div>
                        <div>Total Feeds</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number'>{$totalPortion}g</div>
                        <div>Total Food</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number'>{$avgPortion}g</div>
                        <div>Avg Portion</div>
                    </div>
                </div>
                
                <div style='text-align: center; margin-top: 20px;'>
                    <a href='" . SITE_URL . "/dashboard.php' style='background:#667eea; color:white; padding:12px 24px; text-decoration:none; border-radius:5px;'>View Full Report</a>
                </div>
            </div>
            <div class='footer'>
                <p>This is an automated daily summary from your Smart Pet Feeder.</p>
            </div>
        </div>
    </body>
    </html>
    ";
    
    return sendEmail($userEmail, $subject, $message);
}

// Check food level and send alerts (call this function periodically)
function checkAndSendFoodAlerts() {
    global $pdo;
    
    // Get all devices with low food level (below 20%)
    $stmt = $pdo->prepare("
        SELECT d.*, u.email, u.full_name, p.pet_name 
        FROM feeder_device_status d
        JOIN feeder_users u ON d.user_id = u.id
        JOIN feeder_pets p ON u.id = p.user_id
        WHERE d.food_level < 20 AND d.food_level > 0
    ");
    $stmt->execute();
    $lowFoodDevices = $stmt->fetchAll();
    
    foreach ($lowFoodDevices as $device) {
        // Send email alert
        sendLowFoodAlert($device['email'], $device['pet_name'], $device['food_level']);
        // Also add notification
        addNotification($device['user_id'], '⚠️ Low Food Alert', "Your pet {$device['pet_name']} has only {$device['food_level']}% food remaining!", 'warning');
    }
}

// Test email function (for debugging)
function testEmail($testEmail) {
    $subject = "✅ Smart Pet Feeder - Email Test";
    $message = "
    <html>
    <body>
        <h2>Email Test Successful!</h2>
        <p>Your Smart Pet Feeder system is configured correctly.</p>
        <p>Time: " . date('Y-m-d H:i:s') . "</p>
    </body>
    </html>
    ";
    return sendEmail($testEmail, $subject, $message);
}

// Validate password strength
function validatePasswordStrength($password) {
    $errors = [];
    
    if (strlen($password) < 8) {
        $errors[] = "Password must be at least 8 characters long";
    }
    if (!preg_match('/[A-Z]/', $password)) {
        $errors[] = "Password must contain at least one uppercase letter";
    }
    if (!preg_match('/[a-z]/', $password)) {
        $errors[] = "Password must contain at least one lowercase letter";
    }
    if (!preg_match('/[0-9]/', $password)) {
        $errors[] = "Password must contain at least one number";
    }
    
    return $errors;
}

// Update user password
function updateUserPassword($user_id, $newPassword) {
    global $pdo;
    $password_hash = password_hash($newPassword, PASSWORD_DEFAULT);
    $stmt = $pdo->prepare("UPDATE feeder_users SET password_hash = ? WHERE id = ?");
    return $stmt->execute([$password_hash, $user_id]);
}
?>