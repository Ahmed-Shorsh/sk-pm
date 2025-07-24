<?php
require_once 'config.php';

$message = '';
$messageType = '';

// Initialize variables to preserve form data
$studentId = '';
$name = '';
$university = '';
$otherUniversity = '';
$email = '';
$phone = '';
$academicYear = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $pdo = getDBConnection();
    $userIP = getUserIP();
    
    // Preserve form data
    $studentId = trim($_POST['student_id']);
    $name = trim($_POST['name']);
    $university = trim($_POST['university']);
    $otherUniversity = trim($_POST['other_university'] ?? '');
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $academicYear = trim($_POST['academic_year']);
    
    // Check rate limit
    if (!checkRateLimit($pdo, $userIP)) {
        $message = 'Too many attempts. Please try again later.';
        $messageType = 'error';
    } else {
        // Validate and sanitize input
        $errors = [];
        
        // Validation
        if (empty($studentId)) {
            $errors[] = 'Student ID is required';
        }
        
        if (empty($name)) {
            $errors[] = 'Name is required';
        }
        
        if (empty($university)) {
            $errors[] = 'University is required';
        }
        
        if ($university === 'Other' && empty($otherUniversity)) {
            $errors[] = 'Please specify your university';
        }
        
        if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Valid email is required';
        }
        
        if (empty($phone) || !preg_match('/^[0-9]{10}$/', $phone)) {
            $errors[] = 'Phone number must be in format +964XXXXXXXXXX';
        }
        
        if (empty($academicYear)) {
            $errors[] = 'Academic year is required';
        }
        
        // Check for duplicate student ID
        if (empty($errors)) {
            $stmt = $pdo->prepare("SELECT id FROM students WHERE student_id = ?");
            $stmt->execute([$studentId]);
            if ($stmt->fetch()) {
                $errors[] = 'This student ID is already registered';
            }
        }
        
        if (empty($errors)) {
            // Insert into database
            $stmt = $pdo->prepare("INSERT INTO students (student_id, name, university, other_university, email, phone, academic_year, voucher_code, ip_address) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $finalUniversity = ($university === 'Other') ? $otherUniversity : $university;
            $fullPhone = '+964' . $phone; // Add +964 prefix before storing
            
            if ($stmt->execute([$studentId, $name, $finalUniversity, $otherUniversity, $email, $fullPhone, $academicYear, VOUCHER_CODE, $userIP])) {
                // Send confirmation email
                $emailSubject = 'Your Highcrest Hotel Student Voucher - Confirmation';
                $emailBody = generateConfirmationEmail($name, $studentId, $finalUniversity, VOUCHER_CODE);
                
                if (sendEmail($email, $name, $emailSubject, $emailBody)) {
                    $message = 'Registration successful! Please check your email for voucher details.';
                    $messageType = 'success';
                    
                    // Clear form data on successful submission
                    $studentId = '';
                    $name = '';
                    $university = '';
                    $otherUniversity = '';
                    $email = '';
                    $phone = '';
                    $academicYear = '';
                } else {
                    $message = 'Registration successful, but email could not be sent. Please contact us.';
                    $messageType = 'warning';
                    
                    // Clear form data on successful submission
                    $studentId = '';
                    $name = '';
                    $university = '';
                    $otherUniversity = '';
                    $email = '';
                    $phone = '';
                    $academicYear = '';
                }
            } else {
                $message = 'Registration failed. Please try again.';
                $messageType = 'error';
            }
        } else {
            $message = implode('<br>', $errors);
            $messageType = 'error';
        }
    }
}

