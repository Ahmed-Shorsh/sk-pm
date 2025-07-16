<?php
session_start();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/utils.php';

$status = 'pending';
$message = '';
$user_name = '';

if (!isset($_GET['token']) || empty($_GET['token'])) {
    $status = 'invalid';
    $message = 'Invalid verification link. No token provided.';
} else {
    $token = sanitize($_GET['token']);
    
    try {
        $stmt = $pdo->prepare('
            SELECT user_id, name, email, email_verified, email_verification_expires 
            FROM users 
            WHERE email_verification_token = ? AND active = 1
        ');
        $stmt->execute([$token]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$user) {
            $status = 'invalid';
            $message = 'Invalid verification link. Token not found or account is inactive.';
        } elseif ($user['email_verified'] == 1) {
            $status = 'success';
            $message = 'Your email has already been verified. You can now log in to your account.';
            $user_name = $user['name'];
        } elseif (strtotime($user['email_verification_expires']) < time()) {
            $status = 'expired';
            $message = 'This verification link has expired. Please request a new verification email.';
            $user_name = $user['name'];
        } else {
            $pdo->beginTransaction();
            
            $updateStmt = $pdo->prepare('
                UPDATE users 
                SET email_verified = 1, 
                    email_verification_token = NULL, 
                    email_verification_expires = NULL 
                WHERE user_id = ?
            ');
            $success = $updateStmt->execute([$user['user_id']]);
            
            if ($success) {
                $pdo->commit();
                $status = 'success';
                $message = 'Congratulations! Your email has been successfully verified. You can now log in to your account.';
                $user_name = $user['name'];
                
                error_log("Email verified successfully for user: " . $user['email']);
            } else {
                $pdo->rollBack();
                $status = 'error';
                $message = 'Failed to verify your email. Please try again or contact support.';
            }
        }
        
    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("Email verification error: " . $e->getMessage());
        $status = 'error';
        $message = 'An error occurred while verifying your email. Please try again or contact support.';
    }
}

if ($status === 'success' && !empty($_SESSION['user_id'])) {
    header('Location: ../dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification – SK-PM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet" crossorigin="anonymous">
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="icon" href="../assets/logo/sk-n.ico" type="image/x-icon">
</head>
<body class="bg-light text-dark font-serif">

<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="../index.php">SK-PM</a>
    </div>
</nav>

<div class="container py-5">
    <div class="data-container text-center">
        <?php if ($status === 'success'): ?>
            <div class="mb-4">
                <div class="display-1 text-success mb-3">✓</div>
                <h1 class="text-success mb-3">Email Verified Successfully!</h1>
                <?php if ($user_name): ?>
                    <p class="lead mb-3">Welcome, <?= htmlspecialchars($user_name) ?>!</p>
                <?php endif; ?>
            </div>
        <?php elseif ($status === 'expired'): ?>
            <div class="mb-4">
                <div class="display-1 text-warning mb-3">⏰</div>
                <h1 class="text-warning mb-3">Verification Link Expired</h1>
                <?php if ($user_name): ?>
                    <p class="lead mb-3">Hello, <?= htmlspecialchars($user_name) ?></p>
                <?php endif; ?>
            </div>
        <?php elseif ($status === 'invalid'): ?>
            <div class="mb-4">
                <div class="display-1 text-muted mb-3">⚠</div>
                <h1 class="text-muted mb-3">Invalid Verification Link</h1>
            </div>
        <?php else: ?>
            <div class="mb-4">
                <div class="display-1 text-danger mb-3">✗</div>
                <h1 class="text-danger mb-3">Verification Failed</h1>
            </div>
        <?php endif; ?>
        
        <p class="lead mb-4"><?= htmlspecialchars($message) ?></p>
        
        <div class="d-flex justify-content-center flex-wrap gap-3">
            <?php if ($status === 'success'): ?>
                <a href="../login.php" class="btn" style="background-color: #000; color: #fff; border: 2px solid #000; padding: 0.75rem 1.5rem; text-decoration: none;">Sign In Now</a>
                <a href="../index.php" class="btn" style="background-color: #fff; color: #000; border: 2px solid #000; padding: 0.75rem 1.5rem; text-decoration: none;">Home</a>
            <?php elseif ($status === 'expired'): ?>
                <a href="../resend-verification.php" class="btn" style="background-color: #000; color: #fff; border: 2px solid #000; padding: 0.75rem 1.5rem; text-decoration: none;">Request New Verification</a>
                <a href="../login.php" class="btn" style="background-color: #fff; color: #000; border: 2px solid #000; padding: 0.75rem 1.5rem; text-decoration: none;">Sign In</a>
            <?php elseif ($status === 'invalid'): ?>
                <a href="../signup.php" class="btn" style="background-color: #000; color: #fff; border: 2px solid #000; padding: 0.75rem 1.5rem; text-decoration: none;">Create Account</a>
                <a href="../login.php" class="btn" style="background-color: #fff; color: #000; border: 2px solid #000; padding: 0.75rem 1.5rem; text-decoration: none;">Sign In</a>
            <?php else: ?>
                <a href="../signup.php" class="btn" style="background-color: #000; color: #fff; border: 2px solid #000; padding: 0.75rem 1.5rem; text-decoration: none;">Try Again</a>
                <a href="../index.php" class="btn" style="background-color: #fff; color: #000; border: 2px solid #000; padding: 0.75rem 1.5rem; text-decoration: none;">Home</a>
            <?php endif; ?>
        </div>
        
        <?php if ($status === 'expired' || $status === 'error'): ?>
            <div class="mt-4 p-3 bg-light border-2" style="border: 2px solid #000;">
                <small class="text-muted">
                    Need help? Contact our support team for assistance with your account verification.
                </small>
            </div>
        <?php endif; ?>
    </div>
</div>

<footer class="footer py-3 text-center">
    <small>&copy; <?= date('Y') ?> SK-PM Performance Management</small>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.btn');
    
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            if (!this.classList.contains('loading')) {
                this.classList.add('loading');
                const originalText = this.textContent;
                this.textContent = 'Loading...';
                
                setTimeout(() => {
                    this.textContent = originalText;
                    this.classList.remove('loading');
                }, 3000);
            }
        });
    });
});
</script>
</body>
</html>