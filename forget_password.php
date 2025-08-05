<?php
session_start();
require('config.php');
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Initialize step
if (!isset($_SESSION['fp_step'])) {
    $_SESSION['fp_step'] = 1;
}

$step = $_SESSION['fp_step'];
$bot_token = getenv('DISCORD_BOT_TOKEN');

function WP_Hash($password) {
    return strtoupper(hash('sha256', $password)); // SHA256 in uppercase
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($step) {
        case 1:
            if (isset($_POST['username'])) {
                $username = trim($_POST['username']);

                $stmt = $conn->prepare("SELECT uid, discord_userid FROM users WHERE username = ?");
                $stmt->bind_param("s", $username);
                $stmt->execute();
                $result = $stmt->get_result();

                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    $_SESSION['fp_uid'] = $user['uid'];
                    $_SESSION['fp_discord_id'] = $user['discord_userid'];

                    $otp = rand(100000, 999999);
                    $_SESSION['fp_otp'] = $otp;
                    $_SESSION['fp_step'] = 2; // Move to next step

                    sendDiscordOTP($user['discord_userid'], $otp, $bot_token);
                    $step = 2;
                    $message = "OTP sent to your Discord DM!";
                } else {
                    $error = "Username not found.";
                }
            }
            break;

        case 2:
            if (isset($_POST['otp'])) {
                if ($_POST['otp'] == $_SESSION['fp_otp']) {
                    $_SESSION['fp_step'] = 3;
                    $step = 3;
                    $message = "OTP verified. Please enter a new password.";
                } else {
                    $error = "Invalid OTP. Please try again.";
                }
            }
            break;

        case 3:
            if (isset($_POST['new_password'])) {
                $newPassword = WP_Hash($_POST['new_password']); // Use in-game hashing
                $uid = $_SESSION['fp_uid'];
        
                $stmt = $conn->prepare("UPDATE users SET password = ? WHERE uid = ?");
                $stmt->bind_param("ss", $newPassword, $uid);
                $stmt->execute();
        
                session_unset();
                session_destroy();
        
                echo "<script>alert('Password successfully changed! Please login again.'); window.location.href='https://sunriserp-ucp.byethost15.com';</script>";
                exit;
            }
            break;
    }
}

function sendDiscordOTP($discord_user_id, $otp, $bot_token) {
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

<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
  <meta charset="UTF-8">
  <title>Forgot Password - Sunrise RP</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <link rel="icon" href="files/images/logo.ico">
  <script>
    tailwind.config = {
      darkMode: 'class',
      theme: {
        extend: {
          colors: {
            primary: "#4f46e5",
            bg: "#fdf6f0",
            card: "#fcfbf9",
            darkbg: "#1f2937",
            darkcard: "#111827"
          }
        }
      }
    };
  </script>
  <script src="https://unpkg.com/lucide@latest"></script>
</head>
<body class="min-h-screen flex items-center justify-center bg-bg dark:bg-darkbg text-gray-800 dark:text-gray-100 transition-colors duration-300 p-5">

  <button id="darkToggle" class="absolute top-4 right-4 p-2 rounded-full bg-card dark:bg-darkcard hover:bg-gray-200 dark:hover:bg-gray-700 transition">
    <i data-lucide="moon" class="w-100 h-100 dark:hidden"></i>
    <i data-lucide="sun-dim" class="w-100 h-100 hidden dark:inline"></i>
  </button>

  <div class="bg-card dark:bg-darkcard rounded-xl shadow-lg p-8 w-full max-w-md">
    <div class="flex justify-center mb-4">
      <img src="https://sunriserp-ucp.byethost15.com/files/images/logo.png" alt="Logo" class="h-16 w-16 object-contain" />
    </div>
    <p class="text-center">Sunrise Roleplay - UCP</p>
    <h2 class="text-2xl font-semibold text-center mb-4">Reset your password</h2>

    <?php if (isset($message)): ?>
      <div class="mb-4 text-sm text-green-600 font-medium text-center"><?php echo $message; ?></div>
    <?php endif; ?>
    <?php if (isset($error)): ?>
      <div class="mb-4 text-sm text-red-600 font-medium text-center"><?php echo $error; ?></div>
    <?php endif; ?>

    <form method="POST" class="space-y-4">
      <?php if ($step === 1): ?>
        <div>
          <label for="username" class="block text-sm font-medium mb-1">Username</label>
          <input type="text" name="username" id="username" required class="w-full px-4 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter your username">
        </div>
      <?php elseif ($step === 2): ?>
        <div>
          <label for="otp" class="block text-sm font-medium mb-1">OTP Code (Check Discord)</label>
          <input type="text" name="otp" id="otp" required class="w-full px-4 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="6-digit OTP">
        </div>
      <?php elseif ($step === 3): ?>
        <div>
          <label for="new_password" class="block text-sm font-medium mb-1">New Password</label>
          <input type="text" name="new_password" id="new_password" required class="w-full px-4 py-2 rounded-lg bg-white dark:bg-gray-800 border border-gray-300 dark:border-gray-700 focus:outline-none focus:ring-2 focus:ring-primary" placeholder="Enter new password">
        </div>
      <?php endif; ?>

      <button type="submit" class="w-full bg-primary text-white py-2 rounded-lg hover:bg-indigo-600 transition font-semibold">
        <?php echo ($step === 3) ? 'Change Password' : 'Continue'; ?>
      </button>
    </form>
  </div>

  <script>
    const darkToggle = document.getElementById('darkToggle');
    const html = document.documentElement;

    if (localStorage.getItem('theme') === 'dark') {
      html.classList.add('dark');
      html.setAttribute('data-theme', 'dark');
    }

    darkToggle.addEventListener('click', () => {
      html.classList.toggle('dark');
      const isDark = html.classList.contains('dark');
      html.setAttribute('data-theme', isDark ? 'dark' : 'light');
      localStorage.setItem('theme', isDark ? 'dark' : 'light');
    });

    lucide.createIcons();
  </script>
</body>
</html>
