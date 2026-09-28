<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TEST MODE - STI Exam Room</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;600;700;900&family=Oswald:wght@500;700&family=JetBrains+Mono:wght@700;900&display=swap" rel="stylesheet">
    <style>
        /* 🎨 LIGHTWEIGHT PALETTE */
        :root { 
            --sti-yellow: #fDD000; 
            --sti-blue: #003666; 
            --white: #ffffff; 
            --bg: #f4f7f9;
            --text-dark: #001f3f;
            --text-gray: #657786;
            --red: #FF0B2F; 
            --green: #00b35c;
            --border: #e1e8ed;
        }
        
        body, html { 
            margin: 0; padding: 0; width: 100vw; height: 100vh; 
            color: var(--text-dark); font-family: 'Montserrat', sans-serif; 
            overflow: hidden; background: var(--bg); 
        }
        * { box-sizing: border-box; }

        /* 🧪 FLOATING TEST PANEL */
        #test-panel {
            position: fixed; top: 1vh; left: 1vw; background: rgba(0, 31, 63, 0.9);
            color: white; padding: 1.5vh; border-radius: 1vh; z-index: 9999999;
            display: flex; flex-direction: column; gap: 1vh; font-family: 'Montserrat';
            border: 2px solid var(--sti-yellow); box-shadow: 0 1vh 3vh rgba(0,0,0,0.5);
            backdrop-filter: blur(5px);
        }
        #test-panel h3 { margin: 0; font-family: 'Oswald'; font-size: 2vh; color: var(--sti-yellow); letter-spacing: 0.1vw; text-transform: uppercase; border-bottom: 1px solid rgba(255,255,255,0.2); padding-bottom: 0.5vh;}
        .test-btn {
            background: rgba(255,255,255,0.1); border: 1px solid rgba(255,255,255,0.3); color: white;
            padding: 1vh 1vw; border-radius: 0.5vh; font-size: 1.2vh; font-weight: 700; cursor: pointer;
            text-align: left; transition: 0.2s; font-family: 'Montserrat'; text-transform: uppercase;
        }
        .test-btn:hover { background: var(--sti-yellow); color: var(--sti-dark); border-color: var(--sti-yellow); }
        .test-btn.danger { background: rgba(255,11,47,0.2); border-color: var(--red); color: #ff8a9a;}
        .test-btn.danger:hover { background: var(--red); color: white; }

        /* 😴 IDLE SCREEN */
        #idle-screen { display: none; flex-direction: column; align-items: center; justify-content: center; height: 100vh; position: relative; z-index: 2;}
        #idle-logo { height: 18vh; margin-bottom: 2vh; object-fit: contain; } 
        .idle-clock { font-family: 'JetBrains Mono', monospace; font-size: 16vh; font-weight: 700; color: var(--sti-blue); line-height: 1; margin-bottom: 1vh; letter-spacing: -0.2vw; }
        .idle-room { font-family: 'Montserrat'; font-size: 3vh; color: var(--text-dark); font-weight: 900; letter-spacing: 0.3vw; margin-bottom: 2vh; text-transform: uppercase; display: flex; align-items: center; gap: 1.5vw; background: var(--white); padding: 1vh 3vw; border-radius: 5vh; border: 2px solid var(--border);}
        .idle-room span { color: var(--text-gray); opacity: 0.5; font-weight: 400;}

        /* 🎬 EXAM STAGE */
        #exam-stage { display: flex; width: 100vw; height: 100vh; position: relative; z-index: 2; overflow: hidden; background: var(--bg); }
        
        .top-bar { position: absolute; top: 0; left: 0; width: 100vw; height: 12vh; display: flex; justify-content: space-between; align-items: center; padding: 0 4vw; z-index: 10; }
        .stage-logo-container { position: absolute; left: 50%; top: 3vh; transform: translateX(-50%); z-index: 11; }
        .stage-logo { height: 8vh; object-fit: contain; }
        
        .room-badge { background: var(--white); padding: 1vh 3vw; display: flex; flex-direction: column; justify-content: center; align-items: center; border-radius: 1.5vh; border: 2px solid var(--border); }
        .room-badge span { font-family: 'Montserrat'; font-size: 1.5vh; color: var(--text-gray); letter-spacing: 0.3vw; font-weight: 900; margin-bottom: -0.5vh;}
        .room-badge strong { font-family: 'Oswald'; font-size: 5vh; color: var(--sti-blue); line-height: 1;}
        
        .sys-clock-box { background: var(--white); padding: 1vh 3vw; display: flex; flex-direction: column; align-items: center; justify-content: center; border-radius: 1.5vh; border: 2px solid var(--border); min-width: 15vw;}
        .sys-clock-box span { font-family: 'Montserrat'; font-size: 1.2vh; color: var(--text-gray); letter-spacing: 0.2vw; font-weight: 700; text-transform: uppercase; margin-bottom: 0.2vh;}
        .sys-clock-small { font-family: 'JetBrains Mono', monospace; font-size: 3.5vh; font-weight: 700; color: var(--sti-blue); line-height: 1; letter-spacing: -0.1vw;}
        .sys-date-small { font-family: 'Montserrat'; font-size: 1.5vh; font-weight: 900; color: var(--text-gray); margin-top: 0.2vh; letter-spacing: 0.1vw; text-transform: uppercase;}

        /* Center Content */
        .center-wrapper { width: 100vw; height: 100vh; display: flex; flex-direction: column; align-items: center; justify-content: center; padding-top: 6vh; z-index: 5; }
        
        .exam-info { display: flex; flex-direction: column; align-items: center; text-align: center; gap: 1.5vh; margin-bottom: 3vh;}
        .prog-title { font-size: 2vh; color: var(--sti-dark); background: var(--sti-yellow); font-weight: 900; letter-spacing: 0.4vw; text-transform: uppercase; padding: 0.8vh 3vw; border-radius: 5vh; }
        .subj-title { font-family: 'Oswald'; font-size: 7vh; color: var(--sti-blue); text-transform: uppercase; margin: 0; line-height: 1.1; letter-spacing: 0.1vw; max-width: 90vw; word-wrap: break-word;}
        .sec-title { font-size: 2.2vh; font-weight: 700; color: var(--sti-dark); letter-spacing: 0.1vw; margin-top: -0.5vh;}
        .proctor-badge { margin-top: 1vh; font-family: 'Oswald'; font-size: 2.2vh; background: var(--sti-blue); color: var(--white); padding: 0.5vh 2vw; border-radius: 1vh; letter-spacing: 0.1vw; display: none;}

        /* ✨ SOLID TIMER BOX */
        .timer-container { background: var(--white); padding: 3vh 6vw; border-radius: 2vh; border: 2px solid var(--border); display: flex; flex-direction: column; align-items: center; justify-content: center; position: relative; min-width: 60vw; max-width: 1200px; transition: 0.4s ease-in-out;}
        .timer-label { font-size: 2.5vh; font-weight: 900; letter-spacing: 0.8vw; color: var(--text-gray); text-transform: uppercase; margin-bottom: -1vh;}
        .timer-display { font-family: 'JetBrains Mono', monospace; font-size: 26vh; font-weight: 900; line-height: 1; letter-spacing: -0.5vw; color: var(--sti-blue); transition: 0.4s ease-in-out;}
        
        .timer-small { font-size: 10vh; color: var(--text-gray); margin-top: 2vh; }

        .timer-danger-box { border: 4px solid var(--red); background: rgba(255,11,47,0.05); }
        .time-danger { color: var(--red) !important; }
        .label-danger { color: var(--red) !important; }

        /* 📢 PRE-EXAM REMINDER SCALING */
        #pre-exam-reminder { text-align: center; max-width: 70vw; margin-top: 2vh; transition: 0.4s ease-in-out; display: none; }
        .reminder-large { font-family: 'Oswald'; font-size: 5vh; color: var(--sti-blue); line-height: 1.3; animation: pulse-text 2s infinite alternate;}
        .reminder-small { font-family: 'Montserrat'; font-size: 2vh; font-weight: 700; color: var(--text-gray); line-height: 1.5; }
        @keyframes pulse-text { 0% { transform: scale(0.98); opacity: 0.9; } 100% { transform: scale(1.02); opacity: 1; color: var(--sti-dark); } }

        /* 🚀 BROADCAST WIPE TRANSITION */
        #wipe-screen {
            position: fixed; top: 0; left: 0; width: 100vw; height: 100vh;
            background: var(--sti-yellow); z-index: 99999;
            display: flex; flex-direction: column; align-items: center; justify-content: center; text-align: center;
            transition: transform 0.6s cubic-bezier(0.8, 0, 0.2, 1); transform: translateY(100vh);
        }
        #wipe-screen.slide-in { transform: translateY(0); }
        #wipe-screen.slide-out { transform: translateY(-100vh); }
        
        .wipe-gif { 
            max-width: 50vw; max-height: 40vh; object-fit: contain; margin-bottom: 3vh; 
            border-radius: 2vh; border: 4px solid var(--sti-blue); box-shadow: 0 1vh 3vh rgba(0,0,0,0.3); 
        }
        #wipe-text { font-family: 'Oswald'; font-size: 9vh; color: var(--sti-blue); line-height: 1.1; letter-spacing: 0.2vw; text-transform: uppercase; padding: 0 5vw;}

    </style>
