<?php
/**
 * Email Verification Handler for SK-PM
 * Handles email verification tokens and activates user accounts
 */

session_start();

require_once __DIR__ . '/db.php';    // $pdo
require_once __DIR__ . '/utils.php'; // sanitize function

// Initialize variables
$status = 'pending'; // pending, success, error, expired, invalid
$message = '';
$user_name = '';

// Check if token is provided
if (!isset($_GET['token']) || empty($_GET['token'])) {
    $status = 'invalid';
    $message = 'Invalid verification link. No token provided.';
} else {
    $token = sanitize($_GET['token']);
    
    try {
        // Look up the token in the database
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
            // Token is valid and not expired, verify the email
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
                
                // Log the successful verification
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

// If user is already logged in and verification is successful, redirect to dashboard
if ($status === 'success' && !empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Email Verification &#8211; SK-PM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link
      href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
      rel="stylesheet"
      integrity="sha384-9ndCyUa6mYkV+gEw5Z2a8q0n/2Z1s2lW4mAz6hZ2wAQe1uqUjFZCSvFcGPKmF0xg"
      crossorigin="anonymous"
    />
    <link rel="icon" href="../assets/logo/sk-n.ico" type="image/x-icon">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
        }
        .verification-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 600px;
            margin: 4rem auto;
        }
        .verification-header {
            padding: 3rem 2rem 2rem;
            text-align: center;
        }
        .verification-body {
            padding: 0 2rem 3rem;
            text-align: center;
        }
        .status-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 2rem;
            font-size: 2rem;
        }
        .status-success {
            background: linear-gradient(135deg, #28a745, #20c997);
            color: white;
        }
        .status-error {
            background: linear-gradient(135deg, #dc3545, #fd7e14);
            color: white;
        }
        .status-expired {
            background: linear-gradient(135deg, #ffc107, #fd7e14);
            color: white;
        }
        .status-invalid {
            background: linear-gradient(135deg, #6c757d, #495057);
            color: white;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
        }
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(102, 126, 234, 0.3);
            color: white;
        }
        .btn-secondary {
            background: linear-gradient(135deg, #6c757d, #495057);
            border: none;
            border-radius: 8px;
            padding: 0.75rem 2rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-decoration: none;
            display: inline-block;
            color: white;
            margin-left: 1rem;
        }
        .btn-secondary:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(108, 117, 125, 0.3);
            color: white;
        }
        .navbar {
            background: rgba(52, 58, 64, 0.95) !important;
            backdrop-filter: blur(10px);
        }
        .loading-spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        .user-greeting {
            color: #495057;
            font-size: 1.1rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <div class="container">
        <a class="navbar-brand fw-bold" href="index.php">SK-PM</a>
    </div>
</nav>

<div class="container py-4">
    <div class="verification-container">
        <div class="verification-header">
            <?php if ($status === 'success'): ?>
                <div class="status-icon status-success">
                    <i class="fas fa-check"></i>
                </div>
                <h1 class="text-success mb-3">Email Verified Successfully!</h1>
                <?php if ($user_name): ?>
                    <p class="user-greeting">Welcome, <?= htmlspecialchars($user_name) ?>!</p>
                <?php endif; ?>
            <?php elseif ($status === 'expired'): ?>
                <div class="status-icon status-expired">
                    <i class="fas fa-clock"></i>
                </div>
                <h1 class="text-warning mb-3">Verification Link Expired</h1>
                <?php if ($user_name): ?>
                    <p class="user-greeting">Hello, <?= htmlspecialchars($user_name) ?></p>
                <?php endif; ?>
            <?php elseif ($status === 'invalid'): ?>
                <div class="status-icon status-invalid">
                    <i class="fas fa-exclamation-triangle"></i>
                </div>
                <h1 class="text-muted mb-3">Invalid Verification Link</h1>
            <?php else: ?>
                <div class="status-icon status-error">
                    <i class="fas fa-times"></i>
                </div>
                <h1 class="text-danger mb-3">Verification Failed</h1>
            <?php endif; ?>
        </div>
        
        <div class="verification-body">
            <p class="lead mb-4"><?= htmlspecialchars($message) ?></p>
            
            <div class="d-flex justify-content-center flex-wrap gap-2">
                <?php if ($status === 'success'): ?>
                    <a href="login.php" class="btn btn-primary">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In Now
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home me-2"></i>Home
                    </a>
                <?php elseif ($status === 'expired'): ?>
                    <a href="resend-verification.php" class="btn btn-primary">
                        <i class="fas fa-envelope me-2"></i>Request New Verification
                    </a>
                    <a href="login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </a>
                <?php elseif ($status === 'invalid'): ?>
                    <a href="signup.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Create Account
                    </a>
                    <a href="login.php" class="btn btn-secondary">
                        <i class="fas fa-sign-in-alt me-2"></i>Sign In
                    </a>
                <?php else: ?>
                    <a href="signup.php" class="btn btn-primary">
                        <i class="fas fa-user-plus me-2"></i>Try Again
                    </a>
                    <a href="index.php" class="btn btn-secondary">
                        <i class="fas fa-home me-2"></i>Home
                    </a>
                <?php endif; ?>
            </div>
            
            <?php if ($status === 'expired' || $status === 'error'): ?>
                <div class="mt-4 p-3 bg-light rounded">
                    <small class="text-muted">
                        <i class="fas fa-info-circle me-1"></i>
                        Need help? Contact our support team for assistance with your account verification.
                    </small>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<script>
// Add some visual feedback when buttons are clicked
document.addEventListener('DOMContentLoaded', function() {
    const buttons = document.querySelectorAll('.btn');
    
    buttons.forEach(button => {
        button.addEventListener('click', function() {
            // Add a subtle loading effect
            const originalHTML = this.innerHTML;
            const spinner = '<span class="loading-spinner"></span>';
            
            if (!this.innerHTML.includes('loading-spinner')) {
                this.innerHTML = spinner + originalHTML;
                this.disabled = true;
                
                // Re-enable after a short delay (in case navigation fails)
                setTimeout(() => {
                    this.innerHTML = originalHTML;
                    this.disabled = false;
                }, 3000);
            }
        });
    });
});
</script>
</body>
</html>