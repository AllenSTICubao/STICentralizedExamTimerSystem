<?php
// ==========================================
// STI CETS - STANDALONE WEB INSTALLER (ROOT LEVEL)
// ==========================================
date_default_timezone_set('Asia/Manila');

$step = 1;
$error = '';
$success = '';

// 🛑 SAFETY CHECK: Huwag payagang umandar sa mismong Master Servers (Patch Server Protection)
$current_host = strtolower($_SERVER['HTTP_HOST'] ?? '');
$master_servers = ['502studio.tech', 'www.502studio.tech', 'ppopstage.ph', 'www.ppopstage.ph'];

if (in_array($current_host, $master_servers)) {
    $error = "SECURITY BLOCK: You cannot run this installer on the Master Server ($current_host). Please place and execute this file on your target campus server.";
}

// Check if ZipArchive is enabled
if (empty($error) && !extension_loaded('zip')) {
    $error = "CRITICAL ERROR: PHP 'zip' extension is disabled on this server. Please enable it in cPanel/php.ini.";
}

// Check if cURL is enabled
if (empty($error) && !function_exists('curl_init')) {
    $error = "CRITICAL ERROR: PHP 'curl' extension is disabled on this server. Please enable it in cPanel/php.ini.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['install']) && empty($error)) {
    try {
        $checkJsonUrl = 'https://502studio.tech/updates/check.json';

        // 1. Fetch latest release metadata using cURL
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
            throw new Exception("Could not connect to 502 Studio update server. (HTTP Code: {$httpCode} - {$curlError})");
        }
        
        $data = json_decode($json, true);
        if (!isset($data['download_url']) || !isset($data['version'])) {
            throw new Exception("Invalid response structure from update server.");
        }

        $zipFile = __DIR__ . '/install_temp.zip';

        // 2. Download the latest package zip using cURL
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $data['download_url']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        $fileData = curl_exec($ch);
        $downloadHttpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($downloadHttpCode !== 200 || empty($fileData)) {
            throw new Exception("Failed to download system package zip from: " . $data['download_url']);
        }
        
        if (file_put_contents($zipFile, $fileData) === false) {
            throw new Exception("Failed to write temporary zip file. Check folder write permissions (CHMOD 755/777).");
        }

        // 3. Extract contents directly to THIS directory (Root)
        $targetDir = __DIR__;

        $zip = new ZipArchive;
        if ($zip->open($zipFile) === TRUE) {
            $zip->extractTo($targetDir);
            $zip->close();
            @unlink($zipFile); // Clean up temp zip

            // 4. Create initial version.json tracking file
            @file_put_contents($targetDir . '/version.json', json_encode(['version' => $data['version']], JSON_PRETTY_PRINT));

            $step = 2; // Success step
        } else {
            throw new Exception("Failed to extract system package archive. The zip file might be corrupted.");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STI Centralized Exam Timer System | Standalone Web Installer</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <style>
        /* 🎨 EUROVISION LIGHT MODE AESTHETIC */
        :root { 
            --sti-yellow: #fDD000; 
            --sti-blue: #003666; 
            --white: #ffffff; 
            --bg: #f4f7f9; 
            --text-dark: #001f3f; 
            --text-gray: #657786; 
            --border: #e1e8ed; 
            --green: #00b35c;
            --red: #FF0B2F;
        }
        
        body { 
            font-family: 'Montserrat', sans-serif; 
            background: linear-gradient(-45deg, #f4f7f9, #e1e8ed, #ffffff, #f4f7f9);
            background-size: 400% 400%;
            animation: gradientBG 15s ease infinite;
            color: var(--text-dark); 
            margin: 0; padding: 0; 
            display: flex; flex-direction: column; min-height: 100vh; justify-content: space-between;
        }
        * { box-sizing: border-box; }
        @keyframes gradientBG { 0% { background-position: 0% 50%; } 50% { background-position: 100% 50%; } 100% { background-position: 0% 50%; } }

        .top-nav { 
            background: rgba(255, 255, 255, 0.95); backdrop-filter: blur(15px); border-bottom: 1px solid var(--border); 
            display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; box-shadow: 0 4px 20px rgba(0,54,102,0.05);
        }
        .brand-zone { display: flex; align-items: center; gap: 15px; }
        .brand-logo-container img { height: 35px; object-fit: contain; }
        .top-nav h1 { margin: 0; font-family: 'Oswald'; font-size: 22px; color: var(--sti-blue); letter-spacing: 1px; text-transform: uppercase; display: flex; align-items: center; }
        .top-nav h1 span { color: var(--sti-yellow); background: var(--sti-blue); padding: 2px 8px; border-radius: 4px; margin-right: 8px; }

        .container { max-width: 800px; margin: 40px auto; padding: 0 20px; width: 100%; }
        .installer-box { 
            background: rgba(255, 255, 255, 0.85); border: 1px solid var(--white); border-radius: 16px; padding: 50px; text-align: center; 
            box-shadow: 0 10px 30px rgba(0,54,102,0.05); backdrop-filter: blur(20px); border-top: 8px solid var(--sti-yellow);
        }
        .installer-box h2 { font-family: 'Oswald'; font-size: 36px; color: var(--sti-blue); margin-top: 0; text-transform: uppercase; letter-spacing: 1px; }
        .installer-box p { color: var(--text-gray); font-size: 15px; line-height: 1.6; max-width: 600px; margin: 0 auto 30px auto; font-weight: 600; }

        .btn-install { 
            display: inline-block; background: linear-gradient(135deg, var(--sti-yellow), #ffea00); color: var(--sti-blue); 
            font-family: 'Oswald'; font-size: 20px; font-weight: 700; padding: 16px 40px; border-radius: 50px; border: none; cursor: pointer; 
            text-transform: uppercase; letter-spacing: 1px; transition: 0.3s; box-shadow: 0 5px 15px rgba(253,208,0,0.3); width: 100%;
        }
        .btn-install:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(253,208,0,0.5); filter: brightness(1.05); }

        .error-box { background: rgba(255, 11, 47, 0.1); border: 1px solid var(--red); color: var(--red); padding: 15px; border-radius: 8px; margin-bottom: 25px; font-weight: 700; text-align: left; font-size: 13px; word-break: break-all; }
        
        .btn-launch { 
            display: inline-block; background: var(--sti-blue); color: var(--sti-yellow); text-decoration: none; padding: 16px 40px; 
            font-family: 'Oswald'; font-size: 22px; border-radius: 50px; font-weight: bold; margin-top: 20px; transition: 0.3s; box-shadow: 0 5px 15px rgba(0,54,102,0.2);
        }
        .btn-launch:hover { background: var(--sti-yellow); color: var(--sti-blue); transform: scale(1.03); }

        .credit-footer { text-align: center; font-size: 12px; color: var(--text-gray); line-height: 1.6; max-width: 800px; padding: 20px; margin: 40px auto 20px auto; font-weight: 600; }
        .credit-footer a { color: var(--sti-blue); font-weight: bold; text-decoration: none; transition: 0.2s; }
        .credit-footer a:hover { color: var(--sti-yellow); text-decoration: underline; }
    </style>
</head>
<body>

    <div class="top-nav">
        <div class="brand-zone">
            <div class="brand-logo-container">
                <img src="https://ppopstage.ph/temp/uploads/logo_6a9e5a2a1e16a.png" alt="" crossorigin="anonymous" onerror="this.onerror=null; this.src='uploads/logo.webp';">
            </div>
            <h1><span>Team 5:02</span> STI CETS Web Installer</h1>
        </div>
        <span style="font-size: 11px; font-weight: 900; color: var(--text-gray); text-transform: uppercase;">Deployment Utility</span>
    </div>

    <div class="container">
        <div class="installer-box">
            <?php if ($step === 1): ?>
                <h2>Deploy System Framework</h2>
                <p>This utility will fetch and deploy the latest production package from the 5:02 Studio cloud repository into your environment.</p>

                <?php if ($error): ?>
                    <div class="error-box"><b>Error Details:</b><br><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="POST">
                    <button type="submit" name="install" class="btn-install" <?php echo !empty($error) ? 'disabled style="opacity:0.5; cursor:not-allowed;"' : ''; ?>>📥 Download & Deploy Latest System</button>
                </form>

            <?php elseif ($step === 2): ?>
                <h2 style="color: var(--green);">🎉 Installation Successful!</h2>
                <p>The latest framework has been unpacked successfully.<br>You may now proceed to configure your campus database and initialize system accounts.</p>
                <a href="syssetup.php" class="btn-launch">Run Setup Wizard</a>
            <?php endif; ?>
        </div>
    </div>

    <div class="credit-footer">
        This is a project of <a href="https://www.facebook.com/502StudioSTICubao" target="_blank">Team 5:02 Studio</a> of STI College Cubao spearheaded by Mr. Jan Allen Dela Cruz in collaboration with Mr. Rodolfo Ivan Porwelos Maaño, the MIS Team, the Faculty, the Admins, and the STI College Cubao Marketing Team.<br>Project of 2025, All-Rights Reserved.
    </div>

</body>
</html>