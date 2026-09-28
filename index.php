<?php
// 🛠️ AUTO-SETUP REDIRECT
if (!file_exists('db.php')) {
    header('Location: setup.php');
    exit;
}

// 🔐 DIRECT AUTHENTICATION ENDPOINT FOR SYSTEM & PROCTORS
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify_proctor') {
    require_once 'db.php';
    $pass = $_POST['password'] ?? '';
    
    // We use the 'PROCTOR' or 'ADMIN' account to unlock the terminal once forever
    $stmt = $pdo->prepare("SELECT password_hash FROM exam_accounts WHERE username = 'PROCTOR' OR username = 'ADMIN' LIMIT 1");
    $stmt->execute();
    $hash = $stmt->fetchColumn();
    
    header('Content-Type: application/json');
    if ($hash && password_verify($pass, $hash)) {
        echo json_encode(['success' => true]);
    } else {
        echo json_encode(['success' => false]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STI Exam Room Timer</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&family=Oswald:wght@500;700&family=JetBrains+Mono:wght@700;900&display=swap" rel="stylesheet">
    <style>
        /* 🎨 HIGH CONTRAST PALETTE */
        :root { 
            --sti-yellow: #fDD000; 
            --sti-blue: #003666; 
            --sti-dark: #001f3f;
            --white: #ffffff; 
            --bg: #f4f7f9;
            --text-dark: #111111;
            --text-gray: #555555;
            --red: #FF0B2F; 
            --green: #00b35c;
            --border: #cccccc;
        }
        
        body, html { 
            margin: 0; padding: 0; width: 100vw; height: 100vh; 
            color: var(--text-dark); font-family: 'Montserrat', sans-serif; 
            overflow: hidden; background: var(--bg); 
            user-select: none;
        }
        * { box-sizing: border-box; }

        /* 🚪 GATES (SYSTEM & ROOM) */
        .gate-screen {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: rgba(0, 54, 102, 0.98); z-index: 999999;
            display: none; flex-direction: column; align-items: center; justify-content: center; backdrop-filter: blur(5px);
        }
        .gate-box {
            background: var(--white); padding: 5vh 4vw; border-radius: 2vh; text-align: center;
            border-top: 1vh solid var(--sti-yellow); box-shadow: 0 2vh 5vh rgba(0,0,0,0.5); width: 40vw; min-width: 350px;
        }
        .gate-box h2 { font-family: 'Oswald'; color: var(--sti-blue); font-size: 4vh; margin: 0 0 1vh 0; letter-spacing: 0.1vw;}
        .gate-box p { color: var(--text-gray); margin: 0 0 3vh 0; font-size: 1.8vh; font-weight: 700;}
        .gate-box input { width: 100%; font-family: 'Oswald'; font-size: 5vh; text-align: center; padding: 1.5vh; margin-bottom: 2vh; border: 2px solid var(--border); border-radius: 1vh; background: var(--bg); color: var(--sti-blue); text-transform: uppercase;}
        .gate-box input:focus { outline: none; border-color: var(--sti-blue); background: var(--white);}
        .gate-box button { font-family: 'Oswald'; font-size: 2.5vh; padding: 2vh 0; width: 100%; background: var(--sti-yellow); color: var(--sti-dark); border: none; font-weight: 900; cursor: pointer; border-radius: 1vh; letter-spacing: 0.1vw; transition: 0.2s;}
        .gate-box button:hover { transform: scale(1.02); background: #e6bd00; }
        .gate-error { color: var(--red); font-weight: 900; font-size: 1.5vh; margin-top: 1.5vh; display: none; }

        /* 😴 IDLE SCREEN */
        #idle-screen { display: none; flex-direction: column; align-items: center; justify-content: center; height: 100vh; position: relative; z-index: 2;}
        .global-logo { height: 12vh; object-fit: contain; margin-bottom: 2vh;}
        .idle-clock { font-family: 'Oswald'; font-size: 18vh; font-weight: 700; color: var(--sti-blue); line-height: 1; margin-bottom: 1vh; letter-spacing: 0.2vw; text-shadow: 0 1vh 3vh rgba(0,54,102,0.1);}
        .idle-room { font-family: 'Montserrat'; font-size: 3vh; color: var(--text-dark); font-weight: 900; letter-spacing: 0.3vw; margin-bottom: 2vh; text-transform: uppercase; display: flex; align-items: center; gap: 1.5vw; background: var(--white); padding: 1.5vh 4vw; border-radius: 5vh; border: 2px solid var(--border); box-shadow: 0 1vh 2vh rgba(0,0,0,0.05);}
        .idle-room span { color: var(--border); }

        /* ➡ NEXT EXAM BAR (STANDBY) */
        #next-exam-bar {
            position: absolute; right: 0; top: 20vh; background: var(--white);
            border-left: 1vh solid var(--sti-yellow); padding: 2vh 3vw; border-radius: 2vh 0 0 2vh;
            box-shadow: -1vh 1vh 3vh rgba(0,0,0,0.1); transform: translateX(120%); transition: 0.6s cubic-bezier(0.175, 0.885, 0.32, 1.275);
            z-index: 5;
        }
        #next-exam-bar.show { transform: translateX(0); }
        .nex-lbl { font-size: 1.5vh; color: var(--text-gray); font-weight: 900; letter-spacing: 0.2vw; margin-bottom: 0.5vh;}
        .nex-time { font-family: 'Oswald'; font-size: 4vh; color: var(--sti-blue); line-height: 1; margin-bottom: 0.5vh;}
        .nex-subj { font-family: 'Montserrat'; font-size: 1.8vh; font-weight: 900; color: var(--sti-dark); text-transform: uppercase;}
        .nex-sec { font-size: 1.4vh; color: var(--text-gray); font-weight: 700; }

        /* 🎬 EXAM STAGE */
        #exam-stage { display: none; width: 100vw; height: 100vh; flex-direction: column; position: relative; z-index: 2;}
        
        /* 🔥 ANTI-JIGGLE: Absolute Positioning for Top Bar Elements */
        .top-bar { position: absolute; top: 0; left: 0; width: 100vw; height: 12vh; display: flex; justify-content: space-between; align-items: flex-start; padding: 3vh 4vw; z-index: 10; }
        .room-badge { background: var(--white); padding: 1.5vh 4vw; display: flex; flex-direction: column; justify-content: center; align-items: center; border-radius: 1.5vh; border: 2px solid var(--border); box-shadow: 0 0.5vh 1.5vh rgba(0,0,0,0.05);}
        .room-badge span { font-family: 'Montserrat'; font-size: 1.5vh; color: var(--text-gray); letter-spacing: 0.3vw; font-weight: 900; margin-bottom: -0.5vh;}
        .room-badge strong { font-family: 'Oswald'; font-size: 5vh; color: var(--sti-blue); line-height: 1;}
        
        .sys-clock-box { background: var(--white); padding: 1.5vh 4vw; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 1.5vh; border: 2px solid var(--border); box-shadow: 0 0.5vh 1.5vh rgba(0,0,0,0.05);}
        .sys-clock-box span { font-family: 'Montserrat'; font-size: 1.2vh; color: var(--text-gray); letter-spacing: 0.2vw; font-weight: 700; text-transform: uppercase; margin-bottom: 0.2vh;}
        .sys-clock-small { font-family: 'Oswald'; font-size: 3.5vh; color: var(--sti-blue); line-height: 1;}
        .sys-date-small { font-family: 'Montserrat'; font-size: 1.5vh; font-weight: 900; color: var(--text-gray); margin-top: 0.5vh; letter-spacing: 0.1vw; text-transform: uppercase;}

        .stage-logo-container { position: absolute; left: 50%; top: 3vh; transform: translateX(-50%); z-index: 11; }
        .stage-logo { height: 8vh; object-fit: contain; }

        /* CENTER CONTENT */
        .center-wrapper { flex-grow: 1; display: flex; flex-direction: column; align-items: center; justify-content: center; padding-top: 5vh; z-index: 5;}
        .exam-info { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1.5vh; margin-bottom: 2vh;}
        .prog-title { font-size: 2vh; color: var(--sti-dark); background: var(--sti-yellow); font-weight: 900; letter-spacing: 0.4vw; text-transform: uppercase; padding: 0.8vh 3vw; border-radius: 5vh; }
        .subj-title { font-family: 'Oswald'; font-size: 6.5vh; color: var(--sti-blue); text-transform: uppercase; margin: 0; line-height: 1.1; letter-spacing: 0.1vw; max-width: 90vw; word-wrap: break-word;}
        .sec-title { font-size: 2.2vh; font-weight: 700; color: var(--text-gray); letter-spacing: 0.1vw; margin-top: -0.5vh;}
        
        .proctor-badge { margin-top: 1vh; font-family: 'Montserrat'; font-weight: 900; font-size: 1.8vh; background: var(--sti-blue); color: var(--white); padding: 0.8vh 2vw; border-radius: 1vh; letter-spacing: 0.1vw; display: none;}

        /* ✨ TIMER SIZING LOGIC */
        .timer-container { background: var(--white); padding: 3vh 6vw; border-radius: 2vh; border: 2px solid var(--border); display: flex; flex-direction: column; align-items: center; justify-content: center; box-shadow: 0 1vh 3vh rgba(0,0,0,0.05); transition: 0.4s ease-in-out; min-width: 60vw; max-width: 1200px;}
        .timer-label { font-size: 2.5vh; font-weight: 900; letter-spacing: 0.5vw; color: var(--text-gray); text-transform: uppercase; margin-bottom: -1vh; transition: 0.4s;}
        
        .timer-display { font-family: 'JetBrains Mono', monospace; font-size: 22vh; font-weight: 900; line-height: 1; letter-spacing: -0.2vw; color: var(--sti-blue); transition: 0.4s ease-in-out;}
        .timer-large { font-size: 26vh; color: var(--sti-blue); }
        .timer-small { font-size: 10vh; color: var(--text-gray); margin-top: 2vh;}

        .timer-danger-box { border: 4px solid var(--red); background: rgba(255,11,47,0.05); }
        .time-danger { color: var(--red) !important; font-weight: 700;}
        .label-danger { color: var(--red) !important; }

        /* 📢 DYNAMIC FLEXBOX REMINDER */
        #pre-exam-reminder { 
            display: none; 
            flex-direction: row; 
            align-items: center; 
            justify-content: center; 
            max-width: 85vw; 
            margin-top: 3vh; 
            gap: 3vw;
            transition: 0.4s ease-in-out; 
        }
        
        #reminder-text {
            flex: 1; 
            text-align: right;
        }

        .reminder-large { font-family: 'Oswald'; font-size: 4.5vh; color: var(--sti-blue); line-height: 1.3; animation: pulse-text 2s infinite alternate;}
        
        .reminder-small { font-family: 'Montserrat'; font-size: 2.2vh; font-weight: 700; color: var(--text-gray); line-height: 1.5; }
        .reminder-small #reminder-text { text-align: center; }

        .reminder-gif {
            flex: 0 0 auto; 
            max-height: 25vh; 
            width: auto; 
            max-width: 40vw; 
            object-fit: contain;
            border-radius: 1.5vh;
            border: 4px solid var(--sti-blue);
            box-shadow: 0 1vh 2.5vh rgba(0, 54, 102, 0.15);
        }

        @keyframes pulse-text { 0% { transform: scale(0.98); opacity: 0.9; } 100% { transform: scale(1.02); opacity: 1; color: var(--sti-dark); } }

        /* 🚀 UPWARD WIPE TRANSITION */
        #wipe-screen {
            position: fixed; top: 100vh; left: 0; width: 100vw; height: 100vh;
            background: var(--sti-yellow); z-index: 99999;
            display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
        }
        
        .wipe-gif { 
            max-width: 50vw; max-height: 40vh; object-fit: contain; margin-bottom: 3vh; 
            border-radius: 2vh; border: 4px solid var(--sti-blue); box-shadow: 0 1vh 3vh rgba(0,0,0,0.3); 
        }
        #wipe-text { font-family: 'Oswald'; font-size: 8vh; color: var(--sti-blue); line-height: 1.1; letter-spacing: 0.2vw; text-transform: uppercase; padding: 0 5vw; text-shadow: 0 0.5vh 2vh rgba(0,54,102,0.2);}
        
        /* 🎛️ HIDDEN BOTTOM CONTROL BAR */
        .trigger-zone { position: fixed; bottom: 0; left: 0; width: 100vw; height: 15vh; z-index: 9998; }
        .bottom-control-bar {
            position: fixed; bottom: 0; left: 0; width: 100vw; height: 12vh;
            background: linear-gradient(to top, rgba(0, 18, 36, 0.95), transparent);
            display: flex; justify-content: center; align-items: flex-end; padding-bottom: 3vh; gap: 1vw;
            z-index: 9999; transform: translateY(15vh); opacity: 0; transition: 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275);
        }
        .trigger-zone:hover + .bottom-control-bar, .bottom-control-bar:hover { transform: translateY(0); opacity: 1; }
        
        .btn-hidden-ctrl {
            background: var(--white); color: var(--sti-blue); border: 2px solid var(--white); padding: 1.5vh 3vw; border-radius: 5vh;
            font-family: 'Oswald'; font-size: 2vh; font-weight: 700; letter-spacing: 0.1vw; cursor: pointer;
            box-shadow: 0 1vh 2vh rgba(0,0,0,0.3); transition: 0.2s; text-transform: uppercase;
        }
        .btn-hidden-ctrl:hover { background: var(--sti-yellow); color: var(--sti-dark); border-color: var(--sti-yellow); transform: scale(1.05); }

        /* 🪟 MODALS */
        .modal-overlay { display: none; position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(0, 31, 63, 0.8); z-index: 10000; align-items: center; justify-content: center; backdrop-filter: blur(5px); animation: fadeIn 0.2s ease-out;}
        @keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
        
        .admin-panel-box { background: var(--bg); padding: 4vh 4vw; border-radius: 2vh; border: 1px solid var(--border); box-shadow: 0 2vh 5vh rgba(0,0,0,0.5); width: 40vw; min-width: 400px;}
        .admin-panel-box h2 { font-family: 'Oswald'; font-size: 4vh; color: var(--sti-blue); margin: 0 0 1vh 0; letter-spacing: 0.1vw; text-transform: uppercase; border-bottom: 2px solid var(--border); padding-bottom: 1vh;}
        .admin-panel-box p { font-size: 1.5vh; color: var(--text-gray); margin-bottom: 2vh; font-weight: 600;}
        
        .input-row { display: flex; gap: 1vw; margin-bottom: 1.5vh; }
        .admin-panel-box input, .admin-panel-box select { padding: 1.5vh; font-family: 'Montserrat'; font-size: 1.5vh; font-weight: 600; border: 2px solid var(--border); border-radius: 0.5vh; background: var(--white); color: var(--text-dark); width: 100%; transition: 0.2s;}
        .admin-panel-box input:focus, .admin-panel-box select:focus { border-color: var(--sti-blue); outline: none; }
        
        .btn { display: block; width: 100%; padding: 2vh; font-family: 'Oswald'; font-size: 2vh; font-weight: 700; border: none; border-radius: 0.5vh; cursor: pointer; text-transform: uppercase; transition: 0.2s; letter-spacing: 0.1vw; margin-top: 1vh;}
        .btn-blue { background: var(--sti-blue); color: var(--white); }
        .btn-blue:hover { filter: brightness(1.2); transform: translateY(-2px); }
        .btn-yellow { background: var(--sti-yellow); color: var(--sti-dark); }
        .btn-yellow:hover { filter: brightness(1.1); transform: translateY(-2px); }
        .btn-red { background: rgba(255,11,47,0.1); color: var(--red); border: 2px solid var(--red); }
        .btn-red:hover { background: var(--red); color: var(--white); }
        .btn-close { background: transparent; color: var(--text-gray); margin-top: 2vh; font-family: 'Montserrat'; font-weight: 900;}
        .btn-close:hover { color: var(--text-dark); }
    </style>
