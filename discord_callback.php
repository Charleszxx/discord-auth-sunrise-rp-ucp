<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Discord app credentials
$client_id = "1236520127869227131";
$client_secret = "CpnLABAjtIjaziGu2nlBelLMB17XTD2c";
$redirect_uri = "https://discord-auth-sunrise-rp-ucp.onrender.com/discord_callback.php";

// Get user ID from URL (passed from main site)
$sesuID = $_GET['state'] ?? null;
if (!$sesuID) {
    echo "User ID missing (state param)";
    exit;
}

// Make sure code exists
if (!isset($_GET['code'])) {
    echo "No code provided.";
    exit;
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
curl_close($ch);

$tokenData = json_decode($response, true);

// Check access token success
if (!isset($tokenData['access_token'])) {
    echo "Failed to get access token.<br>Raw response:<br><pre>" . htmlspecialchars($response) . "</pre>";
    exit;
}

$access_token = $tokenData['access_token'];

// Fetch user info
$ch = curl_init('https://discord.com/api/users/@me');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bearer $access_token"
]);
$userResponse = curl_exec($ch);
curl_close($ch);

$userData = json_decode($userResponse, true);

// Validate user data
if (!isset($userData['id'])) {
    echo "Failed to fetch user data.<br>Raw response:<br><pre>" . htmlspecialchars($userResponse) . "</pre>";
    exit;
}

$discord_user_id = $userData['id'];

// Save to DB
require('config.php');

$stmt = $conn->prepare("UPDATE users SET discord_userid = ? WHERE uid = ?");
$stmt->bind_param("ss", $discord_user_id, $sesuID);

if ($stmt->execute()) {
    echo "<script>alert('Discord linked successfully!'); window.location.href='https://sunriserp-ucp.byethost15.com/pages/index.php';</script>";
} else {
    echo "Failed to update DB: " . $stmt->error;
}

$stmt->close();
?>