function generateConfirmationEmail($name, $studentId, $university, $voucherCode) {
    $expiryDate = date('F j, Y', strtotime(VOUCHER_EXPIRY));
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <title>Highcrest Hotel - Student Voucher Confirmation</title>
    </head>
    <body style='margin: 0; padding: 0; font-family: Arial, sans-serif; background-color: #f4f4f4;'>
        <table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color: #f4f4f4; padding: 20px;'>
            <tr>
                <td align='center'>
                    <table width='600' border='0' cellspacing='0' cellpadding='0' style='background-color: #ffffff; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 8px rgba(0,0,0,0.1);'>
                        <!-- Header -->
                        <tr>
                            <td style='background: linear-gradient(135deg, #4a90a4 0%, #2c5f73 100%); padding: 30px; text-align: center;'>
                                <h1 style='color: #ffffff; margin: 0; font-size: 28px; font-weight: bold;'>Highcrest Hotel</h1>
                                <p style='color: #ffffff; margin: 5px 0 0 0; font-size: 16px;'>★ ★ ★ ★ ★</p>
                                <p style='color: #ffffff; margin: 10px 0 0 0; font-size: 18px; font-weight: 300;'>The First to Shine</p>
                            </td>
                        </tr>
                        
                        <!-- Content -->
                        <tr>
                            <td style='padding: 40px 30px;'>
                                <h2 style='color: #2c5f73; margin: 0 0 20px 0; font-size: 24px;'>Registration Confirmed!</h2>
                                
                                <p style='color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;'>
                                    Dear <strong>" . htmlspecialchars($name) . "</strong>,
                                </p>
                                
                                <p style='color: #333333; font-size: 16px; line-height: 1.6; margin: 0 0 20px 0;'>
                                    Thank you for registering for our exclusive student discount program! Your registration has been successfully processed.
                                </p>
                                
                                <!-- Voucher Details -->
                                <table width='100%' border='0' cellspacing='0' cellpadding='0' style='background-color: #f8f9fa; border-radius: 8px; margin: 20px 0;'>
                                    <tr>
                                        <td style='padding: 20px;'>
                                            <h3 style='color: #2c5f73; margin: 0 0 15px 0; font-size: 20px;'>Your Voucher Details</h3>
                                            <p style='margin: 8px 0; color: #333333; font-size: 14px;'><strong>Student ID:</strong> " . htmlspecialchars($studentId) . "</p>
                                            <p style='margin: 8px 0; color: #333333; font-size: 14px;'><strong>University:</strong> " . htmlspecialchars($university) . "</p>
                                            <p style='margin: 8px 0; color: #333333; font-size: 14px;'><strong>Discount:</strong> 20% off</p>
                                            <p style='margin: 8px 0; color: #333333; font-size: 14px;'><strong>Valid Until:</strong> " . $expiryDate . "</p>
                                        </td>
                                    </tr>
                                </table>
                                
                                <!-- How to Use -->
                                <h3 style='color: #2c5f73; margin: 20px 0 15px 0; font-size: 18px;'>How to Use Your Voucher</h3>
                                <ol style='color: #333333; font-size: 14px; line-height: 1.6; padding-left: 20px;'>
                                    <li>Show your student ID at time of use</li>
                                    <li>Present this voucher (digital or printed)</li>
                                    <li>Valid for Food & Beverages or SPA Services</li>
                                    <li>One voucher per visit</li>
                                </ol>
                                
                                <p style='color: #666666; font-size: 14px; line-height: 1.6; margin: 20px 0 0 0;'>
                                    We look forward to welcoming you to Highcrest Hotel!
                                </p>
                            </td>
                        </tr>
                        
                        <!-- Footer -->
                        <tr>
                            <td style='background-color: #2c5f73; padding: 20px; text-align: center;'>
                                <p style='color: #ffffff; margin: 0; font-size: 14px;'>
                                    <strong>Highcrest Hotel</strong><br>
                                    Phone: +964 770 818 1336 | +964 770 818 1337<br>
                                 <p style='color: #ffffff;'>www.highcresthotel.com </p>   
                                </p>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
    </body>
    </html>";
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Highcrest Hotel - Student Registration</title>
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4a90a4;
            --secondary-color: #2c5f73;
            --accent-color: #7bb3c0;
            --text-dark: #2c3e50;
            --text-light: #6c757d;
            --bg-light: #f8f9fa;
            --white: #ffffff;
            --success: #28a745;
            --warning: #ffc107;
            --error: #dc3545;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            min-height: 100vh;
            color: var(--text-dark);
            line-height: 1.6;
        }

        .container-fluid {
            padding: 0;
        }

        .main-wrapper {
            min-height: 100vh;
            display: flex;
            align-items: center;
            padding: 20px 0;
        }

        .form-container {
            background: var(--white);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            overflow: hidden;
            max-width: 600px;
            margin: 0 auto;
            position: relative;
        }

        .form-header {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            padding: 40px 30px;
            text-align: center;
            color: var(--white);
            position: relative;
        }

        .form-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="mountains" patternUnits="userSpaceOnUse" width="50" height="20"><polygon points="0,20 25,0 50,20" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23mountains)"/></svg>');
            opacity: 0.3;
        }

        .form-header h1 {
            font-size: 2.5rem;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .form-header .stars {
            color: #ffd700;
            font-size: 1.2rem;
            margin-bottom: 10px;
            position: relative;
            z-index: 1;
        }

        .form-header .tagline {
            font-size: 1.1rem;
            font-weight: 300;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }

        .form-header .discount-badge {
            background: rgba(255,255,255,0.2);
            border: 2px solid rgba(255,255,255,0.3);
            border-radius: 50px;
            padding: 15px 25px;
            margin: 20px 0;
            display: inline-block;
            position: relative;
            z-index: 1;
        }

        .form-header .discount-badge .discount-text {
            font-size: 1.5rem;
            font-weight: 600;
            margin-bottom: 5px;
        }

        .form-header .discount-badge .discount-subtitle {
            font-size: 0.9rem;
            opacity: 0.8;
        }

        .form-body {
            padding: 40px 30px;
        }

        .form-title {
            color: var(--secondary-color);
            font-size: 1.8rem;
            font-weight: 600;
            margin-bottom: 30px;
            text-align: center;
        }

        .form-group {
            margin-bottom: 25px;
        }

        .form-label {
            color: var(--text-dark);
            font-weight: 500;
            margin-bottom: 8px;
            display: block;
            font-size: 0.95rem;
        }

        .form-control {
            border: 2px solid #e1e5e9;
            border-radius: 10px;
            padding: 12px 16px;
            font-size: 1rem;
            transition: all 0.3s ease;
            background: var(--white);
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 3px rgba(74, 144, 164, 0.1);
            outline: none;
        }

        .form-control:invalid:not(:placeholder-shown) {
    border-color: var(--error);
}

.form-control.was-validated:invalid {
    border-color: var(--error);
}

.form-select {
    cursor: pointer;
    background-image: url("data:image/svg+xml;charset=utf-8,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16'%3E%3Cpath fill='none' stroke='%23343a40' stroke-linecap='round' stroke-linejoin='round' stroke-width='2' d='M2 5l6 6 6-6'/%3E%3C/svg%3E");
    background-repeat: no-repeat;
    background-position: right 0.75rem center;
    background-size: 16px 12px;
}
        .input-group {
            position: relative;
        }

        .input-group-text {
            background: var(--bg-light);
            border: 2px solid #e1e5e9;
            border-right: none;
            border-radius: 10px 0 0 10px;
            font-weight: 500;
            color: var(--text-dark);
        }

        .input-group .form-control {
            border-left: none;
            border-radius: 0 10px 10px 0;
        }

        .input-group:focus-within .input-group-text {
            border-color: var(--primary-color);
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--secondary-color) 100%);
            border: none;
            border-radius: 10px;
            padding: 15px 40px;
            font-size: 1.1rem;
            font-weight: 600;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(74, 144, 164, 0.3);
        }

        .alert {
            border: none;
            border-radius: 10px;
            padding: 15px 20px;
            margin-bottom: 25px;
            font-weight: 500;
        }

        .alert-success {
            background: rgba(40, 167, 69, 0.1);
            color: var(--success);
            border-left: 4px solid var(--success);
        }

        .alert-danger {
            background: rgba(220, 53, 69, 0.1);
            color: var(--error);
            border-left: 4px solid var(--error);
        }

        .alert-warning {
            background: rgba(255, 193, 7, 0.1);
            color: var(--warning);
            border-left: 4px solid var(--warning);
        }

        .other-university {
            display: none;
            margin-top: 15px;
        }

        .form-footer {
            background: var(--bg-light);
            padding: 20px 30px;
            text-align: center;
            color: var(--text-light);
            font-size: 0.9rem;
        }

        .validity-info {
            background: rgba(74, 144, 164, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 25px;
            border-left: 4px solid var(--primary-color);
        }

        .validity-info h6 {
            color: var(--secondary-color);
            margin-bottom: 10px;
            font-weight: 600;
        }

        .validity-info ul {
            margin: 0;
            padding-left: 20px;
            color: var(--text-dark);
        }

        .validity-info li {
            margin-bottom: 5px;
        }

        @media (max-width: 768px) {
            .main-wrapper {
                padding: 10px;
            }

            .form-container {
                border-radius: 15px;
                margin: 10px;
            }

            .form-header {
                padding: 30px 20px;
            }

            .form-header h1 {
                font-size: 2rem;
            }

            .form-body {
                padding: 30px 20px;
            }

            .form-title {
                font-size: 1.5rem;
            }

            .btn-primary {
                width: 100%;
                padding: 15px;
            }
        }

        @media (max-width: 576px) {
            .form-header h1 {
                font-size: 1.8rem;
            }

            .form-header .discount-badge {
                padding: 12px 20px;
            }

            .form-header .discount-badge .discount-text {
                font-size: 1.3rem;
            }

            .form-body {
                padding: 25px 15px;
            }

            .form-footer {
                padding: 15px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-wrapper">
            <div class="container">
                <div class="form-container">
                    <div class="form-header">
                        <h1>Highcrest Hotel</h1>
                        <div class="stars">★ ★ ★ ★ ★</div>
                        <div class="tagline">The First to Shine</div>
                        <div class="discount-badge">
                            <div class="discount-text">Enjoy 20% off</div>
                            <div class="discount-subtitle">University Student Discount</div>
                        </div>
                    </div>

                    <div class="form-body">
                        <h2 class="form-title">Student Registration</h2>
                        
                        <?php if ($message): ?>
                            <div class="alert alert-<?php echo $messageType === 'success' ? 'success' : ($messageType === 'warning' ? 'warning' : 'danger'); ?>">
                                <?php echo $message; ?>
                            </div>
                        <?php endif; ?>

                        <div class="validity-info">
                            <h6><i class="fas fa-info-circle"></i> Voucher Information</h6>
                            <ul>
                                <li>Show your student ID at time of use</li>
                                <li>Valid for Food & Beverages or SPA Services</li>
                                <li>One voucher per visit</li>
                                <li>Valid until: April 1, 2026</li>
                            </ul>
                        </div>

                        <form method="POST" action="" novalidate>
                           <!-- Student ID Input -->
<div class="form-group">
    <label for="student_id" class="form-label">
        <i class="fas fa-id-card"></i> Student ID
    </label>
    <input type="text" 
           class="form-control" 
           id="student_id" 
           name="student_id" 
           required 
           placeholder="Enter your student ID"
           value="<?php echo htmlspecialchars($studentId); ?>">
</div>

<!-- Full Name Input -->
<div class="form-group">
    <label for="name" class="form-label">
        <i class="fas fa-user"></i> Full Name (as in passport)
    </label>
    <input type="text" 
           class="form-control" 
           id="name" 
           name="name" 
           required 
           placeholder="Enter your full name"
           value="<?php echo htmlspecialchars($name); ?>">
</div>

<!-- University Select -->
<div class="form-group">
    <label for="university" class="form-label">
        <i class="fas fa-university"></i> University
    </label>
    <select class="form-control form-select" 
            id="university" 
            name="university" 
            required 
            onchange="toggleOtherUniversity(this)">
        <option value="">Select University</option>
        <!-- Public Universities -->
        <option value="Charmo University" <?php echo ($university === 'Charmo University') ? 'selected' : ''; ?>>Charmo University</option>
        <option value="Duhok Polytechnic University" <?php echo ($university === 'Duhok Polytechnic University') ? 'selected' : ''; ?>>Duhok Polytechnic University</option>
        <option value="Erbil Polytechnic University" <?php echo ($university === 'Erbil Polytechnic University') ? 'selected' : ''; ?>>Erbil Polytechnic University</option>
        <option value="Hawler Medical University" <?php echo ($university === 'Hawler Medical University') ? 'selected' : ''; ?>>Hawler Medical University</option>   
        <option value="Koya University" <?php echo ($university === 'Koya University') ? 'selected' : ''; ?>>Koya University</option>
        <option value="Salahaddin University - Erbil" <?php echo ($university === 'Salahaddin University - Erbil') ? 'selected' : ''; ?>>Salahaddin University - Erbil</option>
        <option value="Soran University" <?php echo ($university === 'Soran University') ? 'selected' : ''; ?>>Soran University</option>
        <option value="Sulaimani Polytechnic University" <?php echo ($university === 'Sulaimani Polytechnic University') ? 'selected' : ''; ?>>Sulaimani Polytechnic University</option>
        <option value="University of Duhok" <?php echo ($university === 'University of Duhok') ? 'selected' : ''; ?>>University of Duhok</option>
        <option value="University of Halabja" <?php echo ($university === 'University of Halabja') ? 'selected' : ''; ?>>University of Halabja</option>
        <option value="University of Garmian" <?php echo ($university === 'University of Garmian') ? 'selected' : ''; ?>>University of Garmian</option>
        <option value="University of Kurdistan Hewler" <?php echo ($university === 'University of Kurdistan Hewler') ? 'selected' : ''; ?>>University of Kurdistan Hewler</option>
        <option value="University of Raparin" <?php echo ($university === 'University of Raparin') ? 'selected' : ''; ?>>University of Raparin</option>
        <option value="University of Sulaimani" <?php echo ($university === 'University of Sulaimani') ? 'selected' : ''; ?>>University of Sulaimani</option>
        <option value="University of Zakho" <?php echo ($university === 'University of Zakho') ? 'selected' : ''; ?>>University of Zakho</option>
        <!-- Private Universities -->
        <option value="American university of Kurdistan" <?php echo ($university === 'American university of Kurdistan') ? 'selected' : ''; ?>>American university of Kurdistan</option>
        <option value="American University of Iraq Sulaimani" <?php echo ($university === 'American University of Iraq Sulaimani') ? 'selected' : ''; ?>>American University of Iraq Sulaimani</option>
        <option value="Bayan University" <?php echo ($university === 'Bayan University') ? 'selected' : ''; ?>>Bayan University</option>
        <option value="Catholic University in Erbil" <?php echo ($university === 'Catholic University in Erbil') ? 'selected' : ''; ?>>Catholic University in Erbil</option>
        <option value="Cihan University - Duhok" <?php echo ($university === 'Cihan University - Duhok') ? 'selected' : ''; ?>>Cihan University - Duhok</option>
        <option value="Cihan University - Sulaimaniya" <?php echo ($university === 'Cihan University - Sulaimaniya') ? 'selected' : ''; ?>>Cihan University - Sulaimaniya</option>
        <option value="Internation University of Erbil" <?php echo ($university === 'Internation University of Erbil') ? 'selected' : ''; ?>>Internation University of Erbil</option>
        <option value="Knowledge University" <?php echo ($university === 'Knowledge University') ? 'selected' : ''; ?>>Knowledge University</option>
        <option value="University of Human Development" <?php echo ($university === 'University of Human Development') ? 'selected' : ''; ?>>University of Human Development</option>
        <option value="Cihan University-Erbil" <?php echo ($university === 'Cihan University-Erbil') ? 'selected' : ''; ?>>Cihan University-Erbil</option>
        <option value="Lebanese French University" <?php echo ($university === 'Lebanese French University') ? 'selected' : ''; ?>>Lebanese French University</option>
        <option value="Nawroz University" <?php echo ($university === 'Nawroz University') ? 'selected' : ''; ?>>Nawroz University</option>
        <option value="Tishk International University" <?php echo ($university === 'Tishk International University') ? 'selected' : ''; ?>>Tishk International University</option>
        <option value="Komar University of Science and Technology" <?php echo ($university === 'Komar University of Science and Technology') ? 'selected' : ''; ?>>Komar University of Science and Technology</option>
        <option value="University College of Goizha" <?php echo ($university === 'University College of Goizha') ? 'selected' : ''; ?>>University College of Goizha</option>
        <option value="Other" <?php echo ($university === 'Other') ? 'selected' : ''; ?>>Other (please specify)</option>
    </select>
    
    <div class="other-university" id="other_university_div" style="display: <?php echo ($university === 'Other') ? 'block' : 'none'; ?>;">
        <label for="other_university" class="form-label">
            <i class="fas fa-edit"></i> Please specify your university
        </label>
        <input type="text" 
               class="form-control" 
               id="other_university" 
               name="other_university" 
               placeholder="Enter your university name"
               value="<?php echo htmlspecialchars($otherUniversity); ?>"
               <?php echo ($university === 'Other') ? 'required' : ''; ?>>
    </div>
</div>

<!-- Email Input -->
<div class="form-group">
    <label for="email" class="form-label">
        <i class="fas fa-envelope"></i> Email Address
    </label>
    <input type="email" 
           class="form-control" 
           id="email" 
           name="email" 
           required 
           placeholder="Enter your email address"
           value="<?php echo htmlspecialchars($email); ?>">
</div>

<!-- Phone Input -->
<div class="form-group">
    <label for="phone" class="form-label">
        <i class="fas fa-phone"></i> Phone Number
    </label>
    <div class="input-group">
        <span class="input-group-text">+964</span>
        <input type="tel" 
               class="form-control" 
               id="phone" 
               name="phone" 
               required 
               pattern="[0-9]{10}" 
               placeholder="7701234567"
               maxlength="10"
               value="<?php echo htmlspecialchars($phone); ?>">
    </div>
    <small class="text-muted">Enter 10 digits after +964</small>
</div>

<!-- Academic Year Select -->
<div class="form-group">
    <label for="academic_year" class="form-label">
        <i class="fas fa-graduation-cap"></i> Academic Year
    </label>
    <select class="form-control form-select" 
            id="academic_year" 
            name="academic_year" 
            required>
        <option value="">Select Academic Year</option>
        <option value="Freshman" <?php echo ($academicYear === 'Freshman') ? 'selected' : ''; ?>>Freshman (1st Year)</option>
        <option value="Sophomore" <?php echo ($academicYear === 'Sophomore') ? 'selected' : ''; ?>>Sophomore (2nd Year)</option>
        <option value="Junior" <?php echo ($academicYear === 'Junior') ? 'selected' : ''; ?>>Junior (3rd Year)</option>
        <option value="Senior" <?php echo ($academicYear === 'Senior') ? 'selected' : ''; ?>>Senior (4th Year)</option>
        <option value="Graduate Student" <?php echo ($academicYear === 'Graduate Student') ? 'selected' : ''; ?>>Graduate Student</option>
    </select>
</div>

                            <div class="form-group">
                                <button type="submit" class="btn btn-primary w-100">
                                    <i class="fas fa-paper-plane"></i> Submit Registration
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="form-footer">
                        <p>
                            <i class="fas fa-shield-alt"></i> Your information is secure and will only be used for voucher verification.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap/5.3.0/js/bootstrap.bundle.min.js"></script>
    <script>
        function toggleOtherUniversity(select) {
            const otherDiv = document.getElementById('other_university_div');
            const otherInput = document.getElementById('other_university');
            
            if (select.value === 'Other') {
                otherDiv.style.display = 'block';
                otherInput.required = true;
            } else {
                otherDiv.style.display = 'none';
                otherInput.required = false;
                otherInput.value = '';
            }
        }

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length > 10) {
                value = value.substring(0, 10);
            }
            e.target.value = value;
        });

        // Form validation
        document.querySelector('form').addEventListener('submit', function(e) {
            const phone = document.getElementById('phone').value;
            const phonePattern = /^[0-9]{10}$/;
            
            if (!phonePattern.test(phone)) {
                e.preventDefault();
                alert('Please enter a valid 10-digit phone number');
                return false;
            }
        });

        // Auto-hide alerts after 10 seconds
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(function() {
                    alert.style.display = 'none';
                }, 500);
            });
        }, 10000);
    </script>
</body>
</html>