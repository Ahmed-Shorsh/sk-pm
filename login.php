<?php
// File: public/login.php

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php'); exit();
}

require_once __DIR__ . '/backend/db.php';
require_once __DIR__ . '/backend/utils.php';
require_once __DIR__ . '/backend/auth.php';

$errors = [];
$email  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email    = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if (!isValidEmail($email)) {
        $errors[] = 'Enter a valid email.';
    }
    if (empty($password)) {
        $errors[] = 'Enter your password.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare('
            SELECT u.user_id, u.name, u.role_id, u.password_hash, r.role_name
            FROM users u
            JOIN roles r ON u.role_id = r.role_id
            WHERE u.email = :email AND u.active = 1
        ');
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password_hash'])) {
            loginUser($user);
            header('Location: dashboard.php');
            exit();
        } else {
            $errors[] = 'Invalid email or password.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Log In – SK‑PM</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link href="https://fonts.googleapis.com/css2?family=Merriweather&family=Playfair+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="./assets/css/style.css">

<link
    href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css"
    rel="stylesheet"
    integrity="sha384-9ndCyUa6mYkV+gEw5Z2a8q0n/2Z1s2lW4mAz6hZ2wAQe1uqUjFZCSvFcGPKmF0xg"
    crossorigin="anonymous" />


<script
    src="https://code.jquery.com/jquery-3.6.4.min.js"
    integrity="sha256-VvG6/iJ7mMzrZ2Yb3G7Uj5GJZkaLyE9mXx6f5r9g8DY="
    crossorigin="anonymous"></script>
<script
    src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"
    integrity="sha384-ENjdO4Dr2bkBIFxQpeoY1wWmI4mg3ObQ4CbbY6EVhZlYxjRRF9j+8abtTE1Pi6jizo"
    crossorigin="anonymous"></script>
    <link rel="icon" href="./assets/logo/sk-n.ico" type="image/x-icon">

</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark bg-dark">
    <a class="navbar-brand" href="index.php">SK‑PM</a>
</nav>

<main class="container py-5">
    <h1 class="mb-4">Log In to Your Account</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $e): ?>
                    <li><?= htmlspecialchars($e) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="login.php" novalidate>
        <div class="form-group mb-3">
            <label for="email">Email Address</label>
            <input 
                type="email" 
                id="email" 
                name="email" 
                class="form-control" 
                required 
                value="<?= htmlspecialchars($email) ?>"
            >
        </div>

        <div class="form-group mb-4">
            <label for="password">Password</label>
            <input 
                type="password" 
                id="password" 
                name="password" 
                class="form-control" 
                required
            >
        </div>

        <button type="submit" class="btn btn-dark btn-block">Log In</button>
    </form>

    <p class="mt-4 text-center">
        Don’t have an account? <a href="signup.php">Sign up here</a>.
    </p>
</main>

<footer class="footer py-3 text-center">
    <small>&copy; <?= date('Y') ?> SK‑PM Performance Management</small>
</footer>


</body>
</html>
