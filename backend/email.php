<?php

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
    
    private function setupMailer() {
        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config['smtp_host'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $this->config['smtp_username'];
        $this->mailer->Password = $this->config['smtp_password'];
        $this->mailer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $this->mailer->Port = 465;
        
        $this->mailer->SMTPOptions = [
            'ssl' => [
                'verify_peer' => false,
                'verify_peer_name' => false,
                'allow_self_signed' => true,
            ],
        ];
        
        $this->mailer->setFrom($this->config['from_email'], $this->config['from_name']);
        $this->mailer->isHTML(true);
    }
    
    public function sendVerificationEmail($toEmail, $toName, $verificationToken) {
        try {
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($toEmail, $toName);
            
            $verificationUrl = $this->getBaseUrl() . '/backend/verify-email.php?token=' . urlencode($verificationToken);

            
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
<html lang="en" xmlns="http://www.w3.org/1999/xhtml" xmlns:v="urn:schemas-microsoft-com:vml" xmlns:o="urn:schemas-microsoft-com:office:office">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="x-apple-disable-message-reformatting">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>Email Verification - SK Estate</title>
    <!--[if mso]>
    <xml>
        <o:OfficeDocumentSettings>
            <o:PixelsPerInch>96</o:PixelsPerInch>
        </o:OfficeDocumentSettings>
    </xml>
    <![endif]-->
    <style>
        :root {
            color-scheme: light dark;
        }
        
        * {
            box-sizing: border-box;
        }
        
        body {
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Arial, sans-serif;
            font-size: 16px;
            line-height: 1.5;
            color: #333333;
            background-color: #f8f9fa;
            -webkit-text-size-adjust: 100%;
            -ms-text-size-adjust: 100%;
        }
        
        table {
            border-spacing: 0;
            border-collapse: collapse;
            width: 100%;
        }
        
        td {
            padding: 0;
            vertical-align: top;
        }
        
        img {
            border: 0;
            line-height: 100%;
            outline: none;
            text-decoration: none;
            -ms-interpolation-mode: bicubic;
        }
        
        .email-wrapper {
            width: 100%;
            background-color: #f8f9fa;
            padding: 20px 0;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            overflow: hidden;
        }
        
        .header {
            background-color: #ffffff;
            padding: 32px 24px;
            text-align: center;
            border-bottom: 1px solid #e9ecef;
        }
        
        .logo-img {
            display: block;
            margin: 0 auto 16px auto;
         
            height: auto;
              width:150px !important;
             height:100% !important;
        }
        
        .logo-img-light {
            display: none;
        }
        
        .company-name {
            font-size: 20px;
            font-weight: 600;
            color: #212529;
            margin: 0 0 4px 0;
        }
        
        .company-subtitle {
            font-size: 14px;
            color: #6c757d;
            margin: 0;
        }
        
        .content {
            padding: 32px 24px;
        }
        
        .title {
            font-size: 18px;
            font-weight: 600;
            color: #212529;
            margin: 0 0 16px 0;
            text-align: center;
        }
        
        .text {
            font-size: 16px;
            line-height: 1.5;
            color: #495057;
            margin: 0 0 16px 0;
        }
        
        .button-container {
            text-align: center;
            margin: 24px 0;
        }
        
        .button {
            display: inline-block;
            background-color: #000000;
            color: #ffffff;
            padding: 12px 24px;
            text-decoration: none;
            border-radius: 4px;
            font-size: 16px;
            font-weight: 500;
            border: 1px solid #000000;
            text-align: center;
            min-width: 200px;
        }
        
        .link-container {
            background-color: #f8f9fa;
            border: 1px solid #e9ecef;
            border-radius: 4px;
            padding: 16px;
            margin: 16px 0;
            word-break: break-all;
            font-size: 14px;
            color: #6c757d;
        }
        
        .notice {
            background-color: #f8f9fa;
            border-left: 3px solid #000000;
            padding: 16px;
            margin: 24px 0;
            font-size: 14px;
            color: #495057;
        }
        
        .footer {
            background-color: #f8f9fa;
            padding: 24px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .footer-text {
            font-size: 12px;
            color: #6c757d;
            margin: 0 0 8px 0;
        }
        
        .footer-text:last-child {
            margin-bottom: 0;
        }
        
        /* Dark mode support */
        @media (prefers-color-scheme: dark) {
            .email-wrapper {
                background-color: #1a1a1a !important;
            }
            
            .email-container {
                background-color: #2d2d2d !important;
                border-color: #404040 !important;
            }
            
            .header {
                background-color: #2d2d2d !important;
                border-bottom-color: #404040 !important;
            }
            
            .logo-img {
                display: none !important;
            }
            
            .logo-img-light {
                display: block !important;
                margin: 0 auto 16px auto !important;
                max-width: 120px !important;
                height: auto !important;
            }
            
            .company-name {
                color: #ffffff !important;
            }
            
            .company-subtitle {
                color: #b3b3b3 !important;
            }
            
            .content {
                background-color: #2d2d2d !important;
            }
            
            .title {
                color: #ffffff !important;
            }
            
            .text {
                color: #cccccc !important;
            }
            
            .button {
                background-color: #ffffff !important;
                color: #000000 !important;
                border-color: #ffffff !important;
            }
            
            .link-container {
                background-color: #404040 !important;
                border-color: #595959 !important;
                color: #b3b3b3 !important;
            }
            
            .notice {
                background-color: #404040 !important;
                color: #cccccc !important;
                border-left-color: #ffffff !important;
            }
            
            .footer {
                background-color: #404040 !important;
                border-top-color: #595959 !important;
            }
            
            .footer-text {
                color: #b3b3b3 !important;
            }
        }
        
        /* Mobile responsiveness */
        @media screen and (max-width: 600px) {
            .email-wrapper {
                padding: 12px !important;
            }
            
            .email-container {
                border-radius: 4px !important;
            }
            
            .header,
            .content,
            .footer {
                padding: 24px 20px !important;
            }
            
            .title {
                font-size: 16px !important;
            }
            
            .text {
                font-size: 14px !important;
            }
            
            .button {
                padding: 14px 20px !important;
                font-size: 14px !important;
                min-width: 160px !important;
            }
        }
        
        /* Outlook specific */
        <!--[if mso]>
        .email-container {
            width: 600px !important;
        }
        
        .button {
            mso-style-priority: 100 !important;
        }
        <![endif]-->
    </style>
</head>
<body>


    <div class="email-wrapper">
        <table role="presentation" class="email-container">
            <tr>
                <td>
                 <div class="header">
   <a href="https://ske.southkurdistan.com" target="_blank">
        <img src="' . $this->getBaseUrl() . '/../assets/logo/ske-dark.png"
             alt="SKE Logo"
             width="150px"
             height="100%"
             style="display: block; margin: 0 auto;"
             class="logo-img">
    </a>
    <h1 class="company-name">SK Estate</h1>
    <p class="company-subtitle">Performance Management System</p>
</div>






                    
                    <div class="content">
                        <h2 class="title">Welcome, ' . htmlspecialchars($userName) . '</h2>
                        
                        <p class="text">
                            Thank you for registering with SKE Performance Management System. 
                            Please verify your email address to complete your registration.
                        </p>
                        
                        <div class="button-container">
                            <a href="' . $verificationUrl . '" class="button" target="_blank">
                                Verify Email Address
                            </a>
                        </div>
                        
                        <p class="text">
                            If the button doesn\'t work, copy and paste this link into your browser:
                        </p>
                        
                        <div class="link-container">
                            ' . $verificationUrl . '
                        </div>
                        
                        <div class="notice">
                            <strong>Security Notice:</strong> This link expires in 24 hours. 
                            If you didn\'t create this account, please ignore this email.
                        </div>
                        
                        <p class="text">
                            Best regards,<br>
                            SKE HR Team
                        </p>
                    </div>
                    
                    <div class="footer">
                        <p class="footer-text">
                            &copy; ' . date('Y') . ' SKE. All rights reserved.
                        </p>
                        <p class="footer-text">
                            This is an automated message. Please do not reply.
                        </p>
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

function generateVerificationToken() {
    return bin2hex(random_bytes(32));
}