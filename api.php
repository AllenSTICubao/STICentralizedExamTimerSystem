<?php
// 🛡️ OUTPUT BUFFERING: Protects JSON from PHP warnings
ob_start();
error_reporting(0); 
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

require_once 'db.php';

// Failsafe if DB connection fails from db.php
if (!isset($pdo)) {
    ob_end_clean();
    echo json_encode(['error' => 'Database connection failed. Please check db.php credentials.']);
    exit;
}

if (isset($_GET['action'])) {
    
    // 📡 GET ALL DATA (For TVs and MCR)
    if ($_GET['action'] === 'get_all') {
        $response = ['settings' => [], 'schedules' => [], 'logs' => []];
        
        try {
            // 1. Fetch Settings
            $stmtSet = $pdo->query("SELECT * FROM exam_settings");
            while($row = $stmtSet->fetch()) {
                $val = $row['setting_value'];
                if (in_array($row['setting_key'], ['exam_days', 'programs'])) {
                    $response['settings'][$row['setting_key']] = json_decode($val, true) ?? [];
                } else {
                    $response['settings'][$row['setting_key']] = $val;
                }
            }
            $response['settings']['exam_mode'] = ($response['settings']['exam_mode'] ?? 'false') === 'true';
            $response['settings']['show_qr'] = ($response['settings']['show_qr'] ?? 'true') === 'true';

            // 2. Fetch Schedules
            $stmtSched = $pdo->query("SELECT * FROM exam_schedules ORDER BY start_time ASC");
            while($row = $stmtSched->fetch()) {
                $block_start = $row['start_time'] - 1800; // Reverse engineer block start
                $row['block_time'] = date('H:i', $block_start);
                $response['schedules'][] = $row;
            }

            // 3. Fetch Live Logs (Last 50)
            $stmtLogs = $pdo->query("SELECT * FROM exam_logs ORDER BY created_at DESC LIMIT 50");
            $logs = [];
            while($row = $stmtLogs->fetch()) {
                $time = date('h:i:s A', strtotime($row['created_at']));
                $logs[] = "[$time] {$row['log_text']}";
            }
            $response['logs'] = array_reverse($logs);

        } catch (Exception $e) {
            $response['error'] = 'Query failed: ' . $e->getMessage();
        }

        ob_end_clean();
        header('Content-Type: application/json');
        echo json_encode($response);
        exit;
    }

    // 🔄 CHECK SYSTEM UPDATE ENDPOINT
if (isset($_GET['action']) && $_GET['action'] === 'check_update') {
    header('Content-Type: application/json');
    
    // 1. Basahin ang local version.json
    $localVersion = "1.0.0";
    if (file_exists('version.json')) {
        $vData = json_decode(file_get_contents('version.json'), true);
        if (isset($vData['version'])) {
            $localVersion = $vData['version'];
        }
    }

    // 2. Kunin ang check.json mula sa 502 Studio cloud gamit ang cURL
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, 'https://502studio.tech/updates/check.json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $remoteJson = curl_exec($ch);
    curl_close($ch);

    $remoteData = $remoteJson ? json_decode($remoteJson, true) : null;
    $remoteVersion = $remoteData['version'] ?? $localVersion;

    // 3. Ikumpara ang versions (gamit ang version_compare para accurate)
    $updateAvailable = version_compare($remoteVersion, $localVersion, '>');

    echo json_encode([
        'update_available' => $updateAvailable,
        'local_version' => $localVersion,
        'remote_version' => $remoteVersion,
        'notes' => $remoteData['notes'] ?? 'Performance improvements and bug fixes.'
    ]);
    exit;
}

    // 📡 UPDATE EXAM STATUS (From TV or MCR)
    if ($_GET['action'] === 'status') {
        $data = json_decode(file_get_contents("php://input"), true);
        if (isset($data['id']) && isset($data['value'])) {
            $id = $data['id'];
            $newStatus = $data['value'];
            $now = time();

            if ($newStatus === 'ongoing') {
                $pdo->prepare("UPDATE exam_schedules SET status = 'ongoing', actual_start = ? WHERE id = ?")->execute([$now, $id]);
            } elseif ($newStatus === 'sync_start') {
                // Force sync to exact scheduled start time
                $pdo->prepare("UPDATE exam_schedules SET status = 'ongoing', actual_start = start_time WHERE id = ?")->execute([$id]);
            } elseif ($newStatus === 'done') {
                $pdo->prepare("UPDATE exam_schedules SET status = 'done' WHERE id = ?")->execute([$id]);
            } elseif ($newStatus === 'pending') {
                $pdo->prepare("UPDATE exam_schedules SET status = 'pending', actual_start = NULL WHERE id = ?")->execute([$id]);
            }
            
            ob_end_clean();
            echo json_encode(['success' => true]);
            exit;
        }
    }

    // 📡 SET PROCTOR
    if ($_GET['action'] === 'set_proctor') {
        $data = json_decode(file_get_contents("php://input"), true);
        if (isset($data['id']) && isset($data['proctor_name'])) {
            $pdo->prepare("UPDATE exam_schedules SET proctor = ? WHERE id = ?")->execute([$data['proctor_name'], $data['id']]);
            ob_end_clean();
            echo json_encode(['success' => true]);
            exit;
        }
    }

    // 📡 WRITE LOG
    if ($_GET['action'] === 'write_log') {
        $data = json_decode(file_get_contents("php://input"), true);
        if (isset($data['log'])) {
            $pdo->prepare("INSERT INTO exam_logs (log_text) VALUES (?)")->execute([$data['log']]);
            ob_end_clean();
            echo json_encode(['success' => true]);
            exit;
        }
    }
}
?>