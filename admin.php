<?php
session_start();
date_default_timezone_set('Asia/Manila');

// 🔄 REMOTE UPDATE PROXY ENDPOINT (Bypasses CORS)
if (isset($_GET['action']) && $_GET['action'] === 'fetch_check_json') {
    header('Content-Type: application/json');
    $ch = curl_init('https://502studio.tech/updates/check.json');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    $response = curl_exec($ch);
    curl_close($ch);
    echo $response ? $response : json_encode(["error" => "unreachable"]);
    exit;
}

// 📦 LOAD LOCAL VERSION SAFELY
$currentVersion = "1.0.0";
if (file_exists('version.json')) {
    $vData = json_decode(file_get_contents('version.json'), true);
    if (isset($vData['version'])) $currentVersion = $vData['version'];
}

// 🛠️ AUTO-SETUP REDIRECT
if (!file_exists('db.php')) {
    header('Location: setup.php');
    exit;
}

// 🧠 THE CONNECTION: Direct to SQL Database via local db.php
require_once 'db.php'; 

// 🚑 AUTO-PATCH OLD DATABASES (Prevents Error 500!)
try {
    $pdo->exec("ALTER TABLE exam_accounts ADD COLUMN role VARCHAR(20) DEFAULT 'ADMIN'");
    $pdo->exec("UPDATE exam_accounts SET role = 'PROCTOR' WHERE username = 'PROCTOR'");
} catch (PDOException $e) { 
    // Ignore error silently if the column already exists
}

// 🚨 EMERGENCY FIX: FORCE RESET ADMIN ACCOUNT
$pdo->exec("INSERT INTO exam_accounts (username, password_hash, role) VALUES ('ADMIN', '" . password_hash('admin502', PASSWORD_DEFAULT) . "', 'ADMIN') ON DUPLICATE KEY UPDATE password_hash = '" . password_hash('admin502', PASSWORD_DEFAULT) . "'");

// 📁 UPLOAD HELPER
$upload_dir = 'uploads/';
function handleUpload($fileInput, $prefix = 'media_') {
    global $upload_dir;
    if (!is_dir($upload_dir)) mkdir($upload_dir, 0775, true);
    if (isset($fileInput) && $fileInput['error'] === UPLOAD_ERR_OK) {
        $ext = strtolower(pathinfo($fileInput['name'], PATHINFO_EXTENSION));
        if(in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'])) {
            $filename = uniqid($prefix) . '.' . $ext;
            $destination = $upload_dir . $filename;
            if (move_uploaded_file($fileInput['tmp_name'], $destination)) return $destination;
        }
    }
    return null;
}

