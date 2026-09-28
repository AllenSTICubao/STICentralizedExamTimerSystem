<?php
session_start();
date_default_timezone_set('Asia/Manila');
require_once 'db.php';

// 🛑 GATEKEEPER: Auto-login kung galing Admin, kick out kung hindi
if (!isset($_SESSION['exam_admin'])) {
    if (!isset($_GET['action']) && !isset($_POST['action'])) {
        header("Location: admin.php");
        exit;
    }
}

// 🛠️ AUTO-PATCH: TV Status Table
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS tv_status (
        room VARCHAR(50) PRIMARY KEY,
        last_seen INT,
        phase VARCHAR(50),
        command VARCHAR(50) DEFAULT NULL,
        command_payload TEXT DEFAULT NULL
    )");
} catch (PDOException $e) { }

// ==========================================
// 📡 MCR API ENDPOINTS (BACKGROUND TASKS)
// ==========================================

// 1. PING RECEIVER: TV Heartbeat
if (isset($_GET['action']) && $_GET['action'] == 'ping') {
    $room = strtoupper(trim($_POST['room'] ?? ''));
    $phase = $_POST['phase'] ?? 'UNKNOWN';
    
    if ($room && $room !== 'UNKNOWN') {
        $now = time();
        $stmt = $pdo->prepare("SELECT command, command_payload FROM tv_status WHERE room = ?");
        $stmt->execute([$room]);
        $cmdData = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $pdo->prepare("INSERT INTO tv_status (room, last_seen, phase) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_seen = ?, phase = ?");
        $stmt->execute([$room, $now, $phase, $now, $phase]);

        if ($cmdData && $cmdData['command']) {
            $pdo->prepare("UPDATE tv_status SET command = NULL, command_payload = NULL WHERE room = ?")->execute([$room]);
        }

        header('Content-Type: application/json');
        echo json_encode(['command' => $cmdData['command'] ?? null, 'payload' => $cmdData['command_payload'] ?? null]);
        exit;
    }
}

// 2. LIVE DATA SENDER: MCR Dashboard Stats
if (isset($_GET['action']) && $_GET['action'] == 'mcr_data') {
    $todayISO = date('Y-m-d');
    $stmt = $pdo->query("SELECT setting_value FROM exam_settings WHERE setting_key = 'exam_days'");
    $exam_days = json_decode($stmt->fetchColumn(), true) ?: [];
    $currentDay = 0;
    foreach($exam_days as $d => $date) { if ($date === $todayISO) { $currentDay = $d; break; } }

    $stmt = $pdo->prepare("SELECT room, subject, section_code, status FROM exam_schedules WHERE day = ? AND status != 'done' ORDER BY start_time ASC");
    $stmt->execute([$currentDay]);
    $roomExams = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if(!isset($roomExams[$row['room']])) $roomExams[$row['room']] = $row;
    }

    $stmt = $pdo->query("SELECT DISTINCT room FROM exam_schedules");
    $dbRooms = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    $stmt = $pdo->query("SELECT * FROM tv_status");
    $statuses = [];
    while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { $statuses[$row['room']] = $row; }
    
    $result = [];
    foreach($dbRooms as $r) { 
        $result[$r] = $statuses[$r] ?? ['last_seen' => 0, 'phase' => 'OFFLINE']; 
        $result[$r]['current_exam'] = $roomExams[$r] ?? null;
    }
    foreach($statuses as $r => $data) { 
        if(!isset($result[$r])) {
            $result[$r] = $data;
            $result[$r]['current_exam'] = $roomExams[$r] ?? null;
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode(['now' => time(), 'rooms' => $result]);
    exit;
}

// 3. GET ROOM QUEUE: For Exam Selector Modal
if (isset($_GET['action']) && $_GET['action'] == 'room_queue') {
    $room = $_GET['room'];
    $todayISO = date('Y-m-d');
    $stmt = $pdo->query("SELECT setting_value FROM exam_settings WHERE setting_key = 'exam_days'");
    $exam_days = json_decode($stmt->fetchColumn(), true) ?: [];
    $currentDay = 0;
    foreach($exam_days as $d => $date) { if ($date === $todayISO) { $currentDay = $d; break; } }

    $stmt = $pdo->prepare("SELECT * FROM exam_schedules WHERE room = ? AND day = ? AND status != 'done' ORDER BY start_time ASC");
    $stmt->execute([$room, $currentDay]);
    $exams = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach($exams as &$ex) {
        $ex['display_time'] = date('h:i A', $ex['start_time'] - 1800);
    }
    
    header('Content-Type: application/json');
    echo json_encode($exams);
    exit;
}

// 💥 3.5 CHECK CONFLICT API
if (isset($_GET['action']) && $_GET['action'] == 'check_conflict') {
    $id = $_GET['exam_id'];
    $new_room = strtoupper(trim($_GET['new_room']));

    $stmt = $pdo->prepare("SELECT day, start_time, end_time FROM exam_schedules WHERE id = ?");
    $stmt->execute([$id]);
    $target = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$target) { echo json_encode(['error' => 'Exam not found']); exit; }

    $day = $target['day'];
    $start = $target['start_time'];
    $end = $target['end_time'];

    // Find conflicting exams in the new room (Overlap Logic)
    $stmt = $pdo->prepare("SELECT subject, section_code, start_time FROM exam_schedules WHERE room = ? AND day = ? AND id != ? AND status != 'done' AND start_time < ? AND end_time > ?");
    $stmt->execute([$new_room, $day, $id, $end, $start]);
    $conflicts_raw = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $conflicts = [];
    foreach($conflicts_raw as $c) {
        $c['display_time'] = date('h:i A', $c['start_time'] - 1800);
        $conflicts[] = $c;
    }

    // Smart Room Suggestion (Find ALL rooms, subtract BUSY rooms)
    $stmt = $pdo->query("SELECT DISTINCT room FROM exam_schedules");
    $all_rooms = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $stmt = $pdo->prepare("SELECT DISTINCT room FROM exam_schedules WHERE day = ? AND id != ? AND status != 'done' AND start_time < ? AND end_time > ?");
    $stmt->execute([$day, $id, $end, $start]);
    $busy_rooms = $stmt->fetchAll(PDO::FETCH_COLUMN);

    $available_rooms = array_diff($all_rooms, $busy_rooms);
    sort($available_rooms);

    header('Content-Type: application/json');
    echo json_encode([
        'has_conflict' => count($conflicts) > 0,
        'conflicts' => $conflicts,
        'suggestions' => array_values($available_rooms)
    ]);
    exit;
}

