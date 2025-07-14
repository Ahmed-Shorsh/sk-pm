<?php
// File: backend/utils.php
// Purpose: Common helper functions

// Redirect helper
function redirect($url) {
    header("Location: $url");
    exit();
}

// Sanitize user input
function sanitize($input) {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

// Display a Bootstrap‑style alert
function flashMessage($message, $type = 'success') {
    echo "<div class='alert alert-$type'>$message</div>";
}

// Validate email format
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}
