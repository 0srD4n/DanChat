<?php
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DANCHAT ACCESS POINT</title>
    <link rel="icon" type="image/x-icon" href="danchat.svg">
    <style>
        body {
            margin: 0;
            padding: 0;
            background-color: #000;
            color: #0f0;
            font-family: 'Courier New', monospace;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background-image: url('danchat.jpg');
            background-size: cover;
            background-position: center;
            overflow: hidden;
            font-size: 0.9em;
        }

        .container {
            backdrop-filter: blur(10px);
            text-align: center;
            background: rgba(0,20,20,0.8); 
            padding: 8px;
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0,255,0,0.3);
            border: 1px solid rgba(0,255,0,0.2);
            animation: glowPulse 2s infinite;
            max-width: 500px;
            width: 85%;
        }

        h1 {
            font-size: 0.9em;
            margin-bottom: 8px;
            text-shadow: 0 0 8px #0f0;
            animation: glitch 1s infinite;
        }

        .warning {
            color: #ff0000;
            margin: 8px 0;
            font-size: 0.8em;
            text-transform: uppercase;
        }

        .enter-btn {
            background: transparent;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 8px 16px;
            font-size: 0.8em;
            cursor: pointer;
            transition: all 0.3s;
            text-transform: uppercase;
            margin-top: 15px;
            font-family: 'Courier New', monospace;
        }

        .enter-btn:hover {
            background: rgba(0,255,0,0.1);
            box-shadow: 0 0 15px rgba(0,255,0,0.5);
            transform: scale(1.03);
        }

        @keyframes glowPulse {
            0% { box-shadow: 0 0 15px rgba(0,255,0,0.3); }
            50% { box-shadow: 0 0 25px rgba(0,255,0,0.5); }
            100% { box-shadow: 0 0 15px rgba(0,255,0,0.3); }
        }

        @keyframes matrixBg {
            0% { background-position: 0 0; }
            100% { background-position: 40px 40px; }
        }

        .disclaimer {
            font-size: 0.7em;
            color: #666;
            margin-top: 20px;
        }

        #loading {
            display: none;
            margin-top: 15px;
            color: #0f0;
            font-size: 0.8em;
        }

        .progress-bar {
            width: 100%;
            height: 15px;
            background: #111;
            border: 1px solid #0f0;
            margin-top: 15px;
            overflow: hidden;
        }

        .progress {
            width: 0%;
            height: 100%;
            background: #0f0;
            transition: width 3s linear;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>DanChat Access Point</h1>
        <div class="warning">⚠ WARNING: RESTRICTED ACCESS ⚠</div>
        <p>You are about to enter a secured network zone.</p>
        <p>All activities are monitored and logged.</p>
        <p>All store on the dark web all <strong style="color:red">scam</strong></p>
        <form action="<?php echo htmlspecialchars('/chat/index.php'); ?>" method="GET">
            <button type="submit" class="enter-btn">ENTER DANCHAT</button>
        </form>
        <div id="loading">
            <p>Establishing secure connection...</p>
            <div class="progress-bar">
                <div class="progress" id="progress"></div>
            </div>
        </div>
        <p class="disclaimer">By entering, you acknowledge all risks and responsibilities.</p>
        <div class="secure-icons">
            <img src="https://www.torproject.org/static/images/tor-logo.svg" alt="Tor Logo" style="height: 40px; margin: 10px;">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/8/8d/Padlock-green.svg/1024px-Padlock-green.svg.png" alt="Secure Lock" style="height: 40px; margin: 10px;">
        </div>
        <div class="secure-text" style="color: #0f0; font-size: 0.8em; margin-top: 10px;">
            Protected by Tor Network | Secure Connection
        </div>
        <div class="tor-link" style="margin-top: 15px;">
            <a href="<?php echo htmlspecialchars('http://7ezcvo2wrozkrakhitpnloz2m3l6uqa33st6lyyylpe7ptzdghpsc4yd.onion/chat/index.php'); ?>" style="color: #0f0; text-decoration: none; border: 1px solid #0f0; padding: 5px 10px; border-radius: 3px;">
                Access via Tor Network
            </a>
        </div>
        <div class="clearnet-link" style="margin-top: 15px;">
            <a href="<?php echo htmlspecialchars('http://danchat.run.place/chat/index.php'); ?>" style="color: #0f0; text-decoration: none; border: 1px solid #0f0; padding: 5px 10px; border-radius: 3px;">
                Access via Clearnet
            </a>
        </div>
    </div>
</body>
</html>
