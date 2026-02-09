<?php
require_once 'vendor/autoload.php';
require_once 'db.php';
session_start();

// Google Client config
$client = new Google_Client();
$client->setClientId('328276873849-3ar6cf59iik4lk0vp61e3oaqidsnq7v9.apps.googleusercontent.com');
$client->setClientSecret('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');
$client->setRedirectUri('http://localhost/Testing/callback.php');
$client->addScope('email');
$client->addScope('profile');

if (isset($_GET['code'])) {
    $token = $client->fetchAccessTokenWithAuthCode($_GET['code']);
    if (isset($token['error'])) {
        exit('Error fetching access token: ' . htmlspecialchars($token['error']));
    }

    $client->setAccessToken($token['access_token']);
    $oauth2 = new Google\Service\Oauth2($client);
    $userinfo = $oauth2->userinfo->get();

    $email = $conn->real_escape_string($userinfo->email);

    // Check user in DB
    $query = "SELECT * FROM users WHERE email = '$email' LIMIT 1";
    $result = $conn->query($query);

    if ($result && $result->num_rows === 1) {
        $user = $result->fetch_assoc();

        $_SESSION['user_info'] = [
            'id' => $user['id'],
            'email' => $user['email'],
            'name' => $user['name'],
            'role' => $user['role'],
            'dept_id' => $user['dept_id']
        ];

        // Redirect to dashboard instead of directly to department page
        if ($result && $result->num_rows === 1) {
        }
        header($_SESSION['user_info']['dept_id'] == 1 && $_SESSION['user_info']['role'] == 'LEAD_MIS' ? "Location: Main_dashboard.php" : "Location: dept_dashboard.php");
        exit;
    } else {
        echo "<script>alert('Access denied. Your account is not authorized.'); window.location.href = 'login.php';</script>";
        exit;
    }
}
