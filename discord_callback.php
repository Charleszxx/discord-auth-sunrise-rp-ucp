<?php
require('config.php'); // Move this to the top so $con is defined
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$client_id = "1360930351572193442";
$client_secret = "aRgjpb3b9JR-dD2PfkH2W4BpL5T1wTQI";
$redirect_uri = "https://discord-auth-sunrise-rp-ucp.onrender.com/discord_callback.php";

$state = $_GET['state'] ?? null;
if (!$state) {
    die("Missing UID (state).");
}

if (!isset($_GET['code'])) {
    die("No authorization code received from Discord.");
}

$code = $_GET['code'];

// Exchange code for access token
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
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error_msg = curl_error($ch);
curl_close($ch);

$tokenData = json_decode($response, true);

if (!isset($tokenData['access_token'])) {
    die("Failed to obtain access token from Discord. HTTP code: $http_code<br>Error: $error_msg<br>Response: $response");
}

$access_token = $tokenData['access_token'];

// Fetch user info
$ch = curl_init('https://discord.com/api/users/@me');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
$userResponse = curl_exec($ch);
curl_close($ch);

$userData = json_decode($userResponse, true);

if (!isset($userData['id'])) {
    die("Failed to fetch user info from Discord. Raw response: $userResponse");
}

$discord_user_id = $userData['id'];

// Save to DB
require('config.php');

try {
    $stmt = $conn->prepare("UPDATE users SET discord_userid = ? WHERE uid = ?");
    $stmt->bind_param("ss", $discord_user_id, $state);
    $stmt->execute();
    echo "<script>alert('Discord linked successfully!'); window.location.href='https://sunriserp-ucp.byethost15.com/pages/dashboard.php';</script>";
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
?>