// 4. MCR DIRECT COMMANDS (Start Specific Exam, Move Exam)
if (isset($_POST['action'])) {
    if ($_POST['action'] == 'start_specific') {
        $id = $_POST['exam_id'];
        $room = $_POST['room'];
        $now = time();
        $pdo->prepare("UPDATE exam_schedules SET status = 'ongoing', actual_start = ? WHERE id = ?")->execute([$now, $id]);
        $pdo->prepare("INSERT INTO tv_status (room, last_seen, phase, command) VALUES (?, 0, 'UNKNOWN', 'refresh') ON DUPLICATE KEY UPDATE command = 'refresh'")->execute([$room]);
        exit;
    }
    
    // 💥 UPDATED MOVE EXAM LOGIC WITH OVERWRITE CAPABILITY
    if ($_POST['action'] == 'move_exam') {
        $id = $_POST['exam_id'];
        $new_room = strtoupper(trim($_POST['new_room']));
        $old_room = $_POST['old_room'];
        $overwrite = $_POST['overwrite'] ?? '0';

        if ($overwrite === '1') {
            $stmt = $pdo->prepare("SELECT day, start_time, end_time FROM exam_schedules WHERE id = ?");
            $stmt->execute([$id]);
            $target = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($target) {
                // DELETE conflicting exams to make way
                $delStmt = $pdo->prepare("DELETE FROM exam_schedules WHERE room = ? AND day = ? AND id != ? AND status != 'done' AND start_time < ? AND end_time > ?");
                $delStmt->execute([$new_room, $target['day'], $id, $target['end_time'], $target['start_time']]);
            }
        }
        
        // Move the actual exam
        $pdo->prepare("UPDATE exam_schedules SET room = ? WHERE id = ?")->execute([$new_room, $id]);
        
        // Refresh both old and new TVs
        $pdo->prepare("INSERT INTO tv_status (room, command) VALUES (?, 'refresh') ON DUPLICATE KEY UPDATE command = 'refresh'")->execute([$old_room]);
        $pdo->prepare("INSERT INTO tv_status (room, command) VALUES (?, 'refresh') ON DUPLICATE KEY UPDATE command = 'refresh'")->execute([$new_room]);
        exit;
    }
}

