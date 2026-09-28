<?php
// ==========================================
// STI CEATS - OTA SYSTEM UPDATER (SELF-CONTAINED)
// ==========================================
session_start();
date_default_timezone_set('Asia/Manila');

// 🛑 GATEKEEPER: Bawal i-run ng walang Admin Session
if (!isset($_SESSION['exam_admin'])) {
    die("UNAUTHORIZED ACCESS. PLEASE LOGIN TO ADMIN PANEL.");
}

$status_msg = "Preparing to update system...";
$targetDir = __DIR__; // Kung saan nakalagay si updater.php, doon din ang update target!

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['run_update'])) {
    try {
        $checkJsonUrl = 'https://502studio.tech/updates/check.json';

        // 1. Fetch metadata using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $checkJsonUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $json = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200 || !$json) {
            throw new Exception("Could not reach 502 Studio update server. (HTTP: {$httpCode} - {$curlError})");
        }
        
        $data = json_decode($json, true);
        if (!isset($data['download_url']) || !isset($data['version'])) {
            throw new Exception("Invalid update package metadata from server.");
        }

        $zipFile = $targetDir . '/update_temp.zip';

        // 2. Download the ZIP file using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $data['download_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $fileData = curl_exec($ch);
        $downloadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($downloadHttpCode !== 200 || empty($fileData)) {
            throw new Exception("Failed to download update package zip from: " . $data['download_url']);
        }
        
        if (file_put_contents($zipFile, $fileData) === false) {
            throw new Exception("Failed to write temporary zip file. Check folder write permissions.");
        }

        // 3. Extract the ZIP file directly where updater.php is located
        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($targetDir); 
            $zip->close();
            @unlink($zipFile); // Delete temp zip
            
            // 4. UPDATE LOCAL version.json AUTOMATICALLY in the exact same folder
            $newVersion = $data['version'];
            @file_put_contents($targetDir . '/version.json', json_encode(['version' => $newVersion], JSON_PRETTY_PRINT));

            // 5. Run database upgrades if included in the zip
            if (file_exists($targetDir . '/upgrade.php')) {
                require_once $targetDir . '/db.php';
                include $targetDir . '/upgrade.php';
                @unlink($targetDir . '/upgrade.php'); // Delete after running
            }

            // Redirect back to admin panel with success flag
            header("Location: admin.php?update_success=1");
            exit;
        } else {
            throw new Exception("Failed to extract the update package archive.");
        }
    } catch (Exception $e) {
        $status_msg = "❌ UPDATE FAILED: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>System Updater | STI CEATS</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <style>
        :root { --sti-yellow: #fDD000; --sti-blue: #003666; --white: #ffffff; --bg: #f4f7f9; --text-dark: #001f3f; --text-gray: #657786; --border: #e1e8ed; --green: #00b35c; --red: #FF0B2F; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text-dark); display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
        .box { background: var(--white); border: 1px solid var(--border); padding: 40px; border-radius: 16px; box-shadow: 0 10px 30px rgba(0,54,102,0.05); max-width: 500px; width: 100%; text-align: center; border-top: 6px solid var(--sti-yellow); }
        h1 { font-family: 'Oswald'; color: var(--sti-blue); font-size: 28px; margin: 0 0 10px 0; text-transform: uppercase; letter-spacing: 1px;}
        p { font-size: 13px; color: var(--text-gray); margin-bottom: 25px; font-weight: 600; word-break: break-all; }
        .btn { display: inline-block; background: var(--sti-blue); color: var(--white); font-family: 'Oswald'; font-size: 16px; font-weight: 700; padding: 14px 30px; text-decoration: none; border-radius: 50px; text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; box-shadow: 0 5px 15px rgba(0,54,102,0.2);}
        .btn:hover { background: var(--sti-yellow); color: var(--sti-blue); transform: translateY(-2px); }
    </style>
</head>
<body>
    <div class="box">
        <h1>SYSTEM UPDATER</h1>
        <p><?php echo $status_msg; ?></p>
        <a href="admin.php" class="btn">RETURN TO ADMIN PANEL</a>
    </div>
</body>
</html>