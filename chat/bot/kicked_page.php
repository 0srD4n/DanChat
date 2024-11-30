<?php
// Get the nickname and kickmessage from the URL parameters
$nickname = filter_input(INPUT_GET, 'nickname', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
$kickmessage = isset($_GET['kickmessage']) ? htmlspecialchars($_GET['kickmessage']) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>xpldan_kicked</title>
    <style>
        body {
            background-color: black;
            color: #0f0;
            font-family: 'Courier New', monospace;
            text-align: center;
            margin: 0;
            padding: 20px;
            overflow: hidden;
        }

        h1 {
            font-size: 48px;
            text-shadow: 0 0 10px #0f0;
            animation: glitch 1s infinite;
            margin-bottom: 30px;
        }

        p {
            font-size: 20px;
            line-height: 1.6;
            margin: 15px 0;
        }

        .warning {
            color: #ff0000;
            font-weight: bold;
            font-size: 24px;
            text-shadow: 0 0 10px #ff0000;
            margin: 30px 0;
        }

        .back-button {
            display: inline-block;
            margin-top: 30px;
            padding: 12px 25px;
            background: #0f0;
            color: #000;
            text-decoration: none;
            border-radius: 5px;
            font-weight: bold;
            transition: all 0.3s;
        }

        .back-button:hover {
            background: #000;
            color: #0f0;
            box-shadow: 0 0 15px #0f0;
        }
    </style>
</head>
<body>
    <h1>ACCESS DENIED</h1>
    <p>Account <span style="color:white;"><?php echo $nickname; ?></span> has been terminated from the system.</p>
    <p class="warning">⚠ VIOLATION DETECTED ⚠</p>
    <p>IP Address: <?php echo $_SERVER['REMOTE_ADDR']; ?></p>
    <p>Message: <span style="color:red;"><?php echo $kickmessage; ?></span></p>
    <p>Timestamp: <?php echo date('Y-m-d H:i:s'); ?></p>
    <a href="../index.php" class="back-button">Return to Login</a>
</body>
</html>
