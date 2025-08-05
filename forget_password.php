<?php
require('config.php');
session_start();
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Step Control
$step = $_SESSION['fp_step'] ?? 1;
$bot_token = getenv('DISCORD_BOT_TOKEN');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($step === 1 && isset($_POST['username'])) {
        $username = $_POST['username'];

        // Check if username exists
        $stmt = $conn->prepare("SELECT uid, discord_userid FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            $_SESSION['fp_uid'] = $user['uid'];
            $_SESSION['fp_discord_id'] = $user['discord_userid'];

            // Generate OTP and save to session
            $otp = rand(100000, 999999);
            $_SESSION['fp_otp'] = $otp;
            $_SESSION['fp_step'] = 2;

            // Send OTP to Discord DM
            sendDiscordOTP($user['discord_userid'], $otp, $bot_token);
            $message = "OTP sent to your Discord DM!";
        } else {
            $error = "Username not found.";
        }

    } elseif ($step === 2 && isset($_POST['otp'])) {
        $enteredOTP = $_POST['otp'];

        if ($enteredOTP == $_SESSION['fp_otp']) {
            $_SESSION['fp_step'] = 3;
            $message = "OTP verified. Please enter a new password.";
        } else {
            $error = "Invalid OTP. Please try again.";
        }

    } elseif ($step === 3 && isset($_POST['new_password'])) {
        $newPassword = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
        $uid = $_SESSION['fp_uid'];

        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE uid = ?");
        $stmt->bind_param("ss", $newPassword, $uid);
        $stmt->execute();

        session_unset();
        session_destroy();

        echo "<script>alert('Password successfully changed! Please login again.'); window.location.href='login.php';</script>";
        exit;
    }
}

// Function to send OTP to Discord DM
function sendDiscordOTP($discord_user_id, $otp, $bot_token) {
    // Create DM channel
    $ch = curl_init("https://discord.com/api/users/@me/channels");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["recipient_id" => $discord_user_id]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bot $bot_token",
        "Content-Type: application/json"
    ]);
    $dmChannelResponse = curl_exec($ch);
    curl_close($ch);

    $dmData = json_decode($dmChannelResponse, true);

    if (!isset($dmData['id'])) {
        error_log("Failed to create DM channel for user $discord_user_id");
        return;
    }

    $dmChannelId = $dmData['id'];

    $dmMessage = [
        "embeds" => [[
            "title" => "🔐 Sunrise RP UCP Password Reset OTP",
            "description" => "Your One-Time Password (OTP) for resetting your UCP password is:\n\n**$otp**\n\nThis code will expire after use.",
            "color" => hexdec("FFD700"),
            "footer" => ["text" => "Sunrise Roleplay | Password Reset"],
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
    curl_exec($ch);
    curl_close($ch);
}
?>

<!-- HTML OUTPUT -->
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password</title>
  <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100 flex items-center justify-center h-screen">
  <div class="bg-white p-8 rounded shadow-md w-full max-w-md">
    <h2 class="text-2xl font-bold mb-6 text-center">Forgot Password</h2>

    <?php if (isset($message)): ?>
      <p class="mb-4 text-green-600 font-medium"><?php echo $message; ?></p>
    <?php endif; ?>
    <?php if (isset($error)): ?>
      <p class="mb-4 text-red-600 font-medium"><?php echo $error; ?></p>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <?php if ($step === 1): ?>
        <div>
          <label for="username" class="block text-sm font-medium mb-1">Username</label>
          <input type="text" name="username" id="username" required class="w-full px-4 py-2 rounded border border-gray-300" placeholder="Enter your username">
        </div>
      <?php elseif ($step === 2): ?>
        <div>
          <label for="otp" class="block text-sm font-medium mb-1">Enter OTP from Discord DM</label>
          <input type="text" name="otp" id="otp" required class="w-full px-4 py-2 rounded border border-gray-300" placeholder="6-digit OTP">
        </div>
      <?php elseif ($step === 3): ?>
        <div>
          <label for="new_password" class="block text-sm font-medium mb-1">New Password</label>
          <input type="password" name="new_password" id="new_password" required class="w-full px-4 py-2 rounded border border-gray-300" placeholder="Enter new password">
        </div>
      <?php endif; ?>

      <button type="submit" class="w-full bg-yellow-500 text-white py-2 rounded hover:bg-yellow-600 font-semibold">
        <?php echo ($step === 3) ? 'Change Password' : 'Continue'; ?>
      </button>
    </form>
  </div>
</body>
</html>
