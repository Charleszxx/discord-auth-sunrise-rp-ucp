<?php
// Database connection (MySQLi)
$servername = "neutron.optiklink.com";
$username = "u247379_Wyc8AuJUck";
$password = "@Ev1I0Em3QjzO8GEJ!wtFGnw";
$dbname = "s247379_sunriserp";

$con = new mysqli($servername, $username, $password, $dbname);
if ($con->connect_error) {
    die("Connection failed: " . $con->connect_error);
}

// Get UID from POST, not session
$uid = $_POST['uid'] ?? null;
if (!$uid) {
    die("UID not provided.");
}

// Fetch user by UID
$stmt = $con->prepare("SELECT * FROM users WHERE uid = ?");
$stmt->bind_param("i", $uid);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if (!$user || empty($user['discord_userid'])) {
    die("No Discord account linked.");
}

$discordId = $user['discord_userid'];

// Unlink the Discord account in the database
$stmt = $con->prepare("UPDATE users SET discord_userid = NULL, discord_verified = 0 WHERE uid = ?");
$stmt->bind_param("i", $uid);
$success = $stmt->execute();
$stmt->close();

if (!$success) {
    die("Failed to unlink Discord.");
}

// Send DM via Discord bot
$botToken = getenv("DISCORD_BOT_TOKEN");
$createChannelUrl = "https://discord.com/api/v10/users/@me/channels";

// Step 1: Create DM channel
$ch = curl_init($createChannelUrl);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["recipient_id" => $discordId]));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $botToken",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$response = curl_exec($ch);
$channel = json_decode($response, true);
curl_close($ch);

if (!isset($channel['id'])) {
    // Couldn't create DM, but unlink still successful
    header("Location: https://sunriserp-ucp.byethost15.com/pages/account.php?status=unlinked_nodm");
    exit;
}

$channelId = $channel['id'];
$message = [
    "content" => "Your Discord account was **unlinked** from the Sunrise RP UCP. If this wasn't you, please contact support."
];

// Step 2: Send DM
$ch = curl_init("https://discord.com/api/v10/channels/{$channelId}/messages");
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $botToken",
    "Content-Type: application/json"
]);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_exec($ch);
curl_close($ch);

// Redirect to account page
header("Location: https://sunriserp-ucp.byethost15.com/pages/account.php?status=unlinked");
exit;
?>
