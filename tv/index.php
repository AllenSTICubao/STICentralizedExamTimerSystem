<?php
// 🛠️ AUTO-SETUP REDIRECT
if (!file_exists('../db.php')) {
    header('Location: ../setup.php');
    exit;
}

// tv/index.php - Airport Style Split-Screen Board (LITE / PERFORMANCE EDITION kasi 2GB ram ang mga Lobby TV sa Cubao, pa-upgrade po fleece)
date_default_timezone_set('Asia/Manila');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>STI Exam Lobby Board</title>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;700;900&family=Oswald:wght@500;700&family=JetBrains+Mono:wght@700;900&display=swap" rel="stylesheet">
    <style>
        /* 🎨 HIGH CONTRAST PALETTE (Optimized for weak GPUs) */
        :root { --sti-yellow: #fDD000; --sti-blue: #003666; --white: #ffffff; --bg: #f4f7f9; --text-dark: #001f3f; --text-gray: #657786; --border: #e1e8ed; --red: #FF0B2F; --green: #00b35c;}
        
        body { font-family: 'Montserrat', sans-serif; margin: 0; padding: 0; color: var(--text-dark); background: var(--bg); overflow: hidden;}
        * { box-sizing: border-box; }
        
        /* TOP NAVIGATION */
        .top-nav { background: var(--white); border-bottom: 2px solid var(--border); display: flex; justify-content: space-between; align-items: center; padding: 2vh 4vw; box-shadow: 0 1vh 2vh rgba(0,0,0,0.05); position: relative; z-index: 10;}
        .brand-zone { display: flex; align-items: center; gap: 2vw;}
        .global-logo { height: 8vh; object-fit: contain; }
        .board-title { font-family: 'Oswald'; font-size: 4.5vh; color: var(--sti-blue); letter-spacing: 0.1vw; text-transform: uppercase; line-height: 1; display: flex; align-items: center;}
        .board-title span { color: var(--sti-yellow); background: var(--sti-blue); padding: 0.5vh 1vw; border-radius: 1vh; margin-right: 1vw;}
        
        .clock-zone { text-align: right; }
        .clock-time { font-family: 'JetBrains Mono', monospace; font-size: 5vh; font-weight: 900; color: var(--sti-blue); letter-spacing: -0.1vw; line-height: 1;}
        .clock-date { font-family: 'Montserrat'; font-size: 2vh; font-weight: 900; color: var(--text-gray); letter-spacing: 0.2vw; text-transform: uppercase; margin-top: 0.5vh;}

        /* SPLIT SCREEN LAYOUT */
        .board-container { display: flex; width: 100vw; height: 86vh; padding: 2vh 2vw; gap: 2vw; transition: 0.5s; }
        .col { display: flex; flex-direction: column; transition: width 0.5s ease; width: 50%; }
        .col-full { width: 100% !important; }
        .col-hidden { width: 0 !important; overflow: hidden; opacity: 0; padding: 0 !important; margin: 0 !important;}
        
        .col-header { font-family: 'Oswald'; font-size: 4vh; color: var(--white); text-transform: uppercase; letter-spacing: 0.2vw; padding: 1.5vh 3vw; border-radius: 1.5vh 1.5vh 0 0; display: flex; justify-content: space-between; align-items: center; z-index: 2;}
        .header-active { background: var(--sti-blue); border-bottom: 0.5vh solid var(--sti-yellow); }
        .header-next { background: var(--text-gray); border-bottom: 0.5vh solid var(--border); }

        /* 🔴 BREATHING LIVE DOT (Simplified to Opacity Only) */
        .live-dot {
            display: inline-block; width: 1.5vh; height: 1.5vh;
            background-color: #ff3333; border-radius: 50%;
            margin-right: 1vw;
            animation: pulse-dot 1.5s infinite alternate linear;
        }
        @keyframes pulse-dot {
            0% { opacity: 0.2; }
            100% { opacity: 1; }
        }

        /* LIST & SCROLLING */
        .list-container { flex-grow: 1; background: var(--white); border: 2px solid var(--border); border-top: none; border-radius: 0 0 1.5vh 1.5vh; overflow: hidden; position: relative;}
        .scroll-area { position: absolute; top: 0; left: 0; width: 100%; height: 100%; padding: 2vh; display: flex; flex-direction: column; gap: 1.5vh;}

        /* ✈️ AIRPORT ROW CARDS */
        .exam-row { display: grid; grid-template-columns: 2.5fr 4.5fr 3fr; background: var(--bg); border-radius: 1.5vh; padding: 2vh 2.5vh; align-items: center; gap: 2vw; border-left: 1vw solid transparent;}
        
        /* ROW STATES */
        .row-white { border-left-color: var(--text-gray); border: 1px solid var(--border); border-left-width: 1vw; }
        .row-white .timer-val, .row-white .timer-lbl { color: var(--sti-blue); }
        
        .row-yellow { border-left-color: var(--sti-yellow); background: #fffcf0; border: 1px solid var(--sti-yellow); border-left-width: 1vw; }
        .row-yellow .timer-val, .row-yellow .timer-lbl { color: #b39200; }

        .row-green { border-left-color: var(--green); background: #f0fbf5; border: 1px solid var(--green); border-left-width: 1vw; }
        .row-green .timer-val, .row-green .timer-lbl { color: #008a47; }
        
        .row-red { border-left-color: var(--red); background: #fff0f2; border: 1px solid var(--red); border-left-width: 1vw; }
        .row-red .timer-val, .row-red .timer-lbl { color: var(--red); }

        .row-blue { border-left-color: var(--sti-blue); background: var(--sti-blue); color: var(--white); }
        .row-blue .main-text, .row-blue .sub-text, .row-blue .timer-lbl { color: var(--white); }
        .row-blue .sub-text { opacity: 0.8; }
        .row-blue .timer-val { color: var(--sti-yellow); }

        /* ROW TYPOGRAPHY */
        .time-block { display: flex; flex-direction: column; overflow: hidden; justify-content: center;}
        .main-text { font-family: 'Oswald'; color: var(--sti-blue); line-height: 1.1; letter-spacing: 0.1vw; text-transform: uppercase; }
        .text-wrap { white-space: normal; word-wrap: break-word; }
        .text-clamp { display: -webkit-box; -webkit-line-clamp: 2; -webkit-box-orient: vertical; overflow: hidden; }
        .sub-text { font-family: 'Montserrat'; font-size: 1.8vh; font-weight: 900; color: var(--text-gray); letter-spacing: 0.2vw; text-transform: uppercase; margin-bottom: 0.2vh;}
        
        .timer-block { text-align: right; display: flex; flex-direction: column; align-items: flex-end; justify-content: center;}
        .timer-val { font-family: 'JetBrains Mono', monospace; font-size: 6vh; font-weight: 900; line-height: 1; letter-spacing: -0.2vw;}
        .timer-lbl { font-family: 'Oswald'; font-size: 2.2vh; font-weight: 700; letter-spacing: 0.2vw; margin-top: 0.5vh;}

        /* FULL STATES */
        .full-state { display: none; height: 86vh; width: 100vw; position: absolute; top: 14vh; left: 0; flex-direction: column; justify-content: center; align-items: center; text-align: center; z-index: 5;}
        #empty-state h2 { font-family: 'Oswald'; font-size: 8vh; color: var(--text-gray); margin: 0; letter-spacing: 0.2vw; opacity: 0.5;}
        #empty-state p { font-family: 'Montserrat'; font-size: 3vh; font-weight: 900; color: var(--text-gray); letter-spacing: 0.2vw; opacity: 0.5;}
        #congrats-state h2 { font-family: 'Oswald'; font-size: 10vh; color: var(--sti-blue); margin: 0; letter-spacing: 0.2vw; text-transform: uppercase;}
        #congrats-state p { font-family: 'Montserrat'; font-size: 3vh; font-weight: 900; color: var(--text-dark); margin: 1vh 0 0 0; letter-spacing: 0.2vw; text-transform: uppercase;}
        .party-emoji { font-size: 20vh; margin-bottom: 2vh;}
    </style>
</head>
<body onclick="enableAudio()">

    <!-- 🔊 AIRPORT CHIMES -->
    <audio id="chime-audio" src="../uploads/chime/chime.mp3" preload="auto"></audio>
    <audio id="chime-end-audio" src="../uploads/chime/end.mp3" preload="auto"></audio>

    <div class="top-nav">
        <div class="brand-zone">
            <img src="" class="global-logo" id="global-logo" onerror="this.style.display='none'">
            <div class="board-title" id="board-title"><span>LIVE</span> EXAM SCHEDULES</div>
        </div>
        <div class="clock-zone">
            <div class="clock-time" id="sys-time">00:00:00</div>
            <div class="clock-date" id="sys-date">LOADING...</div>
        </div>
    </div>

    <!-- MAIN BOARDS -->
    <div class="board-container" id="main-board">
        <div class="col" id="col-active">
            <div class="col-header header-active">
                <div><span class="live-dot"></span>ON-GOING & PREPARING</div>
                <span id="count-active" style="font-size:2.5vh; background:var(--sti-yellow); color:var(--sti-dark); padding: 0.5vh 2vw; border-radius: 5vh;">0</span>
            </div>
            <div class="list-container" id="viewport-active">
                <div class="scroll-area" id="list-active"></div>
            </div>
        </div>

        <div class="col" id="col-upcoming">
            <div class="col-header header-next">
                <div>⏸ UP NEXT</div>
                <span id="count-upcoming" style="font-size:2.5vh; background:var(--white); color:var(--text-gray); padding: 0.5vh 2vw; border-radius: 5vh;">0</span>
            </div>
            <div class="list-container" id="viewport-upcoming">
                <div class="scroll-area" id="list-upcoming"></div>
            </div>
        </div>
    </div>

    <div class="full-state" id="empty-state">
        <h2>STANDBY MODE</h2>
        <p>NO EXAMS IN THE DATABASE FOR TODAY.</p>
    </div>

    <div class="full-state" id="congrats-state">
        <div class="party-emoji">🎊</div>
        <h2>CONGRATULATIONS!</h2>
        <p>ALL EXAMS ARE COMPLETED TODAY.</p>
    </div>

    <script>
        let systemData = null;
        let lastHtmlActive = '';
        let lastHtmlUpcoming = '';
        let examStatuses = {};
        let isInitialized = false;
        let chimedEvents = new Set();
        let partyChimed = false;
        let audioEnabled = false;

        function enableAudio() { audioEnabled = true; }

        function playChime(type) {
            if (!audioEnabled) return;
            const audioId = type === 'end' ? 'chime-end-audio' : 'chime-audio';
            const chime = document.getElementById(audioId);
            if (chime) {
                chime.volume = 0.8; 
                chime.currentTime = 0;
                chime.play().catch(e => console.log("Chime blocked by browser."));
            }
        }
        
        let scrollState = { active: { y: 0, down: true, wait: 0 }, upcoming: { y: 0, down: true, wait: 0 } };

        // ⏱️ 1-SECOND NATIVE TICKER
        setInterval(function() {
            const now = new Date();
            let h = now.getHours(), m = now.getMinutes(), s = now.getSeconds();
            let ampm = h >= 12 ? 'PM' : 'AM';
            h = h % 12 || 12;
            document.getElementById('sys-time').innerText = `${h}:${m < 10 ? '0'+m : m}:${s < 10 ? '0'+s : s} ${ampm}`;
            
            const nowUnix = Math.floor(now.getTime() / 1000);
            
            document.querySelectorAll('.live-timer').forEach(el => {
                const target = parseInt(el.getAttribute('data-target'));
                let diff = target - nowUnix;
                let absDiff = Math.abs(diff);

                const th = Math.floor(absDiff / 3600);
                const tm = Math.floor((absDiff % 3600) / 60);
                const ts = absDiff % 60;
                let tStr = `${th > 0 ? th+':' : ''}${tm < 10 ? '0'+tm : tm}:${ts < 10 ? '0'+ts : ts}`;
                
                el.innerText = diff < 0 ? `-${tStr}` : tStr;
            });
        }, 1000);

        // 🔄 4-SECOND SERVER SYNC
        async function fetchRoutine() {
            try {
                const res = await fetch(`../api.php?action=get_all&t=${Date.now()}`);
                const data = await res.json();
                systemData = data; 
                
                if (data.settings && data.settings.logo) {
                    const img = document.getElementById('global-logo');
                    if(!img.src.includes(data.settings.logo)) {
                        img.src = '../' + data.settings.logo; 
                        img.style.display = 'block';
                    }
                }
                
                buildBoardState();
            } catch(e) {}
        }

        // 🧠 BOARD LOGIC & HTML GENERATOR
        function buildBoardState() {
            if (!systemData) return; 

            const now = new Date();
            const nowUnix = Math.floor(now.getTime() / 1000);
            const todayISO = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0') + '-' + String(now.getDate()).padStart(2, '0');
            
            let currentDayNumber = 0;
            let currentDayString = "ADVANCE PREVIEW";
            
            if (systemData.settings && systemData.settings.exam_days) {
                for (let i = 1; i <= 5; i++) {
                    if (systemData.settings.exam_days[i] === todayISO) { 
                        currentDayNumber = parseInt(i); 
                        currentDayString = "DAY " + i;
                        break; 
                    }
                }
            }

            let schedules = JSON.parse(JSON.stringify(systemData.schedules || []));

            schedules = schedules.filter(s => 
                s && s.start_time != null && String(s.start_time).trim() !== '' && s.start_time !== '0' &&
                s.subject != null && String(s.subject).trim() !== '' && s.room != null && String(s.room).trim() !== ''
            );
            
            let targetDay = currentDayNumber;
            let isAdvanceMode = false;
            
            if (currentDayNumber === 0) {
                isAdvanceMode = true;
                const futureExams = schedules.filter(s => s.status !== 'done');
                if(futureExams.length > 0) {
                    futureExams.sort((a,b) => a.start_time - b.start_time);
                    targetDay = parseInt(futureExams[0].day);
                    currentDayString = "DAY " + targetDay + " (UPCOMING)";
                }
            }

            document.getElementById('sys-date').innerText = currentDayString + " | " + now.toLocaleDateString('en-US', { weekday: 'short', month: 'short', day: '2-digit' }).toUpperCase();
            document.getElementById('board-title').innerHTML = isAdvanceMode ? `<span>PREVIEW</span> ADVANCE SCHEDULE` : `<span>LIVE</span> EXAM SCHEDULE`;

            let activeExams = [];
            let upcomingExams = [];
            let doneCount = 0;
            let totalToday = 0;
            let playStartChime = false;
            let playEndChime = false;

            // ⏰ TIME SHIFT LOGIC (+15 MINS BUFFER)
            schedules.forEach(s => {
                if (parseInt(s.day) !== targetDay) return;
                totalToday++;
                
                let announcedTime = parseInt(s.start_time) - 1800; 
                s.original_scheduled = announcedTime; 
                s.start_time = announcedTime + 900; 
                s.end_time = parseInt(s.end_time) - 1800 + 900; 

                if (isInitialized && !isAdvanceMode) {
                    let prev = examStatuses[s.id];
                    let curr = s.status;
                    let diffToStart = s.start_time - nowUnix; 

                    if (prev === 'ongoing' && curr === 'done') playEndChime = true;
                    if (prev === 'pending' && curr === 'ongoing') playStartChime = true;
                    
                    if (curr === 'pending') {
                        if (diffToStart <= 0) {
                            // Red Zone
                        } else if (diffToStart <= 900) { 
                            if (!chimedEvents.has('green_' + s.id)) {
                                chimedEvents.add('green_' + s.id);
                                playStartChime = true;
                            }
                        } else if (diffToStart <= 1800) { 
                            if (!chimedEvents.has('yellow_' + s.id)) {
                                chimedEvents.add('yellow_' + s.id);
                                playStartChime = true;
                            }
                        }
                    }
                }
                examStatuses[s.id] = s.status; 
                
                if (s.status === 'done') { doneCount++; return; }

                let diff = s.start_time - nowUnix;

                if (s.status === 'ongoing') {
                    activeExams.push(s);
                } else if (s.status === 'pending') {
                    if (diff <= 1800 && !isAdvanceMode) {
                        activeExams.push(s);
                    } else {
                        upcomingExams.push(s);
                    }
                }
            });

            isInitialized = true; 

            if (playStartChime) playChime('start');
            else if (playEndChime) playChime('end');

            if (totalToday > 0 && doneCount === totalToday && !isAdvanceMode) {
                document.getElementById('main-board').style.display = 'none';
                document.getElementById('empty-state').style.display = 'none';
                document.getElementById('congrats-state').style.display = 'flex';
                
                if (!partyChimed) {
                    partyChimed = true;
                    playChime('end'); 
                }
                return;
            } else {
                partyChimed = false;
                if (totalToday === 0 && !isAdvanceMode) {
                    document.getElementById('main-board').style.display = 'none';
                    document.getElementById('congrats-state').style.display = 'none';
                    document.getElementById('empty-state').style.display = 'flex';
                    return;
                } else {
                    document.getElementById('main-board').style.display = 'flex';
                    document.getElementById('congrats-state').style.display = 'none';
                    document.getElementById('empty-state').style.display = 'none';
                }
            }

            function groupExams(examsArr) {
                let grouped = {};
                examsArr.forEach(e => {
                    let key = e.id; 
                    grouped[key] = { ...e, rooms: [e.room], proctors: [e.proctor] };
                });
                return Object.values(grouped).sort((a,b) => {
                    const rank = { 'ongoing': 1, 'pending': 2, 'done': 3 };
                    if (rank[a.status] !== rank[b.status]) return rank[a.status] - rank[b.status];
                    return a.start_time - b.start_time;
                });
            }

            const activeGrouped = groupExams(activeExams);
            const upcomingGrouped = groupExams(upcomingExams);

            const colA = document.getElementById('col-active');
            const colU = document.getElementById('col-upcoming');
            
            if (activeGrouped.length > 0 && upcomingGrouped.length > 0) {
                colA.className = 'col'; colU.className = 'col';
            } else if (activeGrouped.length > 0) {
                colA.className = 'col col-full'; colU.className = 'col col-hidden';
            } else if (upcomingGrouped.length > 0) {
                colA.className = 'col col-hidden'; colU.className = 'col col-full';
            }

            document.getElementById('count-active').innerText = activeGrouped.length;
            document.getElementById('count-upcoming').innerText = upcomingGrouped.length;

            let htmlLeft = renderListHTML(activeGrouped, nowUnix, isAdvanceMode);
            let htmlRight = renderListHTML(upcomingGrouped, nowUnix, isAdvanceMode);

            if (htmlLeft !== lastHtmlActive) {
                document.getElementById('list-active').innerHTML = htmlLeft;
                lastHtmlActive = htmlLeft;
            }
            if (htmlRight !== lastHtmlUpcoming) {
                document.getElementById('list-upcoming').innerHTML = htmlRight;
                lastHtmlUpcoming = htmlRight;
            }
        }

        function renderListHTML(items, nowUnix, isAdvanceMode) {
            let html = '';
            items.forEach((g) => {
                let rowClass = 'row-white';
                let timeColHtml = '';
                
                const diff = g.start_time - nowUnix;
                let dObj = new Date(g.original_scheduled * 1000); 
                let timeStr = !isNaN(dObj) ? dObj.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'}) : '--:--';

                if (isAdvanceMode) {
                    rowClass = 'row-white';
                    timeColHtml = `<div class="timer-val">${timeStr}</div><div class="timer-lbl">DAY ${g.day}</div>`;
                } else if (g.status === 'ongoing') {
                    rowClass = 'row-blue';
                    const duration = g.end_time - g.start_time;
                    const expectedEnd = (g.actual_start || g.start_time) + duration;
                    timeColHtml = `<div class="timer-val live-timer" data-target="${expectedEnd}">--:--</div><div class="timer-lbl">TIME LEFT</div>`;
                } else {
                    if (diff <= 0) { 
                        rowClass = 'row-red';
                        timeColHtml = `<div class="timer-val live-timer" data-target="${g.start_time}">--:--</div><div class="timer-lbl">LATE START</div>`;
                    } else if (diff <= 900) { 
                        rowClass = 'row-green';
                        timeColHtml = `<div class="timer-val live-timer" data-target="${g.start_time}">--:--</div><div class="timer-lbl">PROCEED TO ROOM</div>`;
                    } else if (diff <= 1800) { 
                        rowClass = 'row-yellow';
                        timeColHtml = `<div class="timer-val live-timer" data-target="${g.start_time}">--:--</div><div class="timer-lbl">LOBBY STANDBY</div>`;
                    } else { 
                        rowClass = 'row-white';
                        timeColHtml = `<div class="timer-val">${timeStr}</div><div class="timer-lbl">SCHEDULED</div>`;
                    }
                }

                let roomsArray = g.rooms;
                let roomStr = roomsArray.join(', ');
                let roomHtml = `<div class="sub-text">ROOM</div><div class="main-text text-wrap" style="font-size: 4.5vh;">${roomStr}</div>`;

                let pNames = g.proctors.filter(p => p && p !== 'TBA' && p !== 'undefined' && p !== '').map(p => {
                    let parts = p.split(',');
                    return parts.length === 1 ? p.split(' ').pop().toUpperCase() : parts[0].toUpperCase();
                });
                let uniqueProctors = [...new Set(pNames)];
                let proctStr = uniqueProctors.length > 0 ? (uniqueProctors.length > 3 ? "" + uniqueProctors.slice(0,3).join(', ') + '...' : "" + uniqueProctors.join(', ')) : 'TBA';

                // 🧹 REMOVED INLINE ANIMATION DELAY & ROW-ENTER
                html += `
                <div class="exam-row ${rowClass}">
                    <div class="time-block">
                        ${roomHtml}
                    </div>
                    <div class="time-block">
                        <div class="main-text text-clamp" style="font-size: 3.5vh;">${g.subject}</div>
                        <div class="sub-text" style="font-size: 1.5vh;">PROCTOR: ${proctStr}</div>
                    </div>
                    <div class="timer-block">
                        ${timeColHtml}
                    </div>
                </div>`;
            });
            return html;
        }

        // 🚀 HARDWARE-ACCELERATED SCROLLING (requestAnimationFrame instead of setInterval)
        function startHardwareScroll() {
            handleAutoScroll('viewport-active', 'list-active', 'active');
            handleAutoScroll('viewport-upcoming', 'list-upcoming', 'upcoming');
            requestAnimationFrame(startHardwareScroll);
        }

        function handleAutoScroll(viewportId, contentId, stateKey) {
            const vp = document.getElementById(viewportId);
            const content = document.getElementById(contentId);
            if (!vp || !content) return;
            
            let s = scrollState[stateKey];
            if (content.scrollHeight > vp.clientHeight) {
                if (s.wait > 0) { s.wait--; return; }

                if (s.down) {
                    s.y += 0.3; // Slower increment because requestAnimationFrame runs at 60fps
                    if (s.y >= content.scrollHeight - vp.clientHeight + 50) {
                        s.down = false; s.wait = 100; 
                    }
                } else {
                    s.y -= 2.5;
                    if (s.y <= 0) {
                        s.y = 0; s.down = true; s.wait = 100; 
                    }
                }
                content.style.transform = `translateY(-${s.y}px)`;
            } else {
                s.y = 0;
                content.style.transform = `translateY(0)`;
            }
        }

        // Initiate App
        fetchRoutine();
        setInterval(fetchRoutine, 4000); 
        requestAnimationFrame(startHardwareScroll); // Start the buttery smooth scroll

    </script>
</body>
</html>