<?php
session_start();

// Enable error reporting (disable in production)
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Use getenv() — Render injects environment variables directly
$client_id     = getenv('CLIENT_ID');
$client_secret = getenv('CLIENT_SECRET');
$redirect_uri  = getenv('REDIRECT_URI');
$bot_token     = getenv('BOT_TOKEN');
$guild_id      = getenv('GUILD_ID');
$role_id       = getenv('ROLE_ID');

// Validate state
$sesuID = $_GET['state'] ?? null;
if (!$sesuID || $sesuID === 'unknown') {
    exit("Invalid or missing state parameter.");
}

// Validate code
if (!isset($_GET['code'])) {
    exit("Missing authorization code.");
}

$code = $_GET['code'];

// Exchange code for access token
$tokenData = exchangeCodeForToken($client_id, $client_secret, $redirect_uri, $code);
if (!$tokenData || !isset($tokenData['access_token'])) {
    exit("Failed to obtain access token.");
}

$access_token = $tokenData['access_token'];

// Get user info from Discord
$userData = getDiscordUser($access_token);
if (!$userData || !isset($userData['id'])) {
    exit("Failed to fetch Discord user info.");
}

$discord_user_id = $userData['id'];

// Update database
require 'config.php';

$stmt = $con->prepare("UPDATE users SET discord_userid = ?, discord_verified = 1 WHERE uid = ?");
$stmt->execute([$discord_user_id, $sesuID]);

if ($stmt->rowCount() > 0) {
    // Assign role
    $roleSuccess = assignDiscordRole($bot_token, $guild_id, $discord_user_id, $role_id);
    if ($roleSuccess) {
        echo "<script>alert('Discord linked and role assigned successfully!'); window.location.href='https://sunriserp-ucp.byethost15.com/pages/dashboard.php';</script>";
    } else {
        echo "Discord linked, but failed to assign role.";
    }
} else {
    echo "Database update failed or user not found.";
}

// === Helper Functions ===

function exchangeCodeForToken($client_id, $client_secret, $redirect_uri, $code) {
    $data = [
        'client_id'     => $client_id,
        'client_secret' => $client_secret,
        'grant_type'    => 'authorization_code',
        'code'          => $code,
        'redirect_uri'  => $redirect_uri,
        'scope'         => 'identify guilds.join'
    ];

    $ch = curl_init('https://discord.com/api/oauth2/token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POSTFIELDS => http_build_query($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/x-www-form-urlencoded']
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function getDiscordUser($access_token) {
    $ch = curl_init('https://discord.com/api/users/@me');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer $access_token"]
    ]);
    $response = curl_exec($ch);
    curl_close($ch);

    return json_decode($response, true);
}

function assignDiscordRole($bot_token, $guild_id, $user_id, $role_id) {
    $url = "https://discord.com/api/v10/guilds/$guild_id/members/$user_id/roles/$role_id";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => "PUT",
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            "Authorization: Bot $bot_token",
            "Content-Type: application/json"
        ]
    ]);
    curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $status === 204;
}
?>
