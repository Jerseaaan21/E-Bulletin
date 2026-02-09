<?php
require_once 'vendor/autoload.php';
session_start();

// Check if user is already logged in
if (isset($_SESSION['user_info']['name'])) {
    header('Location: dept_dashboard.php');
    exit();
}

// Check if guest access is requested
if (isset($_GET['guest']) && $_GET['guest'] == 'true') {
    $_SESSION['guest'] = true;
    header('Location: viewer_dashboard.php');
    exit();
}

$client = new Google_Client();
$client->setClientId('328276873849-3ar6cf59iik4lk0vp61e3oaqidsnq7v9.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/Testing/callback.php');
$client->addScope('email');
$client->addScope('profile');

$login_url = $client->createAuthUrl();
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Login with CvSU Account</title>
    <link rel="stylesheet" href="style-login.css" />
    <link href="https://fonts.googleapis.com/css2?family=Roboto:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Roboto', sans-serif;
            background: linear-gradient(135deg, #e7621f, #160700);
            color: #fff;
            height: 100vh;
            overflow: hidden;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        .login-container {
            display: flex;
            width: 100%;
            max-width: 1200px;
            height: 90vh;
            max-height: 700px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }

        .logo-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            padding: 40px;
            background: rgba(0, 0, 0, 0.2);
            position: relative;
        }

        .logo-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('loginbackground.png') no-repeat center center;
            background-size: cover;
            opacity: 0.3;
            z-index: -1;
        }

        .logo {
            width: 80%;
            max-width: 300px;
            margin-bottom: 30px;
            animation: float 3s ease-in-out infinite;
        }

        @keyframes float {
            0% {
                transform: translateY(0px);
            }

            50% {
                transform: translateY(-10px);
            }

            100% {
                transform: translateY(0px);
            }
        }

        .university-name {
            font-size: 28px;
            font-weight: 700;
            text-align: center;
            margin-bottom: 10px;
            text-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
        }

        .tagline {
            font-size: 16px;
            font-weight: 300;
            text-align: center;
            opacity: 0.9;
        }

        .form-section {
            flex: 1;
            display: flex;
            flex-direction: column;
            justify-content: center;
            padding: 40px;
        }

        .form-container {
            max-width: 400px;
            margin: 0 auto;
        }

        .form-header {
            margin-bottom: 30px;
        }

        .form-header h2 {
            font-size: 32px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #fff;
        }

        .form-header p {
            font-size: 16px;
            opacity: 0.8;
            line-height: 1.5;
        }

        .google-btn {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: #fff;
            color: #444;
            border: none;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            margin-top: 20px;
        }

        .google-btn:hover {
            background-color: #f8f9fa;
            transform: translateY(-2px);
            box-shadow: 0 6px 12px rgba(0, 0, 0, 0.15);
        }

        .google-btn img {
            width: 20px;
            margin-right: 12px;
        }

        .guest-btn {
            display: flex;
            justify-content: center;
            align-items: center;
            background-color: rgba(255, 255, 255, 0.2);
            color: #fff;
            border: 2px solid #fff;
            padding: 14px 20px;
            border-radius: 8px;
            font-size: 16px;
            cursor: pointer;
            text-decoration: none;
            font-weight: 500;
            transition: all 0.3s ease;
            margin-top: 15px;
        }

        .guest-btn:hover {
            background-color: rgba(255, 255, 255, 0.3);
            transform: translateY(-2px);
        }

        .divider {
            display: flex;
            align-items: center;
            margin: 20px 0;
        }

        .divider-line {
            flex-grow: 1;
            height: 1px;
            background-color: rgba(255, 255, 255, 0.3);
        }

        .divider-text {
            margin: 0 15px;
            font-size: 14px;
            opacity: 0.7;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 14px;
            opacity: 0.7;
        }

        @media (max-width: 768px) {
            .login-container {
                flex-direction: column;
                height: 100vh;
                max-height: 100vh;
                border-radius: 0;
            }

            .logo-section {
                padding: 30px 20px;
            }

            .logo {
                width: 70%;
                max-width: 200px;
            }

            .university-name {
                font-size: 24px;
            }

            .form-section {
                padding: 30px 20px;
            }

            .form-header h2 {
                font-size: 28px;
            }
        }
    </style>
</head>

<body>
    <div class="login-container">
        <div class="logo-section">
            <img src="cvsulogo.png" alt="CvSU Logo" class="logo">
            <h1 class="university-name">Cavite State University</h1>
            <p class="tagline">Truth, Integrity, Excellence, and Service</p>
        </div>
        <div class="form-section">
            <div class="form-container">
                <div class="form-header">
                    <h2>Welcome to E-Bulletin</h2>
                    <p>Log in with your CvSU account to access the E-Bulletin system. Only CvSU emails (e.g., someone@cvsu.edu.ph) are allowed.</p>
                </div>
                <a class="google-btn" href="<?= htmlspecialchars($login_url) ?>">
                    <img src="https://developers.google.com/identity/images/g-logo.png" alt="Google logo" />
                    Sign in as MIS Officer
                </a>

                <div class="divider">
                    <div class="divider-line"></div>
                    <div class="divider-text">OR</div>
                    <div class="divider-line"></div>
                </div>

                <a class="guest-btn" href="?guest=true">
                    <i class="fas fa-eye" style="margin-right: 12px;"></i>
                    Continue as CEIT E-Bulletin Viewer
                </a>

                <div class="footer">
                    <p>&copy; <?php echo date('Y'); ?> CvSU E-Bulletin System. All rights reserved.</p>
                </div>
            </div>
        </div>
    </div>
</body>

</html>