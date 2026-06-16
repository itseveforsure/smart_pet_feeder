<?php
require_once 'config.php';

echo "<h2>Email Configuration Test</h2>";

// Test email address (change to your email)
$testEmail = "your-email@gmail.com"; // CHANGE THIS

echo "<h3>Testing email to: " . $testEmail . "</h3>";

// Test using mail() function
echo "<h4>Testing mail() function:</h4>";
$subject = "Test Email from Smart Pet Feeder";
$message = "<html><body><h2>Test Email</h2><p>If you receive this, your email is working!</p><p>Time: " . date('Y-m-d H:i:s') . "</p></body></html>";
$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type:text/html;charset=UTF-8\r\n";
$headers .= "From: Smart Pet Feeder <noreply@smartpetfeeder.com>\r\n";

if (@mail($testEmail, $subject, $message, $headers)) {
    echo "<p style='color:green'>✓ mail() function executed successfully. Check your inbox/spam folder.</p>";
} else {
    echo "<p style='color:red'>✗ mail() function failed. Need to configure SMTP.</p>";
}

// Test using sendEmail function
echo "<h4>Testing sendEmail() function:</h4>";
$result = testEmail($testEmail);
if ($result) {
    echo "<p style='color:green'>✓ sendEmail() executed successfully. Check your inbox/spam folder.</p>";
} else {
    echo "<p style='color:red'>✗ sendEmail() failed.</p>";
}

echo "<h3>Configuration Steps:</h3>";
echo "<ol>";
echo "<li>For Gmail: Enable 2-Factor Authentication and generate App Password</li>";
echo "<li>Update SMTP_USER and SMTP_PASS in config.php</li>";
echo "<li>Change EMAIL_METHOD to 'smtp' in config.php</li>";
echo "<li>For testing locally, you can use <a href='https://github.com/PHPMailer/PHPMailer' target='_blank'>PHPMailer</a></li>";
echo "</ol>";
?>