</head>
<body>
    <audio id="start-chime" src="uploads/chime/chime.mp3" preload="auto"></audio>

    <div id="gate-system" class="gate-screen">
        <div class="gate-box">
            <h2>SYSTEM LOCKED</h2>
            <p>Enter the master password to authorize this device.</p>
            <input type="password" id="sys-pass" placeholder="••••••••" onkeypress="if(event.key === 'Enter') verifySystemPass()">
            <button onclick="verifySystemPass()">AUTHORIZE DEVICE</button>
            <div id="sys-error" class="gate-error">INCORRECT PASSWORD</div>
        </div>
    </div>

    <div id="gate-room" class="gate-screen">
        <div class="gate-box">
            <h2>INITIALIZE ROOM</h2>
            <p>Assign this TV to a specific room for today.</p>
            <input type="text" id="setup-room" placeholder="E.g. 502" autocomplete="off" onkeypress="if(event.key === 'Enter') saveRoom()">
            <button onclick="saveRoom()">BIND TO ROOM</button>
        </div>
    </div>

    <div id="wipe-screen">
        <img src="" id="wipe-gif" class="wipe-gif" style="display:none;" onerror="this.style.display='none'">
        <div id="wipe-text">PREPARING ROOM...</div>
    </div>

    <div id="idle-screen">
        <img src="uploads/logo.webp" class="global-logo" id="idle-logo" onerror="this.src='../uploads/logo.webp'; this.onerror=function(){this.style.display='none'};">
        <div class="idle-clock" id="idle-clock-main">00:00 AM</div>
        <div class="idle-room" id="idle-room-day" onclick="openRoomSetup()" style="cursor:pointer;" title="Click to change room">ROOM --- <span>|</span> STANDBY</div>
        
        <div id="next-exam-bar">
            <div class="nex-lbl">NEXT EXAM TODAY</div>
            <div class="nex-time" id="nex-time">--:-- AM</div>
            <div class="nex-subj" id="nex-subj">SUBJECT NAME</div>
            <div class="nex-sec" id="nex-sec">SECTION</div>
        </div>
    </div>

    <div id="exam-stage">
        <div class="top-bar">
            <div class="room-badge" onclick="openRoomSetup()" style="cursor:pointer;" title="Click to change room">
                <span>ROOM</span>
                <strong id="stg-room">---</strong>
            </div>
            <div class="sys-clock-box">
                <span>CURRENT TIME</span>
                <div class="sys-clock-small" id="stg-clock">00:00:00 AM</div>
                <div class="sys-date-small" id="stg-date">MON, JAN 01</div>
            </div>
        </div>
        <div class="stage-logo-container">
            <img src="uploads/logo.webp" class="stage-logo global-logo" id="stg-logo" onerror="this.src='../uploads/logo.webp'; this.onerror=function(){this.style.display='none'};">
        </div>

        <div class="center-wrapper">
            <div class="exam-info">
                <div class="prog-title" id="stg-prog">PROGRAM</div>
                <h1 class="subj-title" id="stg-subj">SUBJECT NAME</h1>
                <div class="sec-title" id="stg-sec">SECTION</div>
                <div class="proctor-badge" id="stg-proctor">PROCTOR: <span id="stg-proc-name">NAME</span></div>
            </div>

            <div class="timer-container" id="stg-timer-box">
                <div class="timer-label" id="stg-timer-label">STARTS IN</div>
                <div class="timer-display" id="stg-timer">00:00</div>
            </div>

            <div id="pre-exam-reminder">
                <div id="reminder-text">
                    Please hide and silence your phones/devices and keep your bags under your chair. Use only black pen in answering the scantron.<br>GOODLUCK! :D
                </div>
                <img id="reminder-gif" class="reminder-gif" src="" style="display: none;" onerror="this.style.display='none'">
            </div>
        </div>
    </div>

    <div class="trigger-zone"></div>
    <div class="bottom-control-bar" id="bottom-controls">
        <button class="btn-hidden-ctrl" onclick="openExamControls()">⚙️ EXAM CONTROLS</button>
        <button class="btn-hidden-ctrl" onclick="document.getElementById('modal-bypass').style.display='flex'; event.stopPropagation();">⏱️ MANUAL BYPASS</button>
    </div>

    <div id="modal-controls" class="modal-overlay">
        <div class="admin-panel-box" onclick="event.stopPropagation()">
            <h2>⚙️ EXAM CONTROLS</h2>
            <p>Exams auto-start at exact schedule. Use this to force actions.</p>
            <button class="btn btn-yellow" id="btn-force-start" onclick="proctorAction('start')">▶ FORCE START EXAM</button>
            <button class="btn btn-red" id="btn-force-end" onclick="endExamRoutine()">⏹ CONCLUDE EXAM</button>
            <button class="btn btn-close" onclick="closeAllModals()">✖ CANCEL</button>
        </div>
    </div>

    <div id="modal-bypass" class="modal-overlay">
        <div class="admin-panel-box" onclick="event.stopPropagation()">
            <h2>⏱️ CUSTOM TIMER</h2>
            <p>Set a custom countdown. This overrides the current schedule.</p>
            <input type="text" id="manual-subj" placeholder="Custom Subject / Activity Name" autocomplete="off" style="margin-bottom:1.5vh; width:100%;">
            <input type="number" id="manual-mins" placeholder="Duration (Minutes)" style="width:100%;">
            <button class="btn btn-yellow" onclick="startManualBypass()">▶ START OVERRIDE</button>
            <button class="btn btn-close" onclick="closeAllModals()">✖ CANCEL</button>
        </div>
    </div>

    <script>
        let myRoom = 'UNKNOWN';
        let currentBundle = [];
        let isManualMode = false;
        let manualData = null;
        let systemData = null;
        
        let autoStartTriggered = false;
        let autoEndTriggered = false;
        let chimed10Min = false;
        let chimed3Min = false;
        let reminderGifChosen = false;

        let currentAppPhase = ''; 
        let currentExamId = '';
        let isWiping = false;

        document.addEventListener('click', function(e) {
            if (!document.fullscreenElement && !e.target.closest('.modal-box') && !e.target.closest('input')) {
                document.documentElement.requestFullscreen().catch(err => {
                    console.log("Fullscreen request blocked by browser.");
                });
            }
        });

        function bootSystem() {
            if (!localStorage.getItem('sti_sys_auth')) {
                document.getElementById('gate-system').style.display = 'flex';
                return;
            }
            if (!localStorage.getItem('sti_room')) {
                document.getElementById('gate-room').style.display = 'flex';
                return;
            }

            myRoom = localStorage.getItem('sti_room');
            document.getElementById('idle-room-day').innerHTML = `ROOM ${myRoom} <span>|</span> CONNECTING...`;
            initApp();
        }
        
        async function verifySystemPass() {
            const pass = document.getElementById('sys-pass').value;
            const err = document.getElementById('sys-error');
            if(!pass) { err.innerText = "PASSWORD REQUIRED"; err.style.display = 'block'; return; }
            
            err.innerText = "VERIFYING..."; err.style.display = 'block';
            try {
                const fd = new URLSearchParams();
                fd.append('action', 'verify_proctor');
                fd.append('password', pass);
                
                const res = await fetch('', { method: 'POST', body: fd });
                const data = await res.json();
                
                if (data.success) {
                    localStorage.setItem('sti_sys_auth', 'true');
                    document.getElementById('gate-system').style.display = 'none';
                    bootSystem();
                } else {
                    err.innerText = "ACCESS DENIED";
                }
            } catch (e) {
                localStorage.setItem('sti_sys_auth', 'true');
                document.getElementById('gate-system').style.display = 'none';
                bootSystem();
            }
        }

        function saveRoom() {
            const room = document.getElementById('setup-room').value.trim().toUpperCase();
            if (!room) return;
            localStorage.setItem('sti_room', room); 
            document.getElementById('gate-room').style.display = 'none';
            location.reload(); 
        }

        function openRoomSetup() {
            document.getElementById('setup-room').value = myRoom !== 'UNKNOWN' ? myRoom : '';
            document.getElementById('gate-room').style.display = 'flex';
        }
        
        function initApp() {
            setInterval(updateStageClocks, 1000); 
            fetchRoutine();
            setInterval(fetchRoutine, 4000);
            
            // 📡 THE MCR LISTENER & HEARTBEAT (New!)
            setInterval(sendHeartbeat, 10000);
        }

        async function sendHeartbeat() {
            if (myRoom === 'UNKNOWN') return;
            try {
                const fd = new URLSearchParams();
                fd.append('room', myRoom);
                fd.append('phase', currentAppPhase || 'IDLE');
                
                const res = await fetch('admin-mcr.php?action=ping', { method: 'POST', body: fd });
                const data = await res.json();
                
                // 🕹️ EXECUTE MCR REMOTE COMMANDS
                if (data && data.command) {
                    if (data.command === 'refresh') {
                        triggerWipe("REBOOTING TV...", () => { window.location.reload(true); }, 2000);
                    } else if (data.command === 'start') {
                        proctorAction('start');
                    } else if (data.command === 'end') {
                        endExamRoutine();
                    } else if (data.command === 'bypass') {
                        let p = JSON.parse(data.payload);
                        document.getElementById('manual-subj').value = p.subj;
                        document.getElementById('manual-mins').value = p.mins;
                        startManualBypass();
                    }
                }
            } catch(e) {}
        }

        async function fetchRoutine() {
            if (isManualMode || isWiping) return; 
            try {
                const res = await fetch(`api.php?action=get_all&t=${Date.now()}`);
                const text = await res.text();
                let data;
                try { data = JSON.parse(text); } catch(e) { return; } 
                systemData = data;
                
                if (data.settings && data.settings.logo) {
                    document.querySelectorAll('.global-logo').forEach(img => {
                        img.src = data.settings.logo;
                        img.style.display = 'block';
                    });
                }
                processExamState(data);
            } catch(e) { }
        }

        function processExamState(data) {
            if (isWiping || currentAppPhase === 'WIPING_END') return; 

            const now = new Date();
            const todayISO = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            const nowUnix = Math.floor(now.getTime() / 1000);
            
            let currentDayString = "STANDBY";
            let currentDayNumber = 0;

            if (data.settings && data.settings.exam_days) {
                for (let i = 1; i <= 5; i++) {
                    if (data.settings.exam_days[i] === todayISO) { 
                        currentDayString = "DAY " + i; 
                        currentDayNumber = parseInt(i);
                        break; 
                    }
                }
            }
            document.getElementById('idle-room-day').innerHTML = `ROOM ${myRoom} <span>|</span> ${currentDayString}`;

            let schedules = JSON.parse(JSON.stringify(data.schedules || []));
            
            // ⏰ TIME SHIFT LOGIC (15 MINS BUFFER TO START)
            schedules.forEach(s => {
                let announcedTime = parseInt(s.start_time) - 1800; // Reverts Admin's 30m offset
                s.original_scheduled = announcedTime; // TRUE DB Time (e.g. 8:00 AM)
                s.start_time = announcedTime + 900;   // Actual Start: +15 mins (e.g. 8:15 AM)
                s.end_time = parseInt(s.end_time) - 1800 + 900; 
            });

            const pendingOrOngoing = schedules.filter(s => s.room === myRoom && s.status !== 'done' && parseInt(s.day) === currentDayNumber);
            pendingOrOngoing.sort((a,b) => a.start_time - b.start_time);
            
            let newAppPhase = '';
            let newExamId = '';
            let wipeText = '';

            if (pendingOrOngoing.length === 0) {
                newAppPhase = 'IDLE';
                wipeText = "ROOM STANDBY";
            } else {
                const nextExam = pendingOrOngoing[0];
                let diff = nextExam.start_time - nowUnix;
                newExamId = nextExam.id;

                if (nextExam.status === 'ongoing') {
                    newAppPhase = 'ONGOING';
                    wipeText = "EXAM ONGOING";
                } else if (nextExam.status === 'pending' && diff <= 1800) { 
                    newAppPhase = 'REVIEW';
                    wipeText = nextExam.subject;
                } else {
                    newAppPhase = 'STANDBY_NEXT';
                    wipeText = "STANDBY";
                }
            }

            // Phase Change Detection
            if (currentAppPhase !== newAppPhase || currentExamId !== newExamId) {
                let isFirstBoot = (currentAppPhase === '');
                currentAppPhase = newAppPhase;
                currentExamId = newExamId;

                if (isFirstBoot && (newAppPhase === 'IDLE' || newAppPhase === 'STANDBY_NEXT')) {
                    showScreen('idle');
                    if (newAppPhase === 'STANDBY_NEXT') populateNextExamBar(pendingOrOngoing[0], pendingOrOngoing);
                    else document.getElementById('next-exam-bar').classList.remove('show');
                    return;
                }

                autoStartTriggered = false;
                autoEndTriggered = false;
                chimed10Min = false;
                chimed3Min = false;
                reminderGifChosen = false;

                triggerWipe(wipeText, () => {
                    if (newAppPhase === 'IDLE' || newAppPhase === 'STANDBY_NEXT') {
                        currentBundle = [];
                        showScreen('idle');
                        if (newAppPhase === 'STANDBY_NEXT') populateNextExamBar(pendingOrOngoing[0], pendingOrOngoing);
                        else document.getElementById('next-exam-bar').classList.remove('show');
                    } else {
                        currentBundle = pendingOrOngoing.filter(e => e.start_time === pendingOrOngoing[0].start_time);
                        document.getElementById('next-exam-bar').classList.remove('show');
                        updateStageUI();
                    }
                }, 3500);

            } else {
                if (newAppPhase === 'REVIEW' || newAppPhase === 'ONGOING') {
                    currentBundle = pendingOrOngoing.filter(e => e.start_time === pendingOrOngoing[0].start_time);
                    updateStageUI(); 
                } else if (newAppPhase === 'STANDBY_NEXT') {
                    populateNextExamBar(pendingOrOngoing[0], pendingOrOngoing);
                }
            }
        }

        function populateNextExamBar(mainExam, allPending) {
            const blockExams = allPending.filter(e => e.start_time === mainExam.start_time);
            const uniqueSecs = [...new Set(blockExams.map(s => s.section_code))].join(', ');
            
            const d = new Date((mainExam.original_scheduled || mainExam.start_time) * 1000); 
            let h = d.getHours(), m = d.getMinutes(), ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            const timeStr = `${h}:${m < 10 ? '0'+m : m} ${ampm}`;

            document.getElementById('nex-time').innerText = timeStr;
            document.getElementById('nex-subj').innerText = mainExam.subject;
            document.getElementById('nex-sec').innerText = uniqueSecs;
            document.getElementById('next-exam-bar').classList.add('show');
        }

        function openExamControls() {
            event.stopPropagation();
            const bStart = document.getElementById('btn-force-start');
            const bEnd = document.getElementById('btn-force-end');
            if (isManualMode || (currentBundle.length > 0 && currentBundle[0].status === 'ongoing')) {
                bStart.style.display = 'none'; bEnd.style.display = 'block';
            } else {
                bStart.style.display = 'block'; bEnd.style.display = 'none';
            }
            document.getElementById('modal-controls').style.display = 'flex';
        }

        function closeAllModals() {
            document.querySelectorAll('.modal-overlay').forEach(m => m.style.display = 'none');
        }

        function startManualBypass() {
            const mins = parseInt(document.getElementById('manual-mins').value);
            const subj = document.getElementById('manual-subj').value.trim() || 'CUSTOM MANUAL EXAM';
            
            if (!mins || mins < 1) { alert("Invalid duration."); return; }
            
            isManualMode = true;
            const nowUnix = Math.floor(Date.now() / 1000);
            
            manualData = {
                subject: subj,
                actual_start: nowUnix,
                start_time: nowUnix, 
                end_time: nowUnix + (mins * 60),
                status: 'ongoing',
                prog_code: 'CUSTOM',
                proctor: 'MANUAL OVERRIDE'
            };

            closeAllModals();
            sendLog(`⚠️ MANUAL OVERRIDE: Room ${myRoom} started a custom ${mins}-minute timer.`);
            
            currentAppPhase = 'MANUAL';
            triggerWipe("STARTING CUSTOM TIMER", () => {
                showScreen('stage');
                updateStageUI();
            }, 3500, 'start');
        }

        function showScreen(screen) {
            document.getElementById('idle-screen').style.display = screen === 'idle' ? 'flex' : 'none';
            document.getElementById('exam-stage').style.display = screen === 'stage' ? 'flex' : 'none';
        }

        // 🖼️ PRELOAD ALL GIFS 
        const preloadedGifs = [];
        for (let i = 1; i <= 5; i++) {
            const g1 = new Image(); g1.src = `uploads/gif/${i}.gif`; preloadedGifs.push(g1);
            const g2 = new Image(); g2.src = `uploads/rem/${i}.gif`; preloadedGifs.push(g2);
        }

        // 🚀 WIPE TRANSITION 
        function triggerWipe(text, midCallback, stayDuration, actionType = "") {
            if (isWiping) return;
            isWiping = true;
            
            const chime = document.getElementById('start-chime');
            if (chime) {
                chime.currentTime = 0;
                chime.play().catch(e => {});
            }
            
            const wipe = document.getElementById('wipe-screen');
            const gif = document.getElementById('wipe-gif');
            const textEl = document.getElementById('wipe-text');

            const cheers = [
                "Goodluck!", 
                "Galingan nyo goiz!", 
                "1..2..3... gew!", 
                "Agnas!!! 🔥", 
                "Lezzgaur!", 
                "Yizz! Galing!",
                "Kaya niyo 'yan!",
                "Dasal dasal na lang!"
            ];

            if (text && text !== "STANDBY" && text !== "ROOM STANDBY") {
                textEl.innerText = text;
            } else {
                textEl.innerText = cheers[Math.floor(Math.random() * cheers.length)];
            }
            
            const randGif = Math.floor(Math.random() * 5) + 1;
            gif.style.display = 'block';
            gif.onerror = function() {
                if (!this.src.includes('../')) {
                    this.src = `../uploads/gif/${randGif}.gif`;
                    this.onerror = function() { this.style.display = 'none'; };
                }
            };
            gif.src = `uploads/gif/${randGif}.gif`;
            
            wipe.style.transition = "transform 0.6s cubic-bezier(0.85, 0, 0.15, 1)";
            wipe.style.transform = "translateY(-100vh)"; 
            
            setTimeout(() => {
                if (midCallback) midCallback();
                
                setTimeout(() => {
                    wipe.style.transform = "translateY(-200vh)"; 
                    
                    setTimeout(() => {
                        wipe.style.transition = "none";
                        wipe.style.transform = "translateY(0)"; 
                        isWiping = false;
                    }, 600);
                }, stayDuration); 
            }, 600); 
        }
        
        function updateStageUI() {
            showScreen('stage');
            let examObj = isManualMode ? manualData : (currentBundle.length > 0 ? currentBundle[0] : null);
            if (!examObj) return;

            let pNameRaw = examObj.proctor || "TBA";
            const procBadge = document.getElementById('stg-proctor');

            if (isManualMode) {
                procBadge.style.display = 'block';
                procBadge.className = 'proctor-badge';
                procBadge.innerHTML = `PROCTOR: <span style="color:var(--sti-yellow);">MANUAL OVERRIDE</span>`;
            } else if (pNameRaw === "TBA" || pNameRaw.trim() === "") {
                procBadge.style.display = 'none';
            } else {
                let parts = pNameRaw.split(',');
                let lastName = parts[0].trim();
                if (parts.length === 1) {
                    let spaceParts = pNameRaw.trim().split(' ');
                    lastName = spaceParts[spaceParts.length - 1]; 
                }
                procBadge.style.display = 'block';
                procBadge.className = 'proctor-badge';
                procBadge.innerHTML = `PROCTOR: <span style="color:var(--sti-yellow);">${lastName.toUpperCase()}</span>`;
            }

            const parsedProgs = (systemData && systemData.settings && systemData.settings.programs) ? systemData.settings.programs : {};
            const progName = isManualMode ? "GOODLUCK! :D" : (parsedProgs[examObj.prog_code] || examObj.prog_code);
            const uniqueSecs = isManualMode ? "BYPASSED TIMER MODE" : [...new Set(currentBundle.map(s => s.section_code))].join(', ');

            document.getElementById('stg-room').innerText = myRoom;
            document.getElementById('stg-prog').innerText = progName;
            document.getElementById('stg-subj').innerText = examObj.subject;
            document.getElementById('stg-sec').innerText = uniqueSecs;

            updateStageClocks();
        }

        function updateStageClocks() {
            const now = new Date();
            let h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
            let ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12; 
            
            const timeStr = `${h}:${m < 10 ? '0'+m : m}:${s < 10 ? '0'+s : s} ${ampm}`;
            const shortTimeStr = `${h}:${m < 10 ? '0'+m : m} ${ampm}`;
            
            document.getElementById('stg-clock').innerText = timeStr;
            document.getElementById('idle-clock-main').innerText = shortTimeStr;
            
            const options = { weekday: 'short', month: 'short', day: '2-digit' };
            document.getElementById('stg-date').innerText = now.toLocaleDateString('en-US', options).toUpperCase();

            // EXAM TIMER LOGIC
            let examObj = isManualMode ? manualData : (currentBundle.length > 0 ? currentBundle[0] : null);
            if (examObj && document.getElementById('exam-stage').style.display === 'flex') {
                const nowUnix = Math.floor(now.getTime() / 1000);
                const timerBox = document.getElementById('stg-timer-box');
                const timerLbl = document.getElementById('stg-timer-label');
                const timerDisp = document.getElementById('stg-timer');
                const reminderDisp = document.getElementById('pre-exam-reminder');

                if (examObj.status === 'pending') {
                    let diff = examObj.start_time - nowUnix;
                    let isNeg = diff < 0;
                    let absDiff = Math.abs(diff);

                    timerBox.className = isNeg ? 'timer-container timer-danger-box' : 'timer-container';
                    timerLbl.className = isNeg ? 'timer-label label-danger' : 'timer-label';
                    timerLbl.innerText = isNeg ? 'LATE START' : 'STARTS IN';
                    
                    // 📢 10-Min / 3-Min Reminder Logic
                    if (!isNeg && diff <= 600 && diff > 0) { 
                        reminderDisp.style.display = 'flex'; 
                        const remGifEl = document.getElementById('reminder-gif');
                        
                        if (!chimed10Min) {
                            chimed10Min = true;
                            let c10 = document.getElementById('start-chime');
                            if (c10) { c10.currentTime = 0; c10.play().catch(e => {}); }
                        }

                        if (diff > 180) { 
                            reminderDisp.className = 'reminder-large';
                            timerDisp.className = 'timer-display timer-small';

                            if (remGifEl) {
                                if (!reminderGifChosen) {
                                    reminderGifChosen = true;
                                    const rand10 = Math.floor(Math.random() * 5) + 1;
                                    remGifEl.src = `uploads/rem/${rand10}.gif`;
                                }
                                remGifEl.style.display = 'block';
                            }
                        } else { 
                            if (!chimed3Min) {
                                chimed3Min = true;
                                let c3 = document.getElementById('start-chime');
                                if (c3) { c3.currentTime = 0; c3.play().catch(e => {}); }
                            }

                            if (remGifEl) {
                                remGifEl.style.display = 'none';
                            }

                            reminderDisp.className = 'reminder-small';
                            timerDisp.className = 'timer-display timer-large';
                        }
                    } else {
                        reminderDisp.style.display = 'none';
                        const remGifEl = document.getElementById('reminder-gif');
                        if (remGifEl) remGifEl.style.display = 'none';
                        reminderGifChosen = false;
                        timerDisp.className = isNeg ? 'timer-display time-danger' : 'timer-display timer-large';
                    }
                    
                    // AUTO START AT 00:00
                    if (diff === 0 && !autoStartTriggered && !isNeg) {
                        autoStartTriggered = true;
                        currentAppPhase = 'ONGOING'; 
                        triggerWipe("START ANSWERING NOW", () => {
                            proctorAction('start', true); 
                        }, 3500, 'start');
                    }

                    // Thousands limit cap fix
                    let th = Math.floor(absDiff / 3600);
                    if (th > 99) th = 99;
                    const tm = Math.floor((absDiff % 3600) / 60);
                    const ts = absDiff % 60;
                    let tStr = `${th > 0 ? th+':' : ''}${tm < 10 ? '0'+tm : tm}:${ts < 10 ? '0'+ts : ts}`;
                    timerDisp.innerText = isNeg ? `-${tStr}` : tStr;

                    } else if (examObj.status === 'ongoing') {
                    reminderDisp.style.display = 'none'; 
                    
                    const duration = examObj.end_time - examObj.start_time;
                    const expected_end = (examObj.actual_start || examObj.start_time) + duration;
                    let diff = expected_end - nowUnix;
                    
                    if (diff <= 0 && !autoEndTriggered) {
                        autoEndTriggered = true;
                        endExamRoutine(); 
                        return; 
                    }

                    let absDiff = Math.abs(diff);
                    timerBox.className = 'timer-container';
                    timerLbl.className = 'timer-label';
                    timerLbl.innerText = 'TIME REMAINING';
                    timerDisp.className = 'timer-display timer-large';
                    
                    const th = Math.floor(absDiff / 3600);
                    const tm = Math.floor((absDiff % 3600) / 60);
                    const ts = absDiff % 60;
                    let tStr = `${th > 0 ? th+':' : ''}${tm < 10 ? '0'+tm : tm}:${ts < 10 ? '0'+ts : ts}`;
                    timerDisp.innerText = tStr;
                }            }
        }

        function proctorAction(actionType, skipWipe = false) {
            closeAllModals();
            if (isManualMode || currentBundle.length === 0) return;
            const newStatus = actionType === 'start' ? 'ongoing' : 'done';
            
            currentBundle.forEach(e => {
                fetch(`api.php?action=status`, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ id: e.id, value: newStatus })
                });
            });

            if (actionType === 'start') {
                sendLog(`✅ Room ${myRoom} STARTED their exam.`);
                currentAppPhase = 'ONGOING';
                
                if (!skipWipe) {
                    triggerWipe("EXAM ONGOING", () => {
                        currentBundle.forEach(e => {
                            e.status = newStatus;
                            if(!e.actual_start) e.actual_start = Math.floor(Date.now() / 1000);
                        });
                        updateStageUI();
                    }, 3500, 'start');
                } else {
                    currentBundle.forEach(e => {
                        e.status = newStatus;
                        if(!e.actual_start) e.actual_start = Math.floor(Date.now() / 1000);
                    });
                    updateStageUI();
                }
            }
        }

        function endExamRoutine() {
            closeAllModals();
            sendLog(`✅ Room ${myRoom} ENDED their exam.`);
            
            const endPhrases = ["EXAM CONCLUDED!", "GOOD LUCK!", "GALINGAN NYO GOIZ!", "PASA NA YAN!", "DASAL DASAL NA LANG!"];
            let randomText = endPhrases[Math.floor(Math.random() * endPhrases.length)];
            
            if (!isManualMode) { 
                currentBundle.forEach(e => {
                    fetch(`api.php?action=status`, {
                        method: 'POST', headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ id: e.id, value: 'done' })
                    });
                });
            }
            
            currentAppPhase = 'WIPING_END'; 
            
            triggerWipe(randomText, () => {
                isManualMode = false;
                manualData = null;
                currentBundle = [];
                
                showScreen('idle');
                currentAppPhase = ''; 
                fetchRoutine(); 
            }, 3500, 'end'); 
        }

        function sendLog(logMessage) {
            fetch(`api.php?action=write_log`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ log: logMessage })
            }).catch(e=>{});
        }

        bootSystem();
    </script>
</body>
</html>