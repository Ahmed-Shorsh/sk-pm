<?php
// Redirect logged-in users
session_start();
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit();
}

require_once __DIR__ . '/backend/db.php';    // $pdo
require_once __DIR__ . '/backend/utils.php'; // sanitize, isValidEmail
require_once __DIR__ . '/backend/email.php'; // EmailService

// Load .env file if needed
env_load_once();

function env_load_once(): void {
    if (getenv('TG_BOT_USERNAME') === false) {
        $env = __DIR__ . '/.env';
        if (is_readable($env)) {
            foreach (file($env, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') continue;
                [$k, $v] = array_map('trim', explode('=', $line, 2));
                if ($k !== '' && getenv($k) === false) {
                    putenv("$k=$v");
                }
            }
        }
    }
}

// Prepare for form values
$errors = [];
$success_message = '';
$name = '';
$email = '';
$dept_id = null;
$password = '';
$confirm = '';
$phone = '';
$position = '';
$birth_date = '';
$hire_date = '';

// Telegram requirement setting
$telegramRequired = false;
try {
    $stmt = $pdo->prepare(
        "SELECT setting_value FROM settings WHERE setting_key = 'telegram_signup_required'"
    );
    $stmt->execute();
    $val = $stmt->fetchColumn();
    if ($val !== false) {
        $telegramRequired = ($val === '1');
    }
} catch (Exception $e) {
    error_log("Unable to load telegram setting: " . $e->getMessage());
}

// Fetch departments
try {
    $stmt = $pdo->query('SELECT dept_id, dept_name FROM departments ORDER BY dept_name');
    $departments = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    die('Unable to load departments.');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Sanitize inputs
    $name = sanitize($_POST['name'] ?? '');
    $email = sanitize($_POST['email'] ?? '');
    $dept_id = (int)($_POST['dept_id'] ?? 0);
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';
    $phone = sanitize($_POST['phone'] ?? '');
    $position = sanitize($_POST['position'] ?? '');
    $birth_date = $_POST['birth_date'] ?? '';
    $hire_date = $_POST['hire_date'] ?? '';

    // Validation
    if (mb_strlen($name) < 2) {
        $errors['name'] = 'Name must be at least 2 characters.';
    }
    if (!isValidEmail($email)) {
        $errors['email'] = 'Please enter a valid email address.';
    }
    $validDeptIds = array_column($departments, 'dept_id');
    if (!in_array($dept_id, $validDeptIds, true)) {
        $errors['dept_id'] = 'Please select a valid department.';
    }
    if (!preg_match('/^\+[1-9]\d{1,14}$/', $phone)) {
        $errors['phone'] = 'Phone must be in international format (e.g., +1234567890).';
    }
    if (empty($position)) {
        $errors['position'] = 'Position is required.';
    }
    if ($birth_date && !DateTime::createFromFormat('Y-m-d', $birth_date)) {
        $errors['birth_date'] = 'Please enter a valid birth date.';
    }
    if ($hire_date && !DateTime::createFromFormat('Y-m-d', $hire_date)) {
        $errors['hire_date'] = 'Please enter a valid hire date.';
    }
    if (mb_strlen($password) < 8) {
        $errors['password'] = 'Password must be at least 8 characters long.';
    }
    if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/', $password)) {
        $errors['password'] = 'Password must contain at least one uppercase letter, one lowercase letter, and one number.';
    }
    if ($password !== $confirm) {
        $errors['confirm_password'] = 'Password confirmation does not match.';
    }
    if (empty($errors['email'])) {
        $stmt = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetchColumn() > 0) {
            $errors['email'] = 'An account with this email already exists.';
        }
    }

    if (empty($errors)) {
        try {
            $pdo->beginTransaction();

            $hash = password_hash($password, PASSWORD_BCRYPT);
            $verification_token = generateVerificationToken();
            $verification_expires = date('Y-m-d H:i:s', strtotime('+24 hours'));
            $sql = 'INSERT INTO users
                    (name, email, password_hash, role_id, dept_id, phone, position, birth_date, hire_date,
                     active, email_verified, email_verification_token, email_verification_expires)
                    VALUES
                    (:name, :email, :hash, 3, :dept, :phone, :position, :birth_date, :hire_date,
                            1, 0, :token, :expires)';
            $stmt = $pdo->prepare($sql);
            $success = $stmt->execute([
                ':name' => $name,
                ':email' => $email,
                ':hash' => $hash,
                ':dept' => $dept_id,
                ':phone' => $phone,
                ':position' => $position,
                ':birth_date' => $birth_date ?: null,
                ':hire_date' => $hire_date ?: null,
                ':token' => $verification_token,
                ':expires' => $verification_expires
            ]);

            if ($success) {
                $userId = $pdo->lastInsertId();
                $telegramToken = bin2hex(random_bytes(16));
                $pdo->prepare(
                    'INSERT INTO user_telegram (user_id, token) VALUES (?, ?)'
                )->execute([$userId, $telegramToken]);

                $emailService = new EmailService();
                $emailSent = $emailService->sendVerificationEmail($email, $name, $verification_token);

                $pdo->commit();

                $botUsername = getenv('TG_BOT_USERNAME');
                $botLink = "https://t.me/{$botUsername}?start={$telegramToken}";
                if ($emailSent) {
                    if ($telegramRequired) {
                        $success_message =
                            "Registration successful!<br>✅ Check your email to activate.<br><br>"
                          . "🚩 <strong>One more step:</strong> Verify via Telegram. Press /start.";
                    } else {
                        $success_message =
                            "Registration successful!<br>✅ Check your email to activate.<br><br>"
                          . "💬 Tip: Link Telegram for reminders (optional).";
                    }
                } else {
                    $success_message =
                        "Registration successful! But we couldn't send the email. Contact support.<br><br>"
                      . "💬 You can still link Telegram:";
                }
                $success_message .= "<br><br><a href=\"{$botLink}\" class=\"btn btn-primary\" target=\"_blank\">"
                                   . "🔗 Verify via Telegram</a>";

                $name = $email = $phone = $position = $birth_date = $hire_date = $password = $confirm = '';
                $dept_id = null;
            } else {
                $pdo->rollBack();
                $errors['general'] = 'Registration failed. Please try again.';
            }
        } catch (Exception $e) {
            $pdo->rollBack();
            error_log("Registration error: " . $e->getMessage());
            $errors['general'] = 'Registration failed. Please try again.';
        }
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Sign Up – SK‑PM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/style.css">
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
        rel="stylesheet"
        integrity="sha384-9ndCyUa6mYkV+gEw5Z2a8q0n/2Z1s2lW4mAz6hZ2wAQe1uqUjFZCSvFcGPKmF0xg"
        crossorigin="anonymous"
    />
    <script
        src="https://code.jquery.com/jquery-3.6.4.min.js"
        integrity="sha256-VvG6/iJ7mMzrZ2Yb3G7Uj5GJZkaLyE9mXx6f5r9g8DY="
        crossorigin="anonymous"
    ></script>
    <script
        src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-ENjdO4Dr2bkBIFxQpeoY1wWmI4mg3ObQ4CbbY6EVhZlYxjRRF9j+8abtTE1Pi6jizo"
        crossorigin="anonymous"
    ></script>
    <link rel="icon" href="../assets/logo/sk-n.ico" type="image/x-icon">
    <style>
        .field-error {
            color: #dc3545;
            font-size: 0.875rem;
            margin-top: 0.25rem;
        }
        .field-note {
            color: #6c757d;
            font-size: 0.875rem;
            margin-top: 0.25rem;
            font-style: italic;
        }
        .form-control.is-invalid {
            border-color: #dc3545;
        }
        .form-control.is-valid {
            border-color: #198754;
        }
    </style>
</head>
<body>


<main class="container py-5">
<div class="text-center mb-4">
    <img src="./assets/logo/ske-dark.png" alt="SK-ESTATE" class="img-fluid" style="max-width: auto; height: 150px;">
</div>

    <h1 class="mb-4">Create Your Account</h1>

    <?php if ($success_message): ?>
    <div class="alert alert-success">
        <?= $success_message ?>
    </div>
<?php endif; ?>

    <?php if (!empty($errors['general'])): ?>
        <div class="alert alert-danger">
            <?= htmlspecialchars($errors['general']) ?>
        </div>
    <?php endif; ?>

    <form method="POST" action="signup.php" novalidate id="signupForm">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="name">Full Name *</label>
                    <input 
                        type="text" 
                        id="name" 
                        name="name" 
                        class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" 
                        required 
                        value="<?= htmlspecialchars($name) ?>"
                    >
                    <div class="field-note">As in passport.</div>
                    <?php if (isset($errors['name'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['name']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="email">Email Address *</label>
                    <input 
                        type="email" 
                        id="email" 
                        name="email" 
                        class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" 
                        required 
                        value="<?= htmlspecialchars($email) ?>"
                    >
                    <div class="field-note">We'll send a verification link to this email</div>
                    <?php if (isset($errors['email'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['email']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="dept_id">Department *</label>
                    <select 
                        id="dept_id" 
                        name="dept_id" 
                        class="form-control <?= isset($errors['dept_id']) ? 'is-invalid' : '' ?>" 
                        required
                    >
                        <option value="">-- Select Department --</option>
                        <?php foreach ($departments as $d): ?>
                            <option 
                                value="<?= $d['dept_id'] ?>" 
                                <?= $d['dept_id'] === $dept_id ? 'selected' : '' ?>
                            >
                                <?= htmlspecialchars($d['dept_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (isset($errors['dept_id'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['dept_id']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="phone">Phone Number *</label>
                    <input 
                        type="tel" 
                        id="phone" 
                        name="phone" 
                        class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" 
                        required 
                        value="<?= htmlspecialchars($phone) ?>"
                        pattern="\+[1-9]\d{1,14}"
                    >
                    <div class="field-note">International format (e.g., +1234567890)</div>
                    <?php if (isset($errors['phone'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['phone']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="position">Position *</label>
                    <input 
                        type="text" 
                        id="position" 
                        name="position" 
                        class="form-control <?= isset($errors['position']) ? 'is-invalid' : '' ?>" 
                        required 
                        value="<?= htmlspecialchars($position) ?>"
                    >
                    <?php if (isset($errors['position'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['position']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="birth_date">Birth Date</label>
                    <input 
                        type="date" 
                        id="birth_date" 
                        name="birth_date" 
                        class="form-control <?= isset($errors['birth_date']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($birth_date) ?>"
                    >
                    <div class="field-note">Optional</div>
                    <?php if (isset($errors['birth_date'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['birth_date']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-3">
                <div class="form-group mb-3">
                    <label for="hire_date">Hire Date</label>
                    <input 
                        type="date" 
                        id="hire_date" 
                        name="hire_date" 
                        class="form-control <?= isset($errors['hire_date']) ? 'is-invalid' : '' ?>" 
                        value="<?= htmlspecialchars($hire_date) ?>"
                    >
                    <div class="field-note">Optional</div>
                    <?php if (isset($errors['hire_date'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['hire_date']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="form-group mb-3">
                    <label for="password">Password *</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        class="form-control <?= isset($errors['password']) ? 'is-invalid' : '' ?>" 
                        required
                        minlength="8"
                    >
                    <div class="field-note">At least 8 characters with uppercase, lowercase, and number</div>
                    <?php if (isset($errors['password'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['password']) ?></div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="col-md-6">
                <div class="form-group mb-4">
                    <label for="confirm_password">Confirm Password *</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        class="form-control <?= isset($errors['confirm_password']) ? 'is-invalid' : '' ?>" 
                        required
                        minlength="8"
                    >
                    <?php if (isset($errors['confirm_password'])): ?>
                        <div class="field-error"><?= htmlspecialchars($errors['confirm_password']) ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-dark btn-block">Create Account</button>
    </form>

    <p class="mt-4 text-center">
        Already have an account? <a href="login.php">Log in here</a>.
    </p>
</main>

<footer class="footer py-3 text-center">
    <small>&copy; <?= date('Y') ?> SK‑PM Performance Management</small>
</footer>

<script>
// Real-time validation
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('signupForm');
    const phoneInput = document.getElementById('phone');
    const passwordInput = document.getElementById('password');
    const confirmPasswordInput = document.getElementById('confirm_password');
    
    // Phone number formatting and validation
    phoneInput.addEventListener('input', function() {
        let value = this.value;
        
        // Remove any non-digit characters except +
        value = value.replace(/[^\d+]/g, '');
        
        // Ensure it starts with +
        if (!value.startsWith('+') && value.length > 0) {
            value = '+' + value;
        }
        
        this.value = value;
        
        // Validate format
        const isValid = /^\+[1-9]\d{1,14}$/.test(value);
        this.classList.toggle('is-invalid', value.length > 0 && !isValid);
        this.classList.toggle('is-valid', isValid);
    });
    
    // Password strength validation
    passwordInput.addEventListener('input', function() {
        const password = this.value;
        const isValid = password.length >= 8 && 
                       /(?=.*[a-z])/.test(password) && 
                       /(?=.*[A-Z])/.test(password) && 
                       /(?=.*\d)/.test(password);
        
        this.classList.toggle('is-invalid', password.length > 0 && !isValid);
        this.classList.toggle('is-valid', isValid);
        
        // Also check confirm password if it has value
        if (confirmPasswordInput.value) {
            validatePasswordConfirmation();
        }
    });
    
    // Password confirmation validation
    confirmPasswordInput.addEventListener('input', validatePasswordConfirmation);
    
    function validatePasswordConfirmation() {
        const password = passwordInput.value;
        const confirm = confirmPasswordInput.value;
        const isValid = confirm.length > 0 && password === confirm;
        
        confirmPasswordInput.classList.toggle('is-invalid', confirm.length > 0 && !isValid);
        confirmPasswordInput.classList.toggle('is-valid', isValid);
    }
});
</script>
</body>
</html>