// 📄 DOWNLOAD CSV TEMPLATE
if (isset($_GET['download_template'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="STI_Exam_Template.csv"');
    echo "SECTION,SUBJECT,DAY,TIME,ROOM,PROCTOR\n";
    echo "BACOMM201,WEB DEVELOPMENT,1,08:00,301,DELA CRUZ\n";
    echo "BSIT301/BSIT302,DATABASE MGMT,2,13:00,405,SANTOS\n";
    exit;
}

// 🗃️ EXPORT JSON (Full System Backup)
if (isset($_GET['export'])) {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="STI_System_Backup_' . date('Ymd_His') . '.json"');
    
    $export = ["accounts" => [], "settings" => [], "schedules" => []];
    foreach($pdo->query("SELECT * FROM exam_accounts") as $r) $export['accounts'][$r['username']] = $r['password_hash'];
    foreach($pdo->query("SELECT * FROM exam_settings") as $r) {
        $dec = json_decode($r['setting_value'], true);
        $export['settings'][$r['setting_key']] = (json_last_error() === JSON_ERROR_NONE && is_array($dec)) ? $dec : $r['setting_value'];
    }
    foreach($pdo->query("SELECT * FROM exam_schedules ORDER BY start_time ASC") as $r) $export['schedules'][] = $r;
    
    echo json_encode($export, JSON_PRETTY_PRINT);
    exit;
}

// 🔐 LOGIN HANDLER
$error = null;
if (isset($_POST['login'])) {
    $user = trim($_POST['username']);
    $pass = $_POST['password'];
    $stmt = $pdo->prepare("SELECT password_hash FROM exam_accounts WHERE username = ?");
    $stmt->execute([$user]);
    $hash = $stmt->fetchColumn();
    
    if ($hash && password_verify($pass, $hash)) {
        $_SESSION['exam_admin'] = $user;
        header("Location: admin.php"); exit;
    } else { $error = "ACCESS DENIED. INVALID CREDENTIALS."; }
}
if (isset($_GET['logout'])) { session_destroy(); header("Location: admin.php"); exit; }

// 🛑 GATEKEEPER
if (!isset($_SESSION['exam_admin'])) {
    echo '<!DOCTYPE html><html><head><title>STI Centralized Exam | Login</title><meta name="viewport" content="width=device-width, initial-scale=1.0"><link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&family=Oswald:wght@700&display=swap" rel="stylesheet"><style>body{background:#f4f7f9;color:#003666;font-family:"Montserrat",sans-serif;display:flex;justify-content:center;align-items:center;height:100vh;margin:0;} .login-box{background:#fff;padding:50px 40px;border-radius:20px;width:100%;max-width:380px;box-shadow:0 15px 35px rgba(0,54,102,0.1);text-align:center;} h2{font-family:"Oswald";margin:0 0 5px 0;font-size:32px;letter-spacing:1px;color:#003666;text-transform:uppercase;} input{padding:15px;margin:10px 0 20px;width:100%;box-sizing:border-box;background:#f4f7f9;border:1px solid #e1e8ed;color:#003666;font-family:"Montserrat";font-size:12px;font-weight:700;border-radius:10px;} input:focus{border-color:#003666;outline:none;} button{background:linear-gradient(135deg, #fDD000, #ffea00);color:#003666;border:none;font-weight:900;font-family:"Oswald";font-size:18px;cursor:pointer;padding:15px;width:100%;letter-spacing:2px;border-radius:50px;box-shadow:0 5px 15px rgba(253,208,0,0.4);transition:0.3s;} button:hover{transform:translateY(-3px);box-shadow:0 8px 20px rgba(253,208,0,0.6);}</style></head><body><div class="login-box"><h2>SYSTEM LOGIN</h2><p style="color:#888;font-size:10px;letter-spacing:3px;margin-bottom:30px;font-weight:700;text-transform:uppercase;">STI Command Center</p>';
    if(isset($error)) echo "<div style='background:rgba(255,11,47,0.1);color:#FF0B2F;padding:15px;margin-bottom:20px;font-size:12px;font-weight:900;letter-spacing:1px;border-radius:10px;'>$error</div>";
    echo '<form method="POST"><input type="text" name="username" placeholder="USERNAME" required><input type="password" name="password" placeholder="PASSWORD" required><button type="submit" name="login">AUTHENTICATE</button></form></div></body></html>';
    exit;
}

// ⚙️ HELPER: Update Setting in SQL
function updateSetting($pdo, $key, $val) {
    $stmt = $pdo->prepare("INSERT INTO exam_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = ?");
    $stmt->execute([$key, $val, $val]);
}

$message = "";

// 👤 ADMIN ACCOUNT MANAGER
if (isset($_POST['add_user'])) {
    $new_user = trim($_POST['new_username']);
    $new_pass = $_POST['new_password'];
    if (!empty($new_user) && !empty($new_pass)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM exam_accounts WHERE username = ?");
        $stmt->execute([$new_user]);
        if ($stmt->fetchColumn() > 0) {
            $message = "<div class='alert error'>USERNAME ALREADY EXISTS</div>";
        } else {
            $stmt = $pdo->prepare("INSERT INTO exam_accounts (username, password_hash, role) VALUES (?, ?, 'ADMIN')");
            $stmt->execute([$new_user, password_hash($new_pass, PASSWORD_DEFAULT)]);
            $message = "<div class='alert success'>NEW ADMIN ACCOUNT CREATED</div>";
        }
    }
}

if (isset($_GET['delete_user'])) {
    $del_user = $_GET['delete_user'];
    if ($del_user === $_SESSION['exam_admin'] || $del_user === 'PROCTOR') { 
        $message = "<div class='alert error'>CANNOT DELETE THIS ACCOUNT</div>"; 
    } else {
        $stmt = $pdo->prepare("DELETE FROM exam_accounts WHERE username = ?");
        $stmt->execute([$del_user]);
        header("Location: admin.php"); exit;
    }
}

// 🔑 PROCTOR PASSWORD MANAGER
if (isset($_POST['update_proctor'])) {
    $new_pass = $_POST['proc_pass'];
    if (!empty($new_pass)) {
        $stmt = $pdo->prepare("UPDATE exam_accounts SET password_hash = ? WHERE username = 'PROCTOR'");
        $stmt->execute([password_hash($new_pass, PASSWORD_DEFAULT)]);
        $message = "<div class='alert success'>PROCTOR CLASSROOM PASSWORD UPDATED</div>";
    }
}

// 🎛️ GLOBAL SETTINGS
if (isset($_POST['update_settings'])) {
    $exam_days = ["1" => $_POST['day_1'], "2" => $_POST['day_2'], "3" => $_POST['day_3'], "4" => $_POST['day_4'], "5" => $_POST['day_5']];
    updateSetting($pdo, 'exam_days', json_encode($exam_days));
    updateSetting($pdo, 'show_qr', isset($_POST['show_qr']) ? 'true' : 'false');
    updateSetting($pdo, 'qr_link', trim($_POST['qr_link']));
    
    $progs = [];
    $lines = explode("\n", $_POST['program_aliases']);
    foreach($lines as $line) {
        if(strpos($line, '=') !== false) {
            list($k, $v) = explode('=', $line, 2);
            $progs[strtoupper(trim($k))] = trim($v);
        }
    }
    updateSetting($pdo, 'programs', json_encode($progs));

    $l_up = handleUpload($_FILES['logo_file'], 'logo_');
    if($l_up) updateSetting($pdo, 'logo', $l_up);
    elseif(!empty($_POST['logo_link'])) updateSetting($pdo, 'logo', trim($_POST['logo_link']));

    $message = "<div class='alert success'>GLOBAL CONFIGURATION SAVED</div>";
}

function calculateExamTimes($dayStr, $timeStr, $pdo) {
    $stmt = $pdo->prepare("SELECT setting_value FROM exam_settings WHERE setting_key = 'exam_days'");
    $stmt->execute();
    $days_json = $stmt->fetchColumn();
    $exam_days = $days_json ? json_decode($days_json, true) : [];
    
    $dateStr = $exam_days[$dayStr] ?? "";
    if(empty($dateStr)) return false;
    
    $block_start = strtotime("$dateStr $timeStr"); 
    if(!$block_start) return false;
    
    $start_time = $block_start + 1800; // 30m buffer for Admin Math
    $end_time = $block_start + 5400;   // 1hr exam
    return ["start" => $start_time, "end" => $end_time, "block_time" => $timeStr];
}

// ➕ ADD SINGLE SCHEDULE (MANUAL)
if (isset($_POST['add_schedule'])) {
    $times = calculateExamTimes($_POST['day'], $_POST['block_time'], $pdo);
    if ($times) {
        $progCode = strtoupper(trim($_POST['prog_code']));
        $secNum = trim($_POST['sec_num']); 
        $sectionCode = $progCode . $secNum; 
        $proctor = trim($_POST['proctor'] ?? 'TBA');
        
        $stmt = $pdo->prepare("INSERT INTO exam_schedules (id, day, room, section_code, prog_code, subject, start_time, end_time, status, proctor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
        $stmt->execute([uniqid('ex_'), $_POST['day'], strtoupper(trim($_POST['room'])), $sectionCode, $progCode, trim($_POST['subject']), $times['start'], $times['end'], $proctor]);
        
        $message = "<div class='alert success'>EXAM SCHEDULE FOR $sectionCode ENQUEUED</div>";
    } else { $message = "<div class='alert error'>ERROR: SET THE DATE FOR DAY {$_POST['day']} IN SETTINGS.</div>"; }
}

// 📝 ADD VIA EXCEL-LIKE GRID
if (isset($_POST['add_grid_schedules'])) {
    $sections = $_POST['grid_sec'] ?? [];
    $days = $_POST['grid_day'] ?? [];
    $times = $_POST['grid_time'] ?? [];
    $rooms = $_POST['grid_room'] ?? [];
    $subjs = $_POST['grid_subj'] ?? [];
    $proctors = $_POST['grid_proctor'] ?? [];

    $addedCount = 0;
    $stmt = $pdo->prepare("INSERT INTO exam_schedules (id, day, room, section_code, prog_code, subject, start_time, end_time, status, proctor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
    
    for ($i = 0; $i < count($sections); $i++) {
        $rawSec = trim($sections[$i]);
        if(empty($rawSec)) continue; 
        
        $rawSec = strtoupper(str_replace(' ', '', $rawSec));
        $progCode = preg_replace('/[0-9]+/', '', $rawSec); 
        $day = trim($days[$i]);
        $time = trim($times[$i]);
        $room = strtoupper(trim($rooms[$i]));
        $subj = trim($subjs[$i]);
        $proctor = trim($proctors[$i] ?? 'TBA');
        if(empty($proctor)) $proctor = 'TBA';

        $calcTimes = calculateExamTimes($day, $time, $pdo);
        if ($calcTimes) {
            $stmt->execute([uniqid('ex_'), $day, $room, $rawSec, $progCode, $subj, $calcTimes['start'], $calcTimes['end'], $proctor]);
            $addedCount++;
        }
    }

    if ($addedCount > 0) {
        $message = "<div class='alert success'>$addedCount EXAM(S) ENQUEUED VIA GRID</div>";
    }
}

// ✏️ EDIT SCHEDULE
if (isset($_POST['update_schedule'])) {
    $id = $_POST['edit_id'];
    $times = calculateExamTimes($_POST['edit_day'], $_POST['edit_time'], $pdo);
    if ($times) {
        $progCode = strtoupper(trim($_POST['edit_prog'])); 
        $secNum = trim($_POST['edit_sec']);
        $sectionCode = $progCode . $secNum;
        $proctor = trim($_POST['edit_proctor'] ?? 'TBA');
        
        $stmt = $pdo->prepare("UPDATE exam_schedules SET room=?, subject=?, prog_code=?, section_code=?, day=?, start_time=?, end_time=?, proctor=? WHERE id=?");
        $stmt->execute([strtoupper(trim($_POST['edit_room'])), trim($_POST['edit_subj']), $progCode, $sectionCode, $_POST['edit_day'], $times['start'], $times['end'], $proctor, $id]);
        
        $message = "<div class='alert success'>SCHEDULE UPDATED SUCCESSFULLY</div>";
    } else { $message = "<div class='alert error'>ERROR: PLEASE CONFIGURE DATES IN SETTINGS.</div>"; }
}

// 🗃️ SMART CSV IMPORTER
if (isset($_POST['import_csv']) && isset($_FILES['csv_file']) && $_FILES['csv_file']['error'] === UPLOAD_ERR_OK) {
    $handle = fopen($_FILES['csv_file']['tmp_name'], "r");
    $addedCount = 0; $replacedCount = 0; $errors = 0;
    
    if ($handle !== FALSE) {
        $isData = false;
        if (isset($_POST['clear_old'])) {
            $pdo->exec("TRUNCATE TABLE exam_schedules");
        }
        
        while (($row = fgetcsv($handle, 1000, ",")) !== FALSE) {
            if (!$isData) {
                if (isset($row[0]) && stripos(trim($row[0]), 'SECTION') !== false) {
                    $isData = true;
                }
                continue;
            }
            
            if (empty(trim($row[0]))) continue;
            
            $rawSec = strtoupper(trim($row[0])); 
            $subj = trim($row[1]);
            $rawDay = trim($row[2]);
            $rawTime = trim($row[3]);
            $room = strtoupper(trim($row[4]));
            $proctor = isset($row[5]) ? trim($row[5]) : 'TBA'; 
            if(empty($proctor)) $proctor = 'TBA';
            
            $day = 1;
            if (preg_match('/Day\s*(\d+)/i', $rawDay, $matches)) {
                $day = $matches[1];
            } elseif (is_numeric(trim($rawDay))) {
                $day = trim($rawDay);
            }
            
            $timeParts = explode('-', $rawTime);
            $timeStart = trim($timeParts[0]);
            
            $firstSec = explode('/', $rawSec)[0];
            $progCode = preg_replace('/[0-9]+/', '', $firstSec);
            $newSecsFormatted = str_replace('/', ', ', $rawSec);
            
            $times = calculateExamTimes($day, $timeStart, $pdo);
            if ($times) {
                $stmtFind = $pdo->prepare("SELECT * FROM exam_schedules WHERE room = ? AND start_time = ? AND section_code = ?");
                $stmtFind->execute([$room, $times['start'], $newSecsFormatted]);
                $existing = $stmtFind->fetch();

                if ($existing) {
                    $pdo->prepare("UPDATE exam_schedules SET subject = ?, proctor = ? WHERE id = ?")->execute([$subj, $proctor, $existing['id']]);
                    $replacedCount++;
                } else {
                    $stmtInsert = $pdo->prepare("INSERT INTO exam_schedules (id, day, room, section_code, prog_code, subject, start_time, end_time, status, proctor) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)");
                    $stmtInsert->execute([uniqid('ex_'), $day, $room, $newSecsFormatted, $progCode, $subj, $times['start'], $times['end'], $proctor]);
                    $addedCount++;
                }
            } else {
                $errors++;
            }
        }
        fclose($handle); 
        $message = "<div class='alert success'>IMPORT SUCCESS: $addedCount ADDED, $replacedCount UPDATED. ($errors ERRORS)</div>";
    }
}

// ❌ ACTIONS
if (isset($_GET['delete'])) {
    $pdo->prepare("DELETE FROM exam_schedules WHERE id = ?")->execute([$_GET['delete']]);
    header("Location: admin.php"); exit;
}
if (isset($_GET['reset_status'])) {
    $pdo->prepare("UPDATE exam_schedules SET status = 'pending', actual_start = NULL WHERE id = ?")->execute([$_GET['reset_status']]);
    header("Location: admin.php"); exit;
}
if (isset($_POST['clear_schedules'])) {
    $pdo->exec("DELETE FROM exam_schedules");
    $message = "<div class='alert success'>ALL SCHEDULES WIPED</div>";
}

// ============================================================================
// 💾 FETCH DATA FOR HTML UI
// ============================================================================
$data = ['accounts' => [], 'settings' => [], 'schedules' => []];

$stmtAcc = $pdo->query("SELECT * FROM exam_accounts WHERE role='ADMIN'");
while ($row = $stmtAcc->fetch()) $data['accounts'][$row['username']] = $row['password_hash'];

$stmtSet = $pdo->query("SELECT * FROM exam_settings");
while ($row = $stmtSet->fetch()) {
    $val = $row['setting_value'];
    if (in_array($row['setting_key'], ['exam_days', 'programs'])) {
        $data['settings'][$row['setting_key']] = json_decode($val, true) ?? [];
    } else {
        $data['settings'][$row['setting_key']] = $val;
    }
}

$stmtSched = $pdo->query("SELECT * FROM exam_schedules ORDER BY start_time ASC");
while ($row = $stmtSched->fetch()) {
    $block_start = $row['start_time'] - 1800; 
    $row['block_time'] = date('H:i', $block_start); 
    $row['term'] = substr(str_replace($row['prog_code'], '', $row['section_code']), 0, 1);
    $row['block'] = substr(str_replace($row['prog_code'], '', $row['section_code']), 1);
    $data['schedules'][] = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exam Admin | STI Centralized System</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&family=Oswald:wght@500;700&display=swap" rel="stylesheet">
    <style>
        /* 🎨 OPTIMIZED CSS FOR PERFORMANCE */
        :root { --sti-yellow: #fDD000; --sti-blue: #003666; --white: #ffffff; --bg: #f4f7f9; --text-dark: #001f3f; --text-gray: #657786; --border: #e1e8ed; --green: #00b35c;}
        
        body { font-family: 'Montserrat', sans-serif; margin: 0; padding: 0; color: var(--text-dark); background: var(--bg); position: relative; overflow-x: hidden;}
        * { box-sizing: border-box; }

        .top-nav { background: var(--white); border-bottom: 1px solid var(--border); display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; position: sticky; top: 0; z-index: 100; box-shadow: 0 4px 20px rgba(0,54,102,0.05);}
        .top-nav h1 { margin: 0; font-family: 'Oswald'; font-size: 22px; color: var(--sti-blue); letter-spacing: 1px; text-transform: uppercase; display: flex; align-items: center;}
        
        .container { padding: 30px 40px; max-width: 1400px; margin: auto; }
        .grid { display: grid; grid-template-columns: 480px 1fr; gap: 30px; align-items: start;}
        
        .card { background: var(--white); padding: 30px; border: 1px solid var(--border); border-radius: 16px; box-shadow: 0 5px 20px rgba(0,54,102,0.03); }
        .card-title { font-family: 'Oswald'; color: var(--sti-blue); font-size: 20px; letter-spacing: 1px; margin-top: 0; margin-bottom: 20px; text-transform: uppercase; border-bottom: 2px solid var(--border); padding-bottom: 10px;}
        
        label { display: block; font-size: 10px; font-weight: 900; color: var(--text-gray); text-transform: uppercase; margin: 15px 0 5px 0; letter-spacing: 1px;}
        input[type="text"], input[type="password"], input[type="time"], input[type="date"], select, textarea { width: 100%; padding: 14px; background: #f8f9fa; border: 1px solid var(--border); color: var(--text-dark); font-family: 'Montserrat'; font-size: 13px; font-weight: 700; box-sizing: border-box; transition: 0.3s; border-radius: 8px; }
        input:focus, select:focus, textarea:focus { border-color: var(--sti-blue); outline: none; background: var(--white); box-shadow: 0 0 0 3px rgba(0,54,102,0.1);}
        input[type="file"] { padding: 10px; background: #f8f9fa; font-size: 11px; border: 1px dashed #ccd6dd; width: 100%; box-sizing: border-box; color: var(--text-gray); cursor: pointer; border-radius: 8px;}
        
        .btn { display: inline-block; padding: 15px 20px; border: none; font-weight: 900; font-family: 'Oswald'; font-size: 16px; text-transform: uppercase; cursor: pointer; transition: 0.3s; width: 100%; text-align: center; letter-spacing: 1px; box-sizing: border-box; border-radius: 50px; text-decoration: none;}
        .btn-blue { background: linear-gradient(135deg, var(--sti-blue), #00509e); color: var(--white); box-shadow: 0 5px 15px rgba(0,54,102,0.2);}
        .btn-blue:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(0,54,102,0.3); }
        .btn-yellow { background: linear-gradient(135deg, var(--sti-yellow), #ffea00); color: var(--sti-blue); box-shadow: 0 5px 15px rgba(253,208,0,0.3);}
        .btn-yellow:hover { transform: translateY(-2px); box-shadow: 0 8px 20px rgba(253,208,0,0.5); }
        .btn-red { background: rgba(255,11,47,0.1); color: #FF0B2F; font-size: 12px; padding: 10px; border-radius: 8px;}
        .btn-red:hover { background: #FF0B2F; color: var(--white); }
        
        .btn-sm { padding: 6px 12px; font-size: 10px; background: #f4f7f9; border: 1px solid var(--border); color: var(--text-gray); font-family: 'Montserrat'; font-weight: 700; border-radius: 6px; cursor: pointer; text-decoration: none; transition: 0.2s;}
        .btn-sm:hover { border-color: var(--sti-blue); color: var(--sti-blue); }
        .btn-sm.del:hover { border-color: #FF0B2F; background: rgba(255,11,47,0.1); color: #FF0B2F; }

        .section-group { margin-bottom: 25px; border: 1px solid var(--border); border-radius: 12px; background: var(--white); overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,0.02);}
        .group-header { background: var(--sti-blue); color: var(--white); padding: 10px 20px; font-family: 'Oswald'; font-size: 18px; letter-spacing: 1px; display: flex; justify-content: space-between; align-items: center; border-left: 6px solid var(--sti-yellow);}
        .group-header span { font-family: 'Montserrat'; font-size: 11px; background: rgba(255,255,255,0.2); padding: 3px 10px; border-radius: 4px; font-weight: 700;}
        .sched-item { display: grid; grid-template-columns: 80px 1fr 140px 140px; gap: 15px; border-bottom: 1px solid var(--border); padding: 15px 20px; align-items: center;}
        .sched-item:last-child { border-bottom: none; }
        .s-ongoing { border-left: 4px solid var(--sti-yellow); background: #fffcf0; }
        .s-done { opacity: 0.5; filter: grayscale(1); }
        .s-room { font-family: 'Oswald'; font-size: 22px; color: var(--sti-blue); font-weight: 700; line-height: 1;}
        .s-room span { display: block; font-family: 'Montserrat'; font-size: 9px; color: var(--text-gray); letter-spacing: 1px; font-weight: 700;}
        .s-time { font-size: 11px; color: var(--text-dark); font-weight: 700; margin-top: 4px;}
        .s-subj { font-weight: 900; font-size: 14px; text-transform: uppercase; color: var(--sti-blue); }
        
        .badge { padding: 5px 10px; font-size: 10px; font-weight: 900; letter-spacing: 1px; font-family: 'Oswald'; text-align: center; border-radius: 6px;}
        .b-pen { background: #e1e8ed; color: var(--text-gray); }
        .b-ong { background: var(--sti-yellow); color: var(--sti-dark); }
        .b-don { background: #00b35c; color: var(--white); }
        
        .alert { padding: 15px; font-weight: 900; text-align: center; margin-bottom: 25px; letter-spacing: 1px; text-transform: uppercase; font-family: 'Montserrat'; font-size: 12px; border-radius: 10px;}
        .alert.success { background: rgba(0, 230, 118, 0.1); border: 1px solid #00b35c; color: #00b35c; }
        .alert.error { background: rgba(255, 11, 47, 0.1); border: 1px solid #FF0B2F; color: #FF0B2F; }

        /* MODALS */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 31, 63, 0.7); z-index: 1000; align-items: center; justify-content: center;}
        .modal-box { background: var(--white); padding: 30px; border-radius: 16px; width: 100%; max-width: 500px; box-shadow: 0 15px 40px rgba(0,0,0,0.3); border: 1px solid var(--border);}
        .modal-box-large { max-width: 1200px; width: 95%; padding: 40px; }

        /* EXCEL-LIKE GRID TABLE */
        .grid-table-container { width: 100%; overflow-x: auto; background: #f8f9fa; border: 1px solid var(--border); border-radius: 8px; margin-bottom: 15px;}
        .grid-table { width: 100%; border-collapse: collapse; min-width: 600px;}
        .grid-table th { background: var(--sti-blue); color: var(--white); font-family: 'Oswald'; font-size: 12px; padding: 10px; text-align: left; letter-spacing: 1px;}
        .grid-table td { padding: 5px; border-bottom: 1px solid var(--border); }
        .grid-table input, .grid-table select { width: 100%; padding: 10px; margin: 0; font-size: 12px; border-radius: 4px; border: 1px solid #ccd6dd; background: var(--white);}
        .grid-table input:focus { border-color: var(--sti-blue); }
        
        .btn-add-row { background: #e1e8ed; color: var(--sti-dark); border: none; padding: 10px; font-family: 'Montserrat'; font-weight: 900; font-size: 10px; border-radius: 6px; cursor: pointer; width: 100%; transition: 0.2s;}
        .btn-add-row:hover { background: var(--sti-yellow); }
        .btn-del-row { background: rgba(255,11,47,0.1); color: var(--red); border: none; padding: 10px; border-radius: 6px; cursor: pointer; transition: 0.2s;}
        .btn-del-row:hover { background: var(--red); color: var(--white); }

        .credit-footer { text-align: center; font-size: 12px; color: #777; line-height: 1.6; max-width: 800px; padding: 20px; margin: 40px auto 20px auto;}
        .credit-footer a { color: var(--sti-blue); font-weight: bold; text-decoration: none; transition: 0.2s;}
        .credit-footer a:hover { color: var(--sti-yellow); text-decoration: underline;}
    </style>
</head>
<body>

    <div id="edit-modal" class="modal-overlay">
    <div class="modal-box">
        <h2 class="card-title">✏️ EDIT SCHEDULE</h2>
        <form method="POST" action="admin.php">
            <input type="hidden" name="edit_id" id="edit_id">
            <div style="display:flex; gap:10px;">
                <div style="flex:2;"><label>PROG CODE</label><input type="text" name="edit_prog" id="edit_prog" required></div>
                <div style="flex:1;"><label>SEC NUM</label><input type="text" name="edit_sec" id="edit_sec" required></div>
            </div>
            <div style="display:flex; gap:10px; margin-top:5px;">
                <div style="flex:2;"><label>SUBJECT</label><input type="text" name="edit_subj" id="edit_subj" required></div>
                <div style="flex:1;"><label>ROOM</label><input type="text" name="edit_room" id="edit_room" required></div>
                <div style="flex:1;"><label>PROCTOR</label><input type="text" name="edit_proctor" id="edit_proctor" required></div>
            </div>
            <div style="display:flex; gap:10px; align-items: flex-end; margin-top:5px;">
                    <div style="flex:1;">
                        <label>DAY</label>
                        <select name="edit_day" id="edit_day" required>
                            <?php for($i=1; $i<=5; $i++): ?><option value="<?php echo $i; ?>">Day <?php echo $i; ?></option><?php endfor; ?>
                        </select>
                    </div>
                    <div style="flex:1;"><label>TIME</label><input type="time" name="edit_time" id="edit_time" required></div>
                </div>
                <div style="display:flex; gap:10px; margin-top:25px;">
                    <button type="button" class="btn btn-red" style="font-size:14px; padding:15px; border-radius:50px; background:rgba(255,11,47,0.1);" onclick="document.getElementById('edit-modal').style.display='none'">CANCEL</button>
                    <button type="submit" name="update_schedule" class="btn btn-blue">SAVE CHANGES</button>
                </div>
            </form>
        </div>
    </div>

    <div id="batch-modal" class="modal-overlay">
    <div class="modal-box modal-box-large">
        <h2 class="card-title" style="margin-bottom: 5px;">➕ BATCH EXAM ENTRY</h2>
        <p style="font-size: 12px; color: var(--text-gray); margin-top: 0; margin-bottom: 20px;">Use the Tab key to quickly navigate through columns. Empty rows will be ignored.</p>
        <form method="POST" action="admin.php">
            <div class="grid-table-container">
                <table class="grid-table" id="input-grid">
                    <thead>
                        <tr>
                            <th style="width:15%;">SECTION (e.g. BACOMM201)</th>
                            <th style="width:10%;">DAY</th>
                            <th style="width:15%;">TIME (Start)</th>
                            <th style="width:10%;">ROOM</th>
                            <th style="width:25%;">SUBJECT</th>
                            <th style="width:20%;">PROCTOR</th>
                            <th style="width:5%;"></th>
                        </tr>
                    </thead>
                    <tbody id="grid-body">
                        <?php for($r=0; $r<5; $r++): ?>
                        <tr>
                            <td><input type="text" name="grid_sec[]" placeholder="BACOMM201" autocomplete="off"></td>
                            <td><select name="grid_day[]"><?php for($i=1; $i<=5; $i++) echo "<option value='$i'>$i</option>"; ?></select></td>
                            <td><input type="time" name="grid_time[]"></td>
                            <td><input type="text" name="grid_room[]" placeholder="502" autocomplete="off"></td>
                            <td><input type="text" name="grid_subj[]" placeholder="Subject Name" autocomplete="off"></td>
                            <td><input type="text" name="grid_proctor[]" placeholder="Dela Cruz" autocomplete="off"></td>
                            <td><button type="button" class="btn-del-row" onclick="this.closest('tr').remove()">X</button></td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>
            </div>
            <button type="button" class="btn-add-row" onclick="addGridRow()">+ ADD ANOTHER ROW</button>
                <div style="display:flex; gap:10px; margin-top:25px; justify-content: flex-end;">
                    <button type="button" class="btn btn-red" style="width:auto; font-size:14px; padding:15px 30px; border-radius:50px; background:rgba(255,11,47,0.1);" onclick="document.getElementById('batch-modal').style.display='none'">CANCEL</button>
                    <button type="submit" name="add_grid_schedules" class="btn btn-blue" style="width:auto; padding:15px 40px;">SAVE TO QUEUE</button>
                </div>
            </form>
            
            <script>
            function addGridRow() {
                const tbody = document.getElementById('grid-body');
                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td><input type="text" name="grid_sec[]" placeholder="BACOMM201" autocomplete="off"></td>
                    <td><select name="grid_day[]"><?php for($i=1; $i<=5; $i++) echo "<option value='$i'>$i</option>"; ?></select></td>
                    <td><input type="time" name="grid_time[]"></td>
                    <td><input type="text" name="grid_room[]" placeholder="502" autocomplete="off"></td>
                    <td><input type="text" name="grid_subj[]" placeholder="Subject Name" autocomplete="off"></td>
                    <td><input type="text" name="grid_proctor[]" placeholder="Dela Cruz" autocomplete="off"></td>
                    <td><button type="button" class="btn-del-row" onclick="this.closest('tr').remove()">X</button></td>
                `;
                tbody.appendChild(tr);
                tr.querySelector('input').focus();
            }
        </script>
    </div>
</div>

<?php
// Load local version safely
$currentVersion = "1.0.0";
if (file_exists('version.json')) {
    $vData = json_decode(file_get_contents('version.json'), true);
    if (isset($vData['version'])) $currentVersion = $vData['version'];
}
if (isset($_GET['update_success'])) {
    $message = "<div class='alert success'>🎉 SYSTEM UPDATED SUCCESSFULLY TO v{$currentVersion}!</div>";
}
?>
<div class="top-nav">
        <div style="display:flex; align-items:center;">
            <img src="<?php echo htmlspecialchars($data['settings']['logo'] ?? 'uploads/logo.webp'); ?>" style="height:35px; object-fit:contain; margin-right:15px;" onerror="this.style.display='none'">
           <h1>STI Centralized Exam Timer System <span style="font-size: 11px; background: var(--sti-yellow); color: var(--sti-dark); padding: 2px 8px; border-radius: 4px; margin-left: 10px; vertical-align: middle;">v<?php echo $currentVersion; ?></span></h1>
        </div>
        <div style="display:flex; align-items:center;">
            <a href="admin-mcr.php" target="_blank" class="btn-sm" style="background:var(--sti-yellow); color:var(--sti-dark); border-color:var(--sti-yellow); padding: 8px 15px; margin-right: 15px;">📺 OPEN MCR</a>
            <span style="font-size:11px; font-weight:900; color:var(--text-gray); margin-right:10px; text-transform:uppercase;">USER: <?php echo htmlspecialchars($_SESSION['exam_admin']); ?></span>
            <a href="?logout=1" class="btn-sm" style="background:var(--sti-blue); color:var(--white); border:none; padding: 10px 20px; border-radius: 8px; text-decoration:none;">LOGOUT</a>
        </div>
    </div>

    <div class="container">
        <?php echo $message; ?>

        <div class="grid">
            <div>
                <div class="card" style="padding-bottom: 25px; text-align: center; border-left: 6px solid var(--sti-yellow);">
                    <h2 class="card-title" style="border:none; margin-bottom: 5px;">⚡ BATCH ENTRY (GRID)</h2>
                    <p style="font-size: 12px; color: var(--text-gray); margin-top: 0; margin-bottom: 20px; font-weight: 600;">Encode multiple exams at once using a spreadsheet-like grid.</p>
                    <button type="button" class="btn btn-blue" onclick="document.getElementById('batch-modal').style.display='flex'">OPEN GRID ENCODER</button>
                </div>

                <div class="card" style="margin-top: 20px;">
                <h2 class="card-title">➕ MANUAL ADD (SINGLE)</h2>
                <form method="POST" action="admin.php">
                    <div style="display:flex; gap:10px;">
                        <div style="flex:2;"><label>PROG CODE (e.g. BACOMM)</label><input type="text" name="prog_code" value="<?php echo htmlspecialchars($_POST['prog_code'] ?? ''); ?>" required></div>
                        <div style="flex:1;"><label>SEC NUM (e.g. 201)</label><input type="text" name="sec_num" placeholder="201" pattern="\d{3,4}" value="<?php echo htmlspecialchars($_POST['sec_num'] ?? ''); ?>" required></div>
                    </div>
                    <div style="display:flex; gap:10px; margin-top: 5px;">
                        <div style="flex:2;"><label>SUBJECT NAME / CODE</label><input type="text" name="subject" placeholder="e.g. CS101 - Programming" required></div>
                        <div style="flex:1;"><label>ROOM</label><input type="text" name="room" placeholder="e.g. 201" value="<?php echo htmlspecialchars($_POST['room'] ?? ''); ?>" required></div>
                        <div style="flex:1;"><label>PROCTOR</label><input type="text" name="proctor" placeholder="e.g. Dela Cruz" value="<?php echo htmlspecialchars($_POST['proctor'] ?? ''); ?>" required></div>
                    </div>
                    <div style="display:flex; gap:10px; align-items: flex-end;">
                            <div style="flex:1;">
                                <label>EXAM DAY</label>
                                <select name="day" required><?php for($i=1; $i<=5; $i++): ?><option value="<?php echo $i; ?>">Day <?php echo $i; ?></option><?php endfor; ?></select>
                            </div>
                            <div style="flex:1;"><label>BLOCK START TIME</label><input type="time" name="block_time" required></div>
                        </div>
                        <button type="submit" name="add_schedule" class="btn btn-yellow" style="margin-top:20px;">ENQUEUE EXAM</button>
                    </form>
                </div>

                <div class="card" style="margin-top: 20px; background: linear-gradient(135deg, #003666, #00509e); border: none; color: white;">
                    <h2 class="card-title" style="color:white; border-bottom-color: rgba(255,255,255,0.2);">📥 MASS IMPORT (CSV)</h2>
                    <p style="font-size: 10px; color: #aaccff; margin-top: 0; font-family: monospace;">Use the standard layout format to upload schedules.<br>
                        <a href="?download_template=1" style="color: var(--sti-yellow); font-weight: bold; text-decoration: none;">Download CSV Template Here</a>
                    </p>
                    <form method="POST" action="admin.php" enctype="multipart/form-data" style="display:flex; flex-direction:column; gap:10px;">
                        <input type="file" name="csv_file" accept=".csv" required style="background: rgba(255,255,255,0.1); border: 1px dashed rgba(255,255,255,0.3); color: white;">
                        <label style="color: white;"><input type="checkbox" name="clear_old" value="1"> Wipe old schedules before importing</label>
                        <button type="submit" name="import_csv" class="btn btn-yellow" style="width:100%; padding: 10px 20px; font-size: 14px; border-radius: 8px; box-shadow:none;">UPLOAD CSV</button>
                    </form>
                </div>

                <div class="card" style="margin-top: 30px;">
                    <h2 class="card-title">⚙️ GLOBAL CONFIGURATION</h2>
                    <form method="POST" action="admin.php" enctype="multipart/form-data">
                        <label style="color:var(--sti-blue);">📚 PROGRAM DICTIONARY</label>
                        <textarea name="program_aliases" rows="4" placeholder="BACOMM=Bachelor of Arts in Communication"><?php 
                            $alias_str = []; foreach($data['settings']['programs'] as $k => $v) { $alias_str[] = "$k=$v"; }
                            echo htmlspecialchars(implode("\n", $alias_str));
                        ?></textarea>

                        <label style="color:var(--sti-blue); margin-top: 20px;">🗓️ MAP EXAM DAYS TO DATES</label>
                        <div class="date-grid" style="margin-bottom: 20px; display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px;">
                            <?php for($i=1; $i<=5; $i++): ?>
                            <div><label style="margin-top:0; font-size:9px;">DAY <?php echo $i; ?></label><input type="date" name="day_<?php echo $i; ?>" value="<?php echo htmlspecialchars($data['settings']['exam_days'][$i] ?? ''); ?>"></div>
                            <?php endfor; ?>
                        </div>
                        
                        <label>GLOBAL BRAND LOGO (STANDBY/LOBBY)</label>
                        <div style="display:flex; gap:10px; margin-bottom: 20px;">
                            <input type="file" name="logo_file" accept="image/*">
                            <input type="text" name="logo_link" value="<?php echo htmlspecialchars($data['settings']['logo'] ?? ''); ?>" placeholder="Or Paste Image URL">
                        </div>

                        <button type="submit" name="update_settings" class="btn btn-yellow" style="margin-top:15px; border-radius: 8px; font-size: 14px;">SAVE SETTINGS</button>
                    </form>
                </div>

                <div class="card" style="margin-top: 30px; border-color: var(--border);">
                    <h2 class="card-title" style="border-bottom:none; margin-bottom:0;">👨‍🏫 PROCTOR & SECURITY</h2>
                    <p style="font-size: 12px; color: var(--text-gray); margin-top: 5px;">Manage the master password that proctors use to unlock TVs.</p>
                    
                    <form method="POST" action="admin.php" style="display:flex; gap:10px; margin-bottom: 25px; padding-bottom: 25px; border-bottom: 1px solid var(--border);">
                        <input type="password" name="proc_pass" placeholder="New Proctor Password" required style="flex:1; padding:10px;">
                        <button type="submit" name="update_proctor" class="btn btn-yellow" style="width:auto; padding: 10px 20px; font-size:12px; border-radius:8px; box-shadow:none;">CHANGE PASSWORD</button>
                    </form>

                    <h2 class="card-title" style="font-size: 16px; border-bottom:none; margin-bottom:0;">ADMINISTRATOR ACCOUNTS</h2>
                    <form method="POST" action="admin.php" style="display:flex; gap:10px; margin: 15px 0;">
                        <input type="text" name="new_username" placeholder="New Admin Username" required style="flex:1; padding:10px;">
                        <input type="password" name="new_password" placeholder="Password" required style="flex:1; padding:10px;">
                        <button type="submit" name="add_user" class="btn btn-blue" style="width:auto; padding: 10px 15px; font-size:12px; border-radius:8px;">ADD</button>
                    </form>
                    
                    <div style="background: #f8f9fa; border: 1px solid var(--border); border-radius: 8px; overflow: hidden;">
                        <?php foreach($data['accounts'] as $uname => $hash): ?>
                            <div style="display:flex; justify-content:space-between; align-items:center; padding: 12px 15px; border-bottom: 1px solid var(--border);">
                                <strong style="color:var(--sti-blue); font-family:'Oswald'; font-size:14px; letter-spacing:1px;"><?php echo htmlspecialchars($uname); ?></strong>
                                <?php if($uname !== $_SESSION['exam_admin']): ?>
                                    <a href="?delete_user=<?php echo urlencode($uname); ?>" class="btn-sm del" onclick="return confirm('Delete this admin account?');">DELETE</a>
                                <?php else: ?>
                                    <span style="font-size:10px; color:var(--green); font-weight:900; background:rgba(0,230,118,0.1); padding:3px 8px; border-radius:4px;">ACTIVE</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>

               <div class="card" style="margin-top: 30px;">
    <h2 class="card-title">🔄 SYSTEM UPDATES</h2>
    <p style="font-size: 12px; color: var(--text-gray);">Check for the latest features and patches from 5:02 Studio.</p>
    <button id="btn-check-updates" class="btn btn-blue" style="font-size:14px; border-radius:8px; padding:12px;" onclick="checkUpdates()">CHECK FOR UPDATES</button>
    <div id="update-status" style="margin-top: 15px; font-size: 12px; font-weight: bold; font-family: 'Montserrat'; text-align: center;"></div>
</div>

<script>
    const localVersion = "<?php echo $currentVersion; ?>";

    function compareVersions(v1, v2) {
        let p1 = v1.split('.').map(Number);
        let p2 = v2.split('.').map(Number);
        for (let i = 0; i < Math.max(p1.length, p2.length); i++) {
            let num1 = p1[i] || 0;
            let num2 = p2[i] || 0;
            if (num1 > num2) return 1;
            if (num1 < num2) return -1;
        }
        return 0;
    }

    async function checkUpdates() {
        const status = document.getElementById('update-status');
        status.style.color = 'var(--sti-blue)';
        status.innerText = "Checking 502studio.tech for updates...";
        
        try {
            // Tawagin ang sariling PHP proxy para iwas CORS block
            let res = await fetch('admin.php?action=fetch_check_json&t=' + Date.now());
            if (!res.ok) throw new Error('Network error');
            
            let data = await res.json();
            if (data.error) throw new Error('Server unreachable');
            
            if (compareVersions(data.version, localVersion) > 0) {
                status.style.color = 'var(--green)';
                status.innerHTML = `✅ Update Available! Version: <b>${data.version}</b> (Current: v${localVersion})<br><span style="color:#555; font-weight:normal; display:block; margin:8px 0;">${data.notes}</span>
                <form method="POST" action="updater.php">
                    <button type="submit" name="run_update" class="btn btn-yellow" style="font-size:12px; padding:10px 20px; border-radius:50px; width:auto;">🚀 INSTALL UPDATE NOW</button>
                </form>`;
            } else {
                status.style.color = 'var(--text-gray)';
                status.innerHTML = `✨ System is up to date. (Current: v${localVersion})`;
            }
        } catch(e) {
            status.style.color = 'var(--red)';
            status.innerText = "❌ Failed to fetch update package. Check server cURL / connection.";
        }
    }
</script>
                <div class="card" style="margin-top: 30px; border-color: var(--border);">
                    <h2 class="card-title" style="color:var(--text-gray); border-bottom:none; margin-bottom:0;">DATABASE MANAGER</h2>
                    <div style="display:flex; gap:10px; margin-top:15px;">
                        <a href="?export=1" class="btn btn-blue" style="padding:12px; font-size:12px; flex:1; border-radius:8px; text-decoration:none;">⬇️ BACKUP JSON</a>
                        <form method="POST" action="admin.php" onsubmit="return confirm('Clear ALL schedules? This cannot be undone.');" style="flex:1;">
                            <button type="submit" name="clear_schedules" class="btn btn-red" style="width:100%; padding:12px; font-family:'Oswald'; letter-spacing:1px; font-size:14px;">🗑️ WIPE SCHEDULES</button>
                        </form>
                    </div>
                </div>
            </div>

            <div>
                <div class="card" style="background: transparent; padding: 0; border: none; box-shadow: none;">
                    <?php 
                    $scheds = $data['schedules'];
                    $groups = [];
                    foreach($scheds as $s) {
                        $grp = $s['section_code'] ?? 'UNGROUPED';
                        if(!isset($groups[$grp])) $groups[$grp] = [];
                        $groups[$grp][] = $s;
                    }
                    ksort($groups); 
                    ?>
                    <div style="background: var(--white); padding: 15px 20px; border: 1px solid var(--border); border-bottom: none; display: flex; align-items: center; gap: 15px; border-radius: 12px 12px 0 0;">
                        <strong style="font-family:'Oswald'; font-size: 20px; color: var(--sti-blue);">QUEUE FILTER</strong>
                        <select id="sectionFilter" onchange="filterQueue()" style="flex:1; padding: 8px 15px;">
                            <option value="ALL">SHOW ALL SECTIONS</option>
                            <?php foreach(array_keys($groups) as $gName): ?>
                                <option value="<?php echo htmlspecialchars($gName); ?>"><?php echo htmlspecialchars($gName); ?></option>
                            <?php endforeach; ?>
                        </select>
                        <span style="background: var(--sti-blue); color: var(--white); padding: 5px 15px; border-radius: 50px; font-size: 12px; font-weight: 900;"><?php echo count($scheds); ?> TOTAL</span>
                    </div>
                    
                    <div id="queue-container" style="background: var(--bg); border: 1px solid var(--border); border-top: none; border-radius: 0 0 12px 12px; padding: 20px;">
                        <?php 
                        if(empty($groups)) echo "<div style='text-align:center; padding:30px; color:var(--text-gray); font-family:Montserrat; font-weight:700; font-size: 14px;'>No exams enqueued.</div>";

                        foreach($groups as $sectionName => $items): 
                            $progCode = $items[0]['prog_code'] ?? '';
                            $fullName = $data['settings']['programs'][$progCode] ?? "PROGRAM";
                            usort($items, function($a, $b) {
                                $rank = ['ongoing' => 1, 'pending' => 2, 'done' => 3];
                                if ($rank[$a['status']] !== $rank[$b['status']]) return $rank[$a['status']] - $rank[$b['status']];
                                return $a['start_time'] - $b['start_time'];
                            });
                        ?>
                        <div class="section-group" data-section="<?php echo htmlspecialchars($sectionName); ?>">
                            <div class="group-header">
                                <?php echo htmlspecialchars($sectionName); ?>
                                <span><?php echo htmlspecialchars($fullName); ?></span>
                            </div>
                            <?php foreach($items as $s): 
                            $status_class = ''; $badge = '';
                            if($s['status'] === 'ongoing') { $status_class = 's-ongoing'; $badge = '<div class="badge b-ong">ONGOING</div>'; }
                            elseif($s['status'] === 'done') { $status_class = 's-done'; $badge = '<div class="badge b-don">COMPLETED</div>'; }
                            else { $badge = '<div class="badge b-pen">WAITING</div>'; }
                            
                            $start = date('h:i A', $s['start_time'] - 1800); // Compute display time natively
                            $end = date('h:i A', $s['end_time']);

                            $e_prog = htmlspecialchars($s['prog_code'] ?? '', ENT_QUOTES);
                            $e_sec = htmlspecialchars(($s['term'] ?? '') . ($s['block'] ?? ''), ENT_QUOTES);
                            $e_subj = htmlspecialchars($s['subject'], ENT_QUOTES); $e_room = htmlspecialchars($s['room'], ENT_QUOTES);
                            $e_day = htmlspecialchars($s['day'], ENT_QUOTES); $e_time = htmlspecialchars($s['block_time'], ENT_QUOTES);
                            $e_proctor = htmlspecialchars($s['proctor'] ?? 'TBA', ENT_QUOTES);
                        ?>
                        <div class="sched-item <?php echo $status_class; ?>">
                            <div class="s-room"><span>ROOM</span><?php echo htmlspecialchars($s['room']); ?></div>
                            <div><div class="s-subj"><?php echo htmlspecialchars($s['subject']); ?></div><div class="s-time">Day <?php echo htmlspecialchars($s['day']); ?> &bull; <?php echo $start; ?> - <?php echo $end; ?></div></div>
                            <div><?php echo $badge; ?></div>
                            <div style="display:flex; flex-wrap:wrap; gap:5px; justify-content:flex-end;">
                                <?php if($s['status'] !== 'pending'): ?><a href="?reset_status=<?php echo $s['id']; ?>" class="btn-sm" title="Revert to Pending" style="width:100%; text-align:center;">RESET STAT</a><?php endif; ?>
                                <a href="javascript:void(0)" class="btn-sm" style="border-color:var(--sti-blue); color:var(--sti-blue);" onclick="openEditModal('<?php echo $s['id']; ?>', '<?php echo $e_prog; ?>', '<?php echo $e_sec; ?>', '<?php echo $e_subj; ?>', '<?php echo $e_room; ?>', '<?php echo $e_day; ?>', '<?php echo $e_time; ?>', '<?php echo $e_proctor; ?>')">EDIT</a>
                                <a href="?delete=<?php echo $s['id']; ?>" class="btn-sm del" onclick="return confirm('Delete this schedule?');">DELETE</a>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- DIRECTOR'S CUT FOOTER -->
    <div class="credit-footer">
        This is a project of <a href="https://www.facebook.com/502StudioSTICubao" target="_blank">Team 5:02 Studio</a> of STI College Cubao spearheaded by Mr. Jan Allen Dela Cruz in collaboration with Mr. Rodolfo Ivan Porwelos Maaño, the MIS Team, the Faculty, the Admins, and the STI College Cubao Marketing Team. Project of 2025, All-Rights Reserved.
    </div>

    <script>
        function filterQueue() {
        const val = document.getElementById('sectionFilter').value;
        const groups = document.querySelectorAll('.section-group');
        groups.forEach(g => { g.style.display = (val === 'ALL' || g.getAttribute('data-section') === val) ? 'block' : 'none'; });
    }
    function openEditModal(id, prog, sec, subj, room, day, time, proctor) {
        document.getElementById('edit_id').value = id; document.getElementById('edit_prog').value = prog;
        document.getElementById('edit_sec').value = sec; document.getElementById('edit_subj').value = subj;
        document.getElementById('edit_room').value = room; document.getElementById('edit_day').value = day;
        document.getElementById('edit_time').value = time; document.getElementById('edit_proctor').value = proctor;
        document.getElementById('edit-modal').style.display = 'flex';
    }
</script>
</body>
</html>