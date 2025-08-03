<?php
session_start();
include 'config.php';

// Define checkForLogin() function here
function checkForLogin() {
  if (!isset($_SESSION['uid'])) {
    header("Location: https://sunriserp-ucp.byethost15.com/index.php");
    exit;
  }
}

checkForLogin();

$uid = $_POST['uid'] ?? null;

// IMPORTANT: Secure your token properly! Do NOT hardcode it like this in production.
$bot_token = getenv('DISCORD_BOT_TOKEN');
$guild_id = '1399685590546518057';

if (!$uid) {
  header('Location: https://sunriserp-ucp.byethost15.com/index.php');
  exit;
}

try {
  // Get Discord user ID before unlinking
  $stmt = $con->prepare("SELECT discord_userid FROM users WHERE uid = ?");
  $stmt->execute([$uid]);
  $result = $stmt->fetch(PDO::FETCH_ASSOC);
  $discord_user_id = $result['discord_userid'];

  if (!$discord_user_id) {
    $_SESSION['error'] = "No Discord account linked.";
    header("Location: https://sunriserp-ucp.byethost15.com/pages/dashboard.php");
    exit;
  }

  // Unlink in DB
  $stmt = $con->prepare("UPDATE users SET discord_verified = 0, discord_userid = NULL WHERE uid = ?");
  $stmt->execute([$uid]);

  // Step 1: Create DM channel with user
  $ch = curl_init("https://discord.com/api/v10/users/@me/channels");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $bot_token",
    "Content-Type: application/json"
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
    "recipient_id" => $discord_user_id
  ]));
  $dmResponse = curl_exec($ch);
  curl_close($ch);

  // Log DM channel creation response
  file_put_contents(__DIR__ . "/dm_response.json", $dmResponse);

  $dmData = json_decode($dmResponse, true);

  if (!isset($dmData['id'])) {
    $_SESSION['error'] = "Failed to send DM.";
    header("Location: https://sunriserp-ucp.byethost15.com/pages/dashboard.php");
    exit;
  }

  $dm_channel_id = $dmData['id'];

  // Step 2: Send message in DM
  $now = date("F j, Y, g:i a");
  $message = [
    "content" => "Hello! A request to **unlink your Discord account** from the Sunrise RP UCP was made on `$now`. If this wasn't you, please secure your account or contact support."
  ];

  $ch = curl_init("https://discord.com/api/v10/channels/$dm_channel_id/messages");
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Authorization: Bot $bot_token",
    "Content-Type: application/json"
  ]);
  curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($message));
  $msgSend = curl_exec($ch);
  curl_close($ch);

  // Log message send response
  file_put_contents(__DIR__ . "/message_response.json", $msgSend);

  $_SESSION['success'] = "Your Discord account has been unlinked.";
} catch (Exception $e) {
  file_put_contents(__DIR__ . "/unlink_error_log.txt", $e->getMessage());
  $_SESSION['error'] = "Something went wrong while unlinking.";
}

header("Location: https://sunriserp-ucp.byethost15.com/pages/dashboard.php");
exit;
