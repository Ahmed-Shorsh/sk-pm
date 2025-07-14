<?php
/**
 * Email Service for SK-PM
 * Handles sending verification emails and other notifications
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

class EmailService {
    private $mailer;
    private $config;
    
    public function __construct() {
        $this->config = [
            'smtp_host' => 'mail.southkurdistan.com',
            'smtp_port' => 465,
            'smtp_username' => 'noreply@southkurdistan.com', 
            'smtp_password' => 'c286^2[$^63q',
            'from_email' => 'noreply@southkurdistan.com',
            'from_name' => 'SK Estate Performance Management'
        ];
        
        $this->mailer = new PHPMailer(true);
        $this->setupMailer();
    }
    
    // private function setupMailer() {
    //     $this->mailer->isSMTP();
    //     $this->mailer->Host = $this->config['smtp_host'];
    //     $this->mailer->SMTPAuth = true;
    //     $this->mailer->Username = $this->config['smtp_username'];
    //     $this->mailer->Password = $this->config['smtp_password'];
    //     $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
    //     $this->mailer->Port = $this->config['smtp_port'];
        
    //     $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
    //     $this->mailer->isHTML(true);
    // }
    private function setupMailer() {
        $this->mailer->isSMTP();
        $this->mailer->Host       = $this->config['smtp_host'];
        $this->mailer->SMTPAuth   = true;
        $this->mailer->Username   = $this->config['smtp_username'];
        $this->mailer->Password   = $this->config['smtp_password'];
        // OPTION A: Implicit SSL on 465
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $this->mailer->Port       = 465;
    
        // OPTION B: STARTTLS on 587
        // $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        // $this->mailer->Port       = 587;
    
        // in case your server uses a self-signed cert:
        $this->mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer'      => false,
                'verify_peer_name' => false,
                'allow_self_signed'=> true,
            ],
        ];
    
        $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
        $this->mailer->isHTML(true);
    }
    
    
    public function sendVerificationEmail($toEmail, $toName, $verificationToken) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($toEmail, $toName);
            
            $verificationUrl = $this->getBaseUrl() . '/verify-email.php?token=' . urlencode($verificationToken);
            
            $this->mailer->Subject = 'Verify Your Email - SK Estate Performance Management';
            $this->mailer->Body = $this->getVerificationEmailTemplate($toName, $verificationUrl);
            
            return $this->mailer->send();
        } catch (Exception $e) {
            error_log("Email sending failed: " . $e->getMessage());
            return false;
        }
    }
    
    private function getVerificationEmailTemplate($userName, $verificationUrl) {
        return '
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Email Verification - SK Estate</title>
            <!--[if mso]>
            <noscript>
                <xml>
                    <o:OfficeDocumentSettings>
                        <o:PixelsPerInch>96</o:PixelsPerInch>
                    </o:OfficeDocumentSettings>
                </xml>
            </noscript>
            <![endif]-->
            <style type="text/css">
                /* Reset styles for better email client compatibility */
                body, table, td, p, a, li, blockquote {
                    -webkit-text-size-adjust: 100%;
                    -ms-text-size-adjust: 100%;
                }
                table, td {
                    mso-table-lspace: 0pt;
                    mso-table-rspace: 0pt;
                }
                img {
                    -ms-interpolation-mode: bicubic;
                    border: 0;
                    height: auto;
                    line-height: 100%;
                    outline: none;
                    text-decoration: none;
                }
                
                /* Main styles */
                body {
                    margin: 0 !important;
                    padding: 0 !important;
                    background-color: #f5f5f5;
                    font-family: Arial, sans-serif;
                    font-size: 16px;
                    line-height: 1.6;
                    color: #333333;
                }
                
                .email-container {
                    max-width: 600px;
                    margin: 0 auto;
                    background-color: #ffffff;
                }
                
                .header-section {
                    background-color: #000000;
                    padding: 40px 30px;
                    text-align: center;
                }
                
                .logo-container {
                    margin-bottom: 20px;
                }
                
                .logo-link {
                    display: inline-block;
                    text-decoration: none;
                }
                
                .logo-circle {
                    width: 80px;
                    height: 80px;
                    background-color: #ffffff;
                    border-radius: 50%;
                    display: inline-flex;
                    align-items: center;
                    justify-content: center;
                    font-size: 28px;
                    font-weight: bold;
                    color: #000000;
                    text-decoration: none;
                    margin: 0 auto;
                }
                
                .company-name {
                    color: #ffffff;
                    font-size: 32px;
                    font-weight: bold;
                    margin: 15px 0 5px 0;
                    letter-spacing: 1px;
                }
                
                .company-subtitle {
                    color: #cccccc;
                    font-size: 16px;
                    margin: 0;
                    font-weight: normal;
                }
                
                .content-section {
                    padding: 40px 30px;
                    background-color: #ffffff;
                }
                
                .welcome-title {
                    font-size: 24px;
                    font-weight: bold;
                    color: #000000;
                    margin: 0 0 20px 0;
                    text-align: center;
                }
                
                .content-text {
                    font-size: 16px;
                    line-height: 1.6;
                    color: #333333;
                    margin: 0 0 20px 0;
                }
                
                .button-container {
                    text-align: center;
                    margin: 30px 0;
                }
                
                .verify-button {
                    display: inline-block;
                    background-color: #000000;
                    color: #ffffff !important;
                    padding: 16px 32px;
                    text-decoration: none;
                    border-radius: 4px;
                    font-size: 16px;
                    font-weight: bold;
                    border: 2px solid #000000;
                    transition: all 0.3s ease;
                }
                
                .verify-button:hover {
                    background-color: #333333;
                    border-color: #333333;
                }
                
                .url-container {
                    background-color: #f8f8f8;
                    border: 1px solid #e0e0e0;
                    border-radius: 4px;
                    padding: 15px;
                    margin: 20px 0;
                    word-break: break-all;
                    font-size: 14px;
                    color: #666666;
                }
                
                .important-notice {
                    background-color: #f0f0f0;
                    border-left: 4px solid #000000;
                    padding: 15px;
                    margin: 25px 0;
                    font-size: 14px;
                }
                
                .signature {
                    margin-top: 30px;
                    padding-top: 20px;
                    border-top: 1px solid #e0e0e0;
                }
                
                .footer-section {
                    background-color: #f8f8f8;
                    padding: 25px 30px;
                    text-align: center;
                    border-top: 1px solid #e0e0e0;
                }
                
                .footer-text {
                    font-size: 12px;
                    color: #666666;
                    margin: 5px 0;
                    line-height: 1.4;
                }
                
                /* Responsive styles */
                @media screen and (max-width: 600px) {
                    .email-container {
                        width: 100% !important;
                        max-width: 100% !important;
                    }
                    
                    .header-section,
                    .content-section,
                    .footer-section {
                        padding-left: 20px !important;
                        padding-right: 20px !important;
                    }
                    
                    .company-name {
                        font-size: 28px !important;
                    }
                    
                    .welcome-title {
                        font-size: 20px !important;
                    }
                    
                    .verify-button {
                        padding: 14px 24px !important;
                        font-size: 14px !important;
                    }
                }
                
                /* Outlook specific fixes */
                <!--[if mso]>
                .verify-button {
                    border: none !important;
                    mso-style-priority: 99;
                }
                <![endif]-->
            </style>
        </head>
        <body>
            <div style="background-color: #f5f5f5; padding: 20px 0;">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    <tr>
                        <td>
                            <div class="email-container">
                                <!-- Header Section -->
                                <div class="header-section">
                                    <div class="logo-container">
                                        <a href="https://skestate.com" class="logo-link" target="_blank">
                                            <div class="logo-circle">SK</div>
                                        </a>
                                    </div>
                                    <h1 class="company-name">SK Estate</h1>
                                    <p class="company-subtitle">Performance Management System</p>
                                </div>
                                
                                <!-- Content Section -->
                                <div class="content-section">
                                    <h2 class="welcome-title">Welcome to SK Estate, ' . htmlspecialchars($userName) . '!</h2>
                                    
                                    <p class="content-text">
                                        Thank you for registering with our Performance Management System. We\'re excited to have you join our team and look forward to supporting your professional growth.
                                    </p>
                                    
                                    <p class="content-text">
                                        To complete your registration and activate your account, please verify your email address by clicking the button below:
                                    </p>
                                    
                                    <div class="button-container">
                                        <a href="' . $verificationUrl . '" class="verify-button" target="_blank">
                                            Verify Your Email Address
                                        </a>
                                    </div>
                                    
                                    <p class="content-text">
                                        If the button above doesn\'t work, you can copy and paste the following link into your browser:
                                    </p>
                                    
                                    <div class="url-container">
                                        ' . $verificationUrl . '
                                    </div>
                                    
                                    <div class="important-notice">
                                        <strong>Important Security Notice:</strong><br>
                                        This verification link will expire in 24 hours for your account security. If you didn\'t create an account with SK Estate, please ignore this email and no further action is required.
                                    </div>
                                    
                                    <div class="signature">
                                        <p class="content-text">
                                            <strong>Best regards,</strong><br>
                                            SK Estate Human Resources Team<br>
                                            Performance Management System
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Footer Section -->
                                <div class="footer-section">
                                    <p class="footer-text">
                                        &copy; ' . date('Y') . ' SK Estate. All rights reserved.
                                    </p>
                                    <p class="footer-text">
                                        This is an automated message. Please do not reply to this email.
                                    </p>
                                    <p class="footer-text">
                                        If you need assistance, please contact our support team through the official channels.
                                    </p>
                                </div>
                            </div>
                        </td>
                    </tr>
                </table>
            </div>
        </body>
        </html>';
    }
    
    private function getBaseUrl() {
        $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $path = dirname($_SERVER['SCRIPT_NAME'] ?? '');
        return $protocol . '://' . $host . $path;
    }
}

// Helper function to generate secure verification token
function generateVerificationToken() {
    return bin2hex(random_bytes(32));
}