<?php
session_start();

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Discord App credentials
$client_id = "1360930351572193442";
$client_secret = "aRgjpb3b9JR-dD2PfkH2W4BpL5T1wTQI";
$redirect_uri = "https://discord-auth-sunrise-rp-ucp.onrender.com/discord_callback.php";

if (isset($_GET['code'])) {
    $code = $_GET['code'];

    // Exchange code for access token
    $data = [
        'client_id' => $client_id,
        'client_secret' => $client_secret,
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirect_uri,
        'scope' => 'identify'
    ];

    $ch = curl_init('https://discord.com/api/oauth2/token');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    $response = curl_exec($ch);
    curl_close($ch);

    $tokenData = json_decode($response, true);

    if (!isset($tokenData['access_token'])) {
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error_msg = curl_error($ch);
        die("Failed to obtain access token from Discord. HTTP code: $http_code<br>Error: $error_msg<br>Response: $response");
    }

    $access_token = $tokenData['access_token'];

    // Fetch user info from Discord
    $ch = curl_init('https://discord.com/api/users/@me');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $access_token"
    ]);
    $userResponse = curl_exec($ch);
    curl_close($ch);

    $userData = json_decode($userResponse, true);

    if (!isset($userData['id'])) {
        die("Failed to fetch user info from Discord. Raw response: " . $userResponse);
    }

    $discord_user_id = $userData['id']; // Discord ID to be saved

    // Save to DB
    require('config.php'); // This should NOT call session_start() again

    if (!isset($_SESSION['uid'])) {
        die("No session UID found. Are you logged in?");
    }

    $sesuID = $_SESSION['uid'];

    try {
        $stmt = $con->prepare("UPDATE users SET discord_userid = :discord_id WHERE uid = :uid");
        $stmt->execute([
            ':discord_id' => $discord_user_id,
            ':uid' => $sesuID
        ]);
        echo "<script>alert('Discord linked successfully!'); window.location.href='../pages/dashboard.php';</script>";
    } catch (PDOException $e) {
        die("Database error: " . $e->getMessage());
    }
} else {
    echo "No authorization code received from Discord.";
}
?>
