 <?php
$nickname = filter_input(INPUT_GET, 'nickname', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$status = filter_input(INPUT_GET, 'status', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Command Center</title>
    <link rel="icon" type="image/svg+xml" href="icon.svg">
    <style>
        body {
            background-color: #0a0a0a;
            color: #00ff00;
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
        }

        .container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            padding: 20px;
            border: 1px solid #00ff88;
            margin-bottom: 30px;
            text-transform: uppercase;
            letter-spacing: 2px;
        }

        .command-section {
            margin-bottom: 40px;
            padding: 20px;
            border: 1px solid #1a1a1a;
            background: rgba(0, 0, 0, 0.7);
        }

        .command-section h2 {
            color: #00ff00;
            border-bottom: 1px solid #00ff88;
            padding-bottom: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .command-item {
            padding: 15px;
            background: rgba(0, 20, 0, 0.3);
            border: 1px solid #00ff88;
            margin-bottom: 15px;
        }

        .command-name {
            color: #ff00ff;
            font-weight: bold;
            font-size: 1.1em;
            margin-bottom: 5px;
        }

        .command-syntax {
            color: #00ffff;
            font-family: monospace;
            background: rgba(0, 0, 0, 0.5);
            padding: 5px;
            margin: 5px 0;
        }

        .command-desc {
            color: #cccccc;
            font-size: 0.9em;
            margin-top: 5px;
        }

        .important {
            color: #ff0000;
            text-align: center;
            margin: 20px 0;
            font-weight: bold;
            text-shadow: 0 0 5px #ff0000;
        }

        .matrix-effect {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            pointer-events: none;
            z-index: -1;
            opacity: 0.1;
            background: linear-gradient(0deg, 
                rgba(0, 255, 0, 0.1) 25%, 
                rgba(0, 0, 0, 0.2) 50%,
                rgba(0, 255, 0, 0.1) 75%
            );
        }
    </style>
</head>
<body>
    <div class="matrix-effect"></div>
    <div class="container">
        <div class="header">
            <h1>[ CYBER COMMAND  ]</h1>
            <p class="important">WARNING: AUTHORIZED ACCESS ONLY</p>
            <p class="cyber-text">Logged in as: <span style="color:white"><?php echo htmlspecialchars($nickname); ?></span> | Status: <span style="color:#0f0"><?php 
                switch($status) {
                    case 1:
                        echo '<span style="color:white">Guest</span>';
                        break;
                    case 2:
                        echo '<span style="color:white">Applicant</span>';
                        break;
                    case 3:
                        echo '<span style="color:white">Member</span>';
                        break;
                    case 5:
                        echo '<span style="color:white">Moderator</span>';
                        break;
                    case 6:
                        echo '<span style="color:white">Super-Moderator</span>';
                        break;
                    case 7:
                        echo '<span style="color:white">Admin</span>';   
                        break;
                    case 8:
                        echo '<span style="color:white">Super-Admin</span>';
                        break;
                }
            ?></span></p>
        </div>

        <div class="command-section">
            <h2>SYSTEM COMMANDS</h2>
            <div class="command-item">
                <div class="command-name">/code</div>
                <div class="command-syntax">Syntax: /code [language] [code]</div>
                <div class="command-desc">Display multi-line code block and colorize it</div>
            </div>
        </div>
        <div class="command-section">
            <h2>COMMUNICATION COMMANDS</h2>
            <div class="command-item">
                <div class="command-name">/pm</div>
                <div class="command-syntax">Syntax: /pm [user] [message]</div>
                <div class="command-desc">Send private message to specified user</div>
            </div>
        </div>

        <div class="command-section">
            <h2>MEMBERS COMMANDS</h2>
            <div class="command-item">
                <div class="command-name">/kick</div>
                <div class="command-syntax">Syntax: /kick [user]</div>
                <div class="command-desc">Kick user from chat (Admin/Mod only)</div>
            </div>
        </div>
    </div>
</body>
</html>