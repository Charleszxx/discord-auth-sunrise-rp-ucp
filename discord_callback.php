<?php
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

$client_id = "1236520127869227131";
$client_secret = "CpnLABAjtIjaziGu2nlBelLMB17XTD2c";
$redirect_uri = "https://<your-render-url>/discord_callback.php"; // change later

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    $data = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri,
        'scope' => 'identify'
    ];

    $ch = curl_init('https://discord.com/api/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);
    if (!isset($tokenData['access_token'])) {
        echo "Failed to get access token.<br><pre>" . htmlspecialchars($response) . "</pre>";
        exit;
    }

    $access_token = $tokenData['access_token'];

    $ch = curl_init('https://discord.com/api/users/@me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
    $userResponse = curl_exec($ch);
    curl_close($ch);

    $userData = json_decode($userResponse, true);
    if (!isset($userData['id'])) {
        echo "Failed to fetch user data.<br><pre>" . htmlspecialchars($userResponse) . "</pre>";
        exit;
    }

    echo "Discord ID: " . htmlspecialchars($userData['id']);
} else {
    echo "No code provided.";
}
