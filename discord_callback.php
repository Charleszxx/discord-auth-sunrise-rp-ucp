<?php
require('config.php'); // DB config
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Discord OAuth & bot config
$client_id = "1360930351572193442";
$client_secret = getenv('DISCORD_CLIENT_SECRET');
$redirect_uri = "https://discord-auth-sunrise-rp-ucp.onrender.com/discord_callback.php";
$bot_token = getenv('DISCORD_BOT_TOKEN');

$guild_id = "1399685590546518057";
$role_id = "1399732879986262128"; // Verified role
$announcement_channel_id = "1401395657020932208";
$remove_role_id = "1401400270709194773"; // Role to remove after verified

$state = $_GET['state'] ?? null;
if (!$state) die("Missing UID (state).");

if (!isset($_GET['code'])) die("No authorization code received from Discord.");

$code = $_GET['code'];

// Step 1: Get access token
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

// Step 2: Get user info
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
$avatar_hash = $userData['avatar'];
$avatar_url = "https://cdn.discordapp.com/avatars/{$discord_user_id}/{$avatar_hash}.png";

// Step 3: Add user to guild
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

// Step 4: Assign verified role
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

// Step 5: Update DB
$stmt = $conn->prepare("UPDATE users SET discord_userid = ?, discord_verified = 1 WHERE uid = ?");
$stmt->bind_param("ss", $discord_user_id, $state);
$stmt->execute();

// Step 5.1: Remove temporary role after verification
$remove_url = "https://discord.com/api/guilds/$guild_id/members/$discord_user_id/roles/$remove_role_id";
$ch = curl_init($remove_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "DELETE");
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $bot_token",
    "Content-Type: application/json"
]);
$removeResponse = curl_exec($ch);
$removeCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

// Optional: You could log if role removal failed, but don’t halt script
if ($removeCode != 204) {
    error_log("Warning: Failed to remove role $remove_role_id from user $discord_user_id. HTTP Code: $removeCode");
}

// Step 6: Send embed message to channel
$embed = [
    "title" => "✅ User Verified Successfully!",
    "description" => "**@$username** has successfully completed Discord verification via the User Control Panel.",
    "color" => hexdec("FFD700"), // Yellow
    "thumbnail" => [
        "url" => $avatar_url
    ],
    "footer" => [
        "text" => "Sunrise Roleplay | User Control Panel Discord Verification"
    ],
    "timestamp" => date("c")
];

$payload = json_encode([
    "embeds" => [$embed]
]);

$webhook_url = "https://discord.com/api/channels/$announcement_channel_id/messages";
$ch = curl_init($webhook_url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $bot_token",
    "Content-Type: application/json"
]);
$discordNotifyResponse = curl_exec($ch);
curl_close($ch);

// Step 6.1: Send DM to the user
$dmPayload = json_encode([
    "recipient_id" => $discord_user_id
]);

// Create DM channel
$ch = curl_init("https://discord.com/api/users/@me/channels");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $dmPayload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $bot_token",
    "Content-Type: application/json"
]);
$dmChannelResponse = curl_exec($ch);
$dmChannelData = json_decode($dmChannelResponse, true);
curl_close($ch);

if (!isset($dmChannelData['id'])) {
    error_log("Failed to create DM channel for user $discord_user_id. Response: $dmChannelResponse");
} else {
    $dmChannelId = $dmChannelData['id'];

    // Send DM message
    $dmMessage = [
        "embeds" => [[
            "title" => "✅ Discord Verification Successful",
            "description" => "Your Discord account has been successfully linked and verified via the User Control Panel (UCP).\n\nWelcome aboard, and thank you for completing the verification process!",
            "color" => hexdec("FFD700"), // Nice teal-green tone
            "footer" => [
                "text" => "Sunrise Roleplay | UCP Verification"
            ],
            "timestamp" => date("c")
        ]]
    ];

    $ch = curl_init("https://discord.com/api/channels/$dmChannelId/messages");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dmMessage));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bot $bot_token",
        "Content-Type: application/json"
    ]);
    $dmSendResponse = curl_exec($ch);
    curl_close($ch);
}

// Step 7: Redirect
echo "<script>alert('Discord linked & role assigned to @$username!'); window.location.href='https://sunriserp-ucp.byethost15.com/pages/dashboard.php';</script>";
?>
