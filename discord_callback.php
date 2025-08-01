<?php
// Enable error reporting
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Load environment variables from Render (or .env locally if needed)
if (file_exists(__DIR__ . '/.env')) {
    require_once __DIR__ . '/vendor/autoload.php';
    Dotenv\Dotenv::createImmutable(__DIR__)->load();
}

// Discord app credentials from environment
$client_id     = getenv('CLIENT_ID');
$client_secret = getenv('CLIENT_SECRET');
$redirect_uri  = getenv('REDIRECT_URI');

// Discord bot credentials from environment
$bot_token = getenv('BOT_TOKEN');
$guild_id  = getenv('GUILD_ID');
$role_id   = getenv('ROLE_ID');

// Get user ID from URL (passed from main site)
$sesuID = $_GET['state'] ?? null;
if (!$sesuID) {
    echo "User ID missing (state param)";
    exit;
}

if (!$sesuID || $sesuID === 'unknown') {
    echo "Error: Invalid or missing state parameter. Please try connecting Discord again from your account page.";
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
    'client_id'     => $client_id,
    'client_secret' => $client_secret,
    'grant_type'    => 'authorization_code',
    'code'          => $code,
    'redirect_uri'  => $redirect_uri,
    'scope'         => 'identify guilds.join'
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
