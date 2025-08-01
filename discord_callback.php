<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Discord app credentials
$client_id = "1236520127869227131";
$client_secret = "CpnLABAjtIjaziGu2nlBelLMB17XTD2c";
$redirect_uri = "https://discord-auth-sunrise-rp-ucp.onrender.com/discord_callback.php";

// Discord bot credentials
$bot_token = "MTM2MDkzMDM1MTU3MjE5MzQ0Mg.G4vdQI.7k6QXTvsYO_jdRSIgHh4Jc_YgMRljROg4Fcedk"; // 🔒 Add your bot token
$guild_id = "1399685590546518057";   // e.g. 123456789012345678
$role_id = "1399732879986262128";   // Role to assign

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
    'scope' => 'identify guilds.join'
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

// Get Discord user info
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

$discord_user_id = $userData['id'];

// Save to DB
require('config.php');

$stmt = $conn->prepare("UPDATE users SET discord_userid = ?, discord_verified = 1 WHERE uid = ?");
$stmt->bind_param("ss", $discord_user_id, $sesuID);

if ($stmt->execute()) {

    // Add role using bot
    $addRoleUrl = "https://discord.com/api/v10/guilds/$guild_id/members/$discord_user_id/roles/$role_id";

    $ch = curl_init($addRoleUrl);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bot $bot_token",
        "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $roleResponse = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode == 204) {
        echo "<script>alert('Discord linked and role assigned successfully!'); window.location.href='https://sunriserp-ucp.byethost15.com/pages/index.php';</script>";
    } else {
        echo "Discord linked, but failed to assign role. HTTP Code: $httpCode<br><pre>$roleResponse</pre>";
    }

} else {
    echo "Failed to update DB: " . $stmt->error;
}

$stmt->close();
?>