</head>
<body>

    <!-- 🧪 FLOATING TEST PANEL -->
    <div id="test-panel">
        <h3>🧪 TEST SIMULATOR</h3>
        <button class="test-btn" onclick="simulateState('idle')">1. Standby Mode (Empty)</button>
        <button class="test-btn" onclick="simulateState('upcoming')">2. Upcoming (> 10m away)</button>
        <button class="test-btn" onclick="simulateState('prep_10')">3. Preparing (Large Reminder)</button>
        <button class="test-btn" onclick="simulateState('prep_3')">4. Preparing (Large Timer)</button>
        <button class="test-btn" style="border-color: var(--sti-yellow);" onclick="simulateState('auto_start')">5. ⚡ Test Auto-Start (5s)</button>
        <button class="test-btn" onclick="simulateState('ongoing')">6. Exam Ongoing</button>
        <button class="test-btn danger" onclick="simulateState('overtime')">7. Exam Overtime (Red)</button>
        <button class="test-btn danger" onclick="simulateEndWipe()">8. 🏁 Trigger End Wipe</button>
        <button class="test-btn" style="background:transparent; border:none; text-align:center; color:var(--text-gray);" onclick="document.getElementById('test-panel').style.display='none'">[ Hide Panel ]</button>
    </div>

    <!-- 🔔 AUDIO CHIME -->
    <audio id="chime-audio" src="../uploads/chime/chime.mp3" preload="auto"></audio>

    <!-- 🚀 WIPE TRANSITION -->
    <div id="wipe-screen">
        <img src="" id="wipe-gif" class="wipe-gif" style="display:none;" onerror="this.style.display='none'">
        <div id="wipe-text">PREPARING ROOM...</div>
    </div>

    <!-- 😴 IDLE SCREEN -->
    <div id="idle-screen">
        <img src="../uploads/logo.webp" id="idle-logo" onerror="this.src='../uploads/logo.png'; this.onerror=function(){this.style.display='none'};">
        <div class="idle-clock" id="idle-clock-main">00:00</div>
        <div class="idle-room">ROOM TEST <span>|</span> STANDBY</div>
    </div>

    <!-- 🎬 EXAM STAGE -->
    <div id="exam-stage">
        <div class="top-bar">
            <div class="room-badge">
                <span>ROOM</span>
                <strong id="stg-room">TEST</strong>
            </div>
            <img src="../uploads/logo.webp" class="stage-logo" id="stg-logo" onerror="this.src='../uploads/logo.png'; this.onerror=function(){this.style.display='none'};">
            <div class="sys-clock-box">
                <span>CURRENT TIME</span>
                <div class="sys-clock-small" id="stg-clock">00:00:00</div>
                <div class="sys-date-small" id="stg-date">MON, JAN 01</div>
            </div>
        </div>

        <div class="center-wrapper">
            <div class="exam-info">
                <div class="prog-title" id="stg-prog">TESTING PROGRAM</div>
                <h1 class="subj-title" id="stg-subj">SIMULATED SUBJECT</h1>
                <div class="sec-title" id="stg-sec">TEST-SECTION-1</div>
                <div class="proctor-badge" id="stg-proctor" style="display:block;">PROCTOR: <span style="color:var(--sti-yellow);">TEACHER TEST</span></div>
            </div>

            <div class="timer-container" id="stg-timer-box">
                <div class="timer-label" id="stg-timer-label">STARTS IN</div>
                <div class="timer-display" id="stg-timer">00:00</div>
            </div>

            <!-- 📢 PRE-EXAM REMINDER TEXT -->
            <div id="pre-exam-reminder">
                Please hide and silence your phones/devices. And keep your bags under your chair. Use only black pen in answering the scantron. Good luck!
            </div>
        </div>
    </div>

    <script>
        // --- 🧪 MOCK STATE MANAGEMENT ---
        let mockExam = null;
        let isWiping = false;
        window.autoStartedIds = new Set(); // Anti-duplicate auto-start

        function simulateState(type) {
            const now = Math.floor(Date.now() / 1000);
            document.getElementById('idle-screen').style.display = 'none';
            document.getElementById('exam-stage').style.display = 'flex';

            if (type === 'idle') {
                document.getElementById('idle-screen').style.display = 'flex';
                document.getElementById('exam-stage').style.display = 'none';
                mockExam = null;
                return;
            }

            // Create a fake exam object based on the requested phase
            mockExam = { id: 'test_123', status: 'pending', start_time: 0, end_time: 0, actual_start: 0 };

            if (type === 'upcoming') {
                mockExam.start_time = now + 900; // 15 mins away
                mockExam.status = 'pending';
            } else if (type === 'prep_10') {
                mockExam.start_time = now + 500; // ~8 mins away
                mockExam.status = 'pending';
            } else if (type === 'prep_3') {
                mockExam.start_time = now + 120; // 2 mins away
                mockExam.status = 'pending';
            } else if (type === 'auto_start') {
                window.autoStartedIds.clear(); // Reset so it can trigger again
                mockExam.start_time = now + 5; // Exactly 5 seconds away
                mockExam.status = 'pending';
            } else if (type === 'ongoing') {
                mockExam.start_time = now - 1800; // Started 30 mins ago
                mockExam.actual_start = now - 1800;
                mockExam.end_time = now + 1800; // Ends in 30 mins
                mockExam.status = 'ongoing';
            } else if (type === 'overtime') {
                mockExam.start_time = now - 4000;
                mockExam.actual_start = now - 4000;
                mockExam.end_time = now - 400; // Ended 400s ago
                mockExam.status = 'ongoing';
            }
            
            updateStageClocks();
        }

        function simulateEndWipe() {
            triggerWipe('end', "EXAM CONCLUDED. GOOD LUCK!");
            setTimeout(() => {
                simulateState('idle');
            }, 1000);
        }

        // 🚀 WIPE ANIMATION ENGINE (CHIME + GIF)
        function triggerWipe(actionType, overrideText) {
            if(isWiping) return;
            isWiping = true;

            const wipe = document.getElementById('wipe-screen');
            const gif = document.getElementById('wipe-gif');
            const textEl = document.getElementById('wipe-text');

            if (actionType === 'start') {
                const cheers = ["Goodluck!", "Galingan nyo goiz!", "1..2..3... gew!", "Agnas!!! 🔥", "Lezzgaur!", "Yizz! Galing!"];
                textEl.innerText = cheers[Math.floor(Math.random() * cheers.length)];
            } else if (overrideText) {
                textEl.innerText = overrideText;
            } else {
                textEl.innerText = "PREPARING ROOM...";
            }

            // 🖼️ Load random GIF
            if (actionType === 'start' || actionType === 'end') {
                const randGif = Math.floor(Math.random() * 5) + 1;
                gif.src = `../uploads/gif/${randGif}.gif`;
                gif.style.display = 'block';
                
                // 🔊 Play Chime
                const chime = document.getElementById('chime-audio');
                chime.currentTime = 0;
                chime.play().catch(e => console.log("Audio blocked by browser. Click anywhere first.", e));
            } else {
                gif.style.display = 'none';
            }

            wipe.classList.remove('slide-out');
            wipe.classList.add('slide-in');
            
            setTimeout(() => {
                wipe.classList.remove('slide-in');
                wipe.classList.add('slide-out');
                isWiping = false;
            }, 3500); 
        }

        // ⏱️ GLOBAL CLOCK TICKER
        setInterval(updateStageClocks, 1000);

        function updateStageClocks() {
            const now = new Date();
            let h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
            let ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12; 
            
            const timeStr = `${h < 10 ? '0'+h : h}:${m < 10 ? '0'+m : m}:${s < 10 ? '0'+s : s} ${ampm}`;
            document.getElementById('stg-clock').innerText = timeStr;
            document.getElementById('idle-clock-main').innerText = `${h < 10 ? '0'+h : h}:${m < 10 ? '0'+m : m}`;
            
            const options = { weekday: 'short', month: 'short', day: '2-digit' };
            document.getElementById('stg-date').innerText = now.toLocaleDateString('en-US', options).toUpperCase();

            // EXAM TIMER LOGIC
            if (mockExam && document.getElementById('exam-stage').style.display === 'flex') {
                const nowUnix = Math.floor(now.getTime() / 1000);
                const timerBox = document.getElementById('stg-timer-box');
                const timerLbl = document.getElementById('stg-timer-label');
                const timerDisp = document.getElementById('stg-timer');
                const reminderDisp = document.getElementById('pre-exam-reminder');

                if (mockExam.status === 'pending') {
                    let diff = mockExam.start_time - nowUnix;
                    
                    // 🚀 AUTO-START TRIGGER
                    if (diff <= 0 && diff > -10 && !window.autoStartedIds.has(mockExam.id)) {
                        window.autoStartedIds.add(mockExam.id);
                        mockExam.status = 'ongoing';
                        mockExam.actual_start = nowUnix;
                        mockExam.end_time = nowUnix + 3600; // 1hr test
                        triggerWipe('start'); 
                        return;
                    }

                    let isNeg = diff < 0;
                    let absDiff = Math.abs(diff);

                    timerBox.className = isNeg ? 'timer-container timer-danger-box' : 'timer-container';
                    timerLbl.className = isNeg ? 'timer-label label-danger' : 'timer-label';
                    timerLbl.innerText = isNeg ? 'LATE START' : 'STARTS IN';
                    
                    // 📢 10-Min / 3-Min Reminder Logic
                    if (!isNeg && diff <= 600 && diff > 0) { 
                        reminderDisp.style.display = 'block';
                        if (diff > 180) { // More than 3 mins remaining
                            reminderDisp.className = 'reminder-large';
                            timerDisp.className = 'timer-display timer-small';
                        } else { // Less than 3 mins remaining
                            reminderDisp.className = 'reminder-small';
                            timerDisp.className = 'timer-display timer-large';
                        }
                    } else {
                        reminderDisp.style.display = 'none';
                        timerDisp.className = isNeg ? 'timer-display time-danger' : 'timer-display timer-large';
                    }

                    let th = Math.floor(absDiff / 3600);
                    let tm = Math.floor((absDiff % 3600) / 60);
                    let ts = absDiff % 60;
                    if(th > 99) th = 99; // Cap

                    let tStr = `${th > 0 ? th+':' : ''}${tm < 10 ? '0'+tm : tm}:${ts < 10 ? '0'+ts : ts}`;
                    timerDisp.innerText = isNeg ? `-${tStr}` : tStr;

                } else if (mockExam.status === 'ongoing') {
                    reminderDisp.style.display = 'none'; 
                    
                    const duration = mockExam.end_time - mockExam.start_time;
                    const expected_end = mockExam.actual_start + duration;
                    let diff = expected_end - nowUnix;
                    let isNeg = diff < 0;
                    let absDiff = Math.abs(diff);

                    timerBox.className = isNeg ? 'timer-container timer-danger-box' : 'timer-container';
                    timerLbl.className = isNeg ? 'timer-label label-danger' : 'timer-label';
                    timerLbl.innerText = isNeg ? 'OVERTIME' : 'TIME REMAINING';
                    timerDisp.className = isNeg ? 'timer-display timer-large time-danger' : 'timer-display timer-large';
                    
                    let th = Math.floor(absDiff / 3600);
                    let tm = Math.floor((absDiff % 3600) / 60);
                    let ts = absDiff % 60;
                    if(th > 99) th = 99; // Cap

                    let tStr = `${th > 0 ? th+':' : ''}${tm < 10 ? '0'+tm : tm}:${ts < 10 ? '0'+ts : ts}`;
                    timerDisp.innerText = isNeg ? `-${tStr}` : tStr;
                }
            }
        }

        // Auto-Fullscreen to ensure Chime works natively (browsers require click to play audio)
        document.addEventListener('click', function(e) {
            if (!document.fullscreenElement && !e.target.closest('.test-btn')) {
                document.documentElement.requestFullscreen().catch(err => {});
            }
        });

    </script>
</body>
</html>