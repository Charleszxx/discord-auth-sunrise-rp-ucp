<?php
include 'config.php'; // if needed for DB
// Do NOT use session-based UID here — it won't exist cross-domain

// Get UID from POST
$uid = $_POST['uid'] ?? null;

if (!$uid) {
  die("UID not provided.");
}

// Remove Discord info for that UID
$update = $con->prepare("UPDATE users SET discord_id = NULL, discord_verified = 0 WHERE uid = ?");
$success = $update->execute([$uid]);

if ($success) {
  echo "Discord account unlinked successfully.";

  // Optional: send a DM via your bot
  $userQuery = $con->prepare("SELECT * FROM users WHERE uid = ?");
  $userQuery->execute([$uid]);
  $user = $userQuery->fetch();

  if ($user && !empty($user['discord_id'])) {
    $discordId = $user['discord_id'];

    $message = [
      "content" => "<@$discordId>, there has been a request to unlink your Discord from the UCP. If this was not you, please contact staff."
    ];

    $jsonData = json_encode($message);

    $botToken = getenv('BOT_TOKEN'); // Store in .env or Render Secret
    $url = "https://discord.com/api/v10/users/@me/channels";

    // Create DM channel first
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["recipient_id" => $discordId]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
      "Authorization: Bot $botToken",
      "Content-Type: application/json"
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response = curl_exec($ch);
    $channel = json_decode($response, true);
    curl_close($ch);

    if (isset($channel['id'])) {
      // Send message to DM
      $channelId = $channel['id'];
      $url = "https://discord.com/api/v10/channels/$channelId/messages";

      $ch = curl_init($url);
      curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
      curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bot $botToken",
        "Content-Type: application/json"
      ]);
      curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
      $result = curl_exec($ch);
      curl_close($ch);
    }
  }

  // Redirect or show success
  header("Location: https://sunriserp-ucp.byethost15.com/pages/account.php?status=unlinked");
  exit;

} else {
  echo "Failed to unlink Discord.";
}