// 5. GLOBAL COMMAND ISSUER (Buttons)
if (isset($_POST['issue_command'])) {
    $room = $_POST['room'];
    $cmd = $_POST['cmd'];
    $payload = $_POST['payload'] ?? '';
    
    $pdo->prepare("INSERT INTO tv_status (room, last_seen, phase, command, command_payload) VALUES (?, 0, 'UNKNOWN', ?, ?) ON DUPLICATE KEY UPDATE command = ?, command_payload = ?")->execute([$room, $cmd, $payload, $cmd, $payload]);
    exit;
}

$stmt = $pdo->query("SELECT setting_value FROM exam_settings WHERE setting_key = 'logo'");
$sysLogo = $stmt->fetchColumn() ?: 'uploads/logo.webp';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STI Centralized Exam Timer System | Master Control Room</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&family=Oswald:wght@500;700&family=JetBrains+Mono:wght@700;900&display=swap" rel="stylesheet">
    <style>
        /* 🌑 DARK MODE TACTICAL UI */
        :root { --sti-yellow: #fDD000; --sti-blue: #003666; --bg: #000b18; --surface: #001a33; --border: #003366; --text-light: #e1e8ed; --green: #00ff66; --red: #ff3333; }
        body { font-family: 'Montserrat', sans-serif; background: var(--bg); color: var(--text-light); margin: 0; padding: 0; user-select: none;}
        * { box-sizing: border-box; }

        .top-nav { background: var(--surface); border-bottom: 2px solid var(--sti-yellow); display: flex; justify-content: space-between; align-items: center; padding: 15px 40px; box-shadow: 0 10px 30px rgba(0,0,0,0.5);}
        .top-nav .brand { display: flex; align-items: center; gap: 15px; }
        .top-nav img { height: 40px; object-fit: contain; }
        .top-nav h1 { margin: 0; font-family: 'Oswald'; font-size: 24px; color: var(--white); letter-spacing: 1px; text-transform: uppercase;}
        .top-nav h1 span { color: var(--sti-yellow); }
        .clock { font-family: 'JetBrains Mono', monospace; font-size: 24px; color: var(--sti-yellow); font-weight: bold;}
        .btn-close { background: transparent; border: 1px solid var(--border); color: var(--text-light); padding: 8px 15px; border-radius: 5px; cursor: pointer; text-decoration: none; font-size: 12px; font-weight: bold;}
        .btn-close:hover { background: rgba(255,255,255,0.1); }

        .container { padding: 30px 40px; max-width: 1600px; margin: auto; min-height: 80vh;}
        .grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)); gap: 20px;}
        
        .room-card { background: var(--surface); border: 1px solid var(--border); border-radius: 12px; padding: 20px; cursor: pointer; transition: 0.2s; position: relative; overflow: hidden; display: flex; flex-direction: column; justify-content: space-between; min-height: 160px;}
        .room-card:hover { border-color: var(--sti-yellow); transform: translateY(-3px); box-shadow: 0 10px 20px rgba(253, 208, 0, 0.1);}
        .room-card.offline { opacity: 0.5; filter: grayscale(0.5); }
        
        .r-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid var(--border); padding-bottom: 10px; margin-bottom: 10px;}
        .r-name { font-family: 'Oswald'; font-size: 32px; color: var(--white); line-height: 1;}
        .status-badge { display: flex; align-items: center; gap: 8px; font-size: 10px; font-weight: 900; letter-spacing: 1px; text-transform: uppercase;}
        
        .dot { width: 12px; height: 12px; border-radius: 50%; box-shadow: 0 0 10px currentColor; }
        .dot.online { color: var(--green); background: var(--green); animation: blink 2s infinite alternate;}
        .dot.offline { color: var(--red); background: var(--red); }
        @keyframes blink { 100% { opacity: 0.4; box-shadow: none; } }

        .r-phase { font-size: 16px; font-weight: 900; color: var(--sti-yellow); text-transform: uppercase; font-family: 'Oswald'; letter-spacing: 1px; margin-bottom: 10px;}
        .r-exam { background: rgba(0,0,0,0.4); padding: 10px; border-radius: 8px; font-size: 11px; font-weight: 700; color: #aaa; border: 1px solid var(--border);}
        .r-exam strong { color: var(--white); font-size: 13px; display: block; margin-bottom: 3px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;}

        /* MODAL CONTROLS */
        .modal { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 0, 0, 0.8); backdrop-filter: blur(10px); z-index: 1000; align-items: center; justify-content: center;}
        .modal-box { background: var(--surface); border: 2px solid var(--sti-yellow); padding: 30px; border-radius: 16px; width: 90%; max-width: 900px; box-shadow: 0 20px 50px rgba(0,0,0,0.8); max-height: 90vh; display: flex; flex-direction: column;}
        
        /* CONFLICT MODAL OVERRIDE */
        #conflict-modal .modal-box { max-width: 600px; border-color: var(--red); text-align: center; }

        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 2px solid var(--border); padding-bottom: 15px; margin-bottom: 20px;}
        .modal-header h2 { font-family: 'Oswald'; font-size: 32px; margin: 0; color: var(--white);}
        
        .modal-layout { display: grid; grid-template-columns: 1fr 1fr; gap: 30px; overflow-y: auto;}
        .m-section h3 { font-family: 'Oswald'; font-size: 18px; color: var(--sti-yellow); margin-top: 0; margin-bottom: 15px; letter-spacing: 1px;}
        
        /* EXAM SELECTOR LIST */
        .exam-list { display: flex; flex-direction: column; gap: 10px; }
        .ex-item { background: rgba(0,0,0,0.3); border: 1px solid var(--border); border-radius: 8px; padding: 12px; display: flex; justify-content: space-between; align-items: center;}
        .ex-info { flex: 1; }
        .ex-info strong { display: block; font-family: 'Oswald'; font-size: 16px; color: var(--white); margin-bottom: 2px;}
        .ex-info span { font-size: 11px; color: #888; font-weight: bold;}
        .ex-actions { display: flex; flex-direction: column; gap: 5px; align-items: flex-end;}
        
        .move-box { display: flex; gap: 5px; }
        .move-box input { width: 60px; padding: 5px; background: #000; color: white; border: 1px solid var(--border); border-radius: 4px; font-size: 11px; text-align: center;}
        .move-box button { padding: 5px 10px; font-size: 10px;}

        .btn { display: block; width: 100%; padding: 15px; margin-bottom: 10px; font-family: 'Oswald'; font-size: 16px; font-weight: 700; border: none; border-radius: 8px; cursor: pointer; text-transform: uppercase; letter-spacing: 1px; transition: 0.2s;}
        .btn-sm { padding: 6px 12px; font-size: 11px; width: auto; margin: 0; }
        .btn-blue { background: #00aaff; color: #000; }
        .btn-red { background: var(--red); color: white; }
        .btn-yellow { background: var(--sti-yellow); color: #000; }
        .btn-green { background: var(--green); color: #000; }
        .btn-outline { background: transparent; border: 2px solid var(--text-light); color: var(--text-light); }
        .btn:hover { filter: brightness(1.2); transform: scale(1.02); }

        .bypass-zone { display: none; background: rgba(0,0,0,0.3); padding: 15px; border-radius: 8px; margin-bottom: 10px; border: 1px solid var(--border);}
        .bypass-zone input { width: 100%; padding: 12px; margin-bottom: 10px; background: #000; border: 1px solid var(--border); color: white; font-family: 'Montserrat'; font-weight:bold; border-radius: 5px;}

        .credit-footer { text-align: center; font-size: 12px; color: #555; line-height: 1.6; max-width: 800px; padding: 20px; margin: 20px auto;}
        .credit-footer a { color: var(--sti-yellow); font-weight: bold; text-decoration: none; transition: 0.2s;}
        .credit-footer a:hover { color: var(--white); text-decoration: underline;}
    </style>
</head>
<body>

    <div class="top-nav">
        <div class="brand">
            <img src="<?php echo htmlspecialchars($sysLogo); ?>" onerror="this.style.display='none'">
            <h1>STI Centralized Exam Timer System <span>|</span> MCR</h1>
        </div>
        <div class="clock" id="clock">00:00:00</div>
        <a href="admin.php" class="btn-close">⬅ BACK TO ADMIN</a>
    </div>

    <div class="container">
        <div class="grid" id="mcr-grid">
            <!-- Populated by JS -->
        </div>
    </div>

    <!-- MAIN CONTROL MODAL -->
    <div class="modal" id="ctrl-modal">
        <div class="modal-box">
            <div class="modal-header">
                <h2 id="modal-room-title">ROOM 000</h2>
                <button class="btn btn-outline btn-sm" onclick="closeModal()">✖ CLOSE</button>
            </div>
            <div class="modal-layout">
                <div class="m-section">
                    <h3>📌 TODAY'S QUEUE FOR THIS ROOM</h3>
                    <div class="exam-list" id="modal-exam-list">
                        <div style="color:#888; font-size:12px; text-align:center;">Loading queue...</div>
                    </div>
                </div>
                <div class="m-section">
                    <h3>⚡ DIRECT TV COMMANDS</h3>
                    <div id="btn-group-main">
                        <button class="btn btn-blue" onclick="sendCommand('start')">▶ FORCE START CURRENT EXAM</button>
                        <button class="btn btn-red" onclick="sendCommand('end')">⏹ CONCLUDE CURRENT EXAM</button>
                        <button class="btn btn-yellow" onclick="showBypass()">⏱️ SET CUSTOM TIMER BYPASS</button>
                        <button class="btn btn-outline" onclick="sendCommand('refresh')">🔄 REFRESH / REBOOT TV</button>
                    </div>

                    <div class="bypass-zone" id="bypass-zone">
                        <input type="text" id="bp_subj" placeholder="Custom Subject Name" autocomplete="off">
                        <input type="number" id="bp_mins" placeholder="Duration (Minutes)">
                        <button class="btn btn-yellow" style="margin-bottom:0;" onclick="sendBypass()">TRANSMIT BYPASS</button>
                        <button class="btn btn-outline" style="margin-top:10px; font-size: 12px; padding: 10px;" onclick="hideBypass()">CANCEL</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 💥 CONFLICT WARNING MODAL -->
    <div class="modal" id="conflict-modal" style="z-index: 2000;">
        <div class="modal-box">
            <h2 style="color: var(--red); font-size: 28px; margin-bottom: 10px;">⚠️ SCHEDULE CONFLICT DETECTED</h2>
            <p id="conflict-msg" style="color: var(--text-light); font-size: 14px; margin-bottom: 20px; line-height: 1.5; font-weight: normal; text-transform: none;">
                Room <b>302</b> has the following schedule that might conflict with your move. Moving schedules will automatically <b style="color:var(--red);">OVERWRITE (DELETE)</b> scheduled exams for that time frame:
            </p>
            
            <div id="conflict-list" style="background: rgba(255,11,47,0.1); border: 1px solid var(--red); padding: 15px; border-radius: 8px; margin-bottom: 20px; text-align: left; font-size: 12px; color: #ff9999;">
                <!-- Conflicting exams injected here -->
            </div>

            <div style="background: rgba(0,255,102,0.1); border: 1px solid var(--green); padding: 15px; border-radius: 8px; margin-bottom: 25px; text-align: left;">
                <strong style="color: var(--green); display: block; margin-bottom: 5px; font-family: 'Oswald'; font-size: 16px;">💡 SUGGESTED AVAILABLE ROOMS:</strong>
                <div id="suggest-list" style="font-size: 14px; font-weight: bold; color: var(--white);">
                    <!-- Suggested rooms injected here -->
                </div>
            </div>

            <div style="display: flex; gap: 10px;">
                <button class="btn btn-outline" style="flex: 1;" onclick="document.getElementById('conflict-modal').style.display='none'">CANCEL MOVE</button>
                <button class="btn btn-red" style="flex: 1;" id="btn-force-move">PROCEED (OVERWRITE)</button>
            </div>
        </div>
    </div>

    <!-- DIRECTOR'S CUT FOOTER -->
    <div class="credit-footer">
        This is a project of <a href="https://www.facebook.com/502StudioSTICubao" target="_blank">Team 5:02 Studio</a> of STI College Cubao spearheaded by Mr. Jan Allen Dela Cruz in collaboration with Mr. Rodolfo Ivan Porwelos Maaño, the MIS Team, the Faculty, the Admins, and the STI College Cubao Marketing Team. Project of 2025, All-Rights Reserved.
    </div>

    <script>
        let selectedRoom = '';

        setInterval(() => {
            const now = new Date();
            let h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
            let ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            document.getElementById('clock').innerText = `${h}:${m < 10 ? '0'+m : m}:${s < 10 ? '0'+s : s} ${ampm}`;
        }, 1000);

        async function fetchMCR() {
            try {
                let res = await fetch('admin-mcr.php?action=mcr_data&t=' + Date.now());
                let data = await res.json();
                renderGrid(data);
            } catch(e) {}
        }

        function renderGrid(data) {
            const grid = document.getElementById('mcr-grid');
            let html = '';
            
            for (const [room, info] of Object.entries(data.rooms)) {
                const isOnline = (data.now - info.last_seen) <= 30; // 30 second buffer
                const statusTxt = isOnline ? 'ONLINE' : 'OFFLINE';
                const dotClass = isOnline ? 'online' : 'offline';
                const phaseTxt = isOnline ? (info.phase || 'IDLE') : 'DISCONNECTED';
                const cardClass = isOnline ? '' : 'offline';
                
                let examHtml = '<div class="r-exam">No pending exams.</div>';
                if (info.current_exam) {
                    let ex = info.current_exam;
                    let statColor = ex.status === 'ongoing' ? 'var(--green)' : '#aaa';
                    examHtml = `<div class="r-exam"><strong style="color:${statColor};">${ex.subject}</strong>${ex.section_code} &bull; ${ex.status.toUpperCase()}</div>`;
                }

                html += `
                <div class="room-card ${cardClass}" onclick="openModal('${room}')">
                    <div>
                        <div class="r-header">
                            <div class="r-name">${room}</div>
                            <div class="status-badge"><div class="dot ${dotClass}"></div> ${statusTxt}</div>
                        </div>
                        <div style="font-size:10px; color:#888; margin-bottom:2px;">TV PHASE</div>
                        <div class="r-phase" style="color: ${isOnline ? 'var(--sti-yellow)' : '#555'};">${phaseTxt}</div>
                    </div>
                    ${examHtml}
                </div>`;
            }
            grid.innerHTML = html;
        }

        function openModal(room) {
            selectedRoom = room;
            document.getElementById('modal-room-title').innerText = 'ROOM ' + room;
            document.getElementById('bypass-zone').style.display = 'none';
            document.getElementById('btn-group-main').style.display = 'block';
            document.getElementById('ctrl-modal').style.display = 'flex';
            fetchRoomQueue(room);
        }

        async function fetchRoomQueue(room) {
            const list = document.getElementById('modal-exam-list');
            list.innerHTML = '<div style="color:#888; font-size:12px; text-align:center;">Loading...</div>';
            try {
                let res = await fetch(`admin-mcr.php?action=room_queue&room=${room}&t=` + Date.now());
                let data = await res.json();
                
                if (data.length === 0) {
                    list.innerHTML = '<div style="color:#888; font-size:12px; text-align:center;">No pending exams for this room today.</div>';
                    return;
                }

                let html = '';
                data.forEach(ex => {
                    let btnStart = ex.status === 'ongoing' 
                        ? `<button class="btn btn-sm" disabled style="background:#555; color:#222; cursor:not-allowed;">ONGOING</button>`
                        : `<button class="btn btn-sm btn-green" onclick="startSpecificExam('${ex.id}')">▶ START THIS</button>`;

                    html += `
                    <div class="ex-item">
                        <div class="ex-info">
                            <strong>${ex.subject}</strong>
                            <span>${ex.section_code} | ${ex.display_time}</span>
                        </div>
                        <div class="ex-actions">
                            ${btnStart}
                            <div class="move-box">
                                <input type="text" id="mv_${ex.id}" placeholder="New Rm">
                                <button class="btn btn-sm btn-blue" onclick="checkAndMoveExam('${ex.id}', '${room}')">MOVE</button>
                            </div>
                        </div>
                    </div>`;
                });
                list.innerHTML = html;
            } catch(e) {
                list.innerHTML = '<div style="color:red; font-size:12px; text-align:center;">Failed to load queue.</div>';
            }
        }

        function closeModal() {
            document.getElementById('ctrl-modal').style.display = 'none';
        }

        function showBypass() {
            document.getElementById('btn-group-main').style.display = 'none';
            document.getElementById('bypass-zone').style.display = 'block';
        }
        function hideBypass() {
            document.getElementById('btn-group-main').style.display = 'block';
            document.getElementById('bypass-zone').style.display = 'none';
        }

        function sendCommand(cmd) {
            if(!confirm(`Transmit [${cmd.toUpperCase()}] command to Room ${selectedRoom}?`)) return;
            const fd = new URLSearchParams();
            fd.append('issue_command', '1');
            fd.append('room', selectedRoom);
            fd.append('cmd', cmd);
            fetch('admin-mcr.php', { method: 'POST', body: fd }).then(() => {
                closeModal();
                fetchMCR();
            });
        }

        function sendBypass() {
            const subj = document.getElementById('bp_subj').value.trim();
            const mins = document.getElementById('bp_mins').value;
            if(!subj || !mins) { alert("Please fill both fields."); return; }
            if(!confirm(`Transmit CUSTOM BYPASS (${mins} mins) to Room ${selectedRoom}?`)) return;

            const payload = JSON.stringify({ subj: subj, mins: mins });
            const fd = new URLSearchParams();
            fd.append('issue_command', '1');
            fd.append('room', selectedRoom);
            fd.append('cmd', 'bypass');
            fd.append('payload', payload);
            
            fetch('admin-mcr.php', { method: 'POST', body: fd }).then(() => {
                closeModal();
                fetchMCR();
            });
        }

        function startSpecificExam(examId) {
            if(!confirm("Force this specific exam to START NOW? This will bypass its schedule.")) return;
            const fd = new URLSearchParams();
            fd.append('action', 'start_specific');
            fd.append('exam_id', examId);
            fd.append('room', selectedRoom);
            fetch('admin-mcr.php', { method: 'POST', body: fd }).then(() => {
                fetchRoomQueue(selectedRoom);
                fetchMCR();
            });
        }

        // 💥 SMART COLLISION DETECTOR & MOVER
        async function checkAndMoveExam(examId, oldRoom) {
            const newRoom = document.getElementById('mv_' + examId).value.trim().toUpperCase();
            if (!newRoom) { alert("Please enter the new room number."); return; }
            if (newRoom === oldRoom) return;

            try {
                let res = await fetch(`admin-mcr.php?action=check_conflict&exam_id=${examId}&new_room=${newRoom}`);
                let data = await res.json();

                if (data.has_conflict) {
                    // Setup Conflict Modal
                    document.getElementById('conflict-msg').innerHTML = `Room <b>${newRoom}</b> has the following schedule that might conflict with your move. Moving schedules will automatically <b style="color:var(--red);">OVERWRITE (DELETE)</b> scheduled exams for that time frame:`;
                    
                    let cHtml = '';
                    data.conflicts.forEach(c => {
                        cHtml += `<div style="margin-bottom: 5px;">&bull; <b>${c.subject}</b> - ${c.section_code} (${c.display_time})</div>`;
                    });
                    document.getElementById('conflict-list').innerHTML = cHtml;

                    let sHtml = data.suggestions.length > 0 ? data.suggestions.join(', ') : '<span style="color:#aaa;">No totally empty rooms found.</span>';
                    document.getElementById('suggest-list').innerHTML = sHtml;

                    // Bind force move event
                    document.getElementById('btn-force-move').onclick = function() {
                        document.getElementById('conflict-modal').style.display = 'none';
                        executeMove(examId, oldRoom, newRoom, 1);
                    };

                    document.getElementById('conflict-modal').style.display = 'flex';
                } else {
                    if(confirm(`Move this exam from ${oldRoom} to ${newRoom}? Both TVs will be refreshed.`)) {
                        executeMove(examId, oldRoom, newRoom, 0);
                    }
                }
            } catch(e) { alert("Error checking conflicts."); }
        }

        function executeMove(examId, oldRoom, newRoom, overwriteFlag) {
            const fd = new URLSearchParams();
            fd.append('action', 'move_exam');
            fd.append('exam_id', examId);
            fd.append('old_room', oldRoom);
            fd.append('new_room', newRoom);
            fd.append('overwrite', overwriteFlag);

            fetch('admin-mcr.php', { method: 'POST', body: fd }).then(() => {
                fetchRoomQueue(selectedRoom);
                fetchMCR();
            });
        }

        fetchMCR();
        setInterval(fetchMCR, 8000);

    </script>
</body>
</html>