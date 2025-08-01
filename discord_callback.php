<?php
require('config.php'); // DB config
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$client_id = "1360930351572193442";
$client_secret = getenv('DISCORD_CLIENT_SECRET');
$redirect_uri = "https://discord-auth-sunrise-rp-ucp.onrender.com/discord_callback.php";
$bot_token = getenv('DISCORD_BOT_TOKEN'); // Securely stored in Render env vars

$guild_id = "1399685590546518057";
$role_id = "1399732879986262128";

$state = $_GET['state'] ?? null;
if (!$state) die("Missing UID (state).");

if (!isset($_GET['code'])) die("No authorization code received from Discord.");

$code = $_GET['code'];

// Get access token
$data = [
    'client_id' => $client_id,
    'client_secret' => $client_secret,
    'grant_type' => 'authorization_code',
    'code' => $code,
    'redirect_uri' => $redirect_uri,
    'scope' => 'identify guilds.join'
];

$ch = curl_init('https://discord.com/api/oauth2/token');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
$response = curl_exec($ch);
$tokenData = json_decode($response, true);
curl_close($ch);

if (!isset($tokenData['access_token'])) {
    die("Failed to obtain access token from Discord. Response: $response");
}

$access_token = $tokenData['access_token'];

// Fetch Discord user info
$ch = curl_init('https://discord.com/api/users/@me');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $access_token"]);
$userResponse = curl_exec($ch);
$userData = json_decode($userResponse, true);
curl_close($ch);

if (!isset($userData['id'])) {
    die("Failed to fetch user info from Discord.");
}

$discord_user_id = $userData['id'];
$username = $userData['username'] . '#' . $userData['discriminator'];

// Add user to guild (optional but recommended)
$joinPayload = json_encode([
    "access_token" => $access_token
]);

$ch = curl_init("https://discord.com/api/guilds/$guild_id/members/$discord_user_id");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_POSTFIELDS, $joinPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $bot_token",
    "Content-Type: application/json"
]);
$joinResponse = curl_exec($ch);
$joinCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($joinCode != 201 && $joinCode != 204) {
    die("Failed to add user to guild. HTTP Code: $joinCode, Response: $joinResponse");
}

// Assign role to user
$role_url = "https://discord.com/api/guilds/$guild_id/members/$discord_user_id/roles/$role_id";
$ch = curl_init($role_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $bot_token",
    "Content-Type: application/json"
]);
$roleResponse = curl_exec($ch);
$roleCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($roleCode != 204) {
    die("Failed to assign role. HTTP Code: $roleCode, Response: $roleResponse");
}

// Save to database
$stmt = $conn->prepare("UPDATE users SET discord_userid = ?, discord_verified = 1 WHERE uid = ?");
$stmt->bind_param("ss", $discord_user_id, $state);
$stmt->execute();

// Redirect
echo "<script>alert('Discord linked & role assigned to @$username!'); window.location.href='https://sunriserp-ucp.byethost15.com/pages/dashboard.php';</script>";
?>
