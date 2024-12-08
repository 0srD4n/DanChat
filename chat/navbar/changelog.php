<?php
session_start();
$session = $_GET['session'] ?? '';
$language = $_GET['lang'] ?? 'en';
?>
<!DOCTYPE html>
<html lang="<?php echo $language; ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Changelog</title>
    <link rel="icon" type="image/svg+xml" href="./icon.svg">
    <style>
        :root {
            --neon-blue: #00f3ff;
            --neon-purple: #9d00ff;
            --dark-bg: #0a0a12;
        }

        body {
            background-color: var(--dark-bg);
            color: #fff;
            font-family: 'Segoe UI', 'Roboto', sans-serif;
            margin: 0;
            line-height: 1.6;
      }

        .container {
            margin: 0 auto;
            background: rgba(10,10,18,0.9);
            border: 1px solid rgba(0,243,255,0.2);
            border-radius: 12px;
            padding: 30px;
            backdrop-filter: blur(10px);
        }

        h1 {
            text-align: center;
            font-size: 2.5em;
            margin: 0 0 40px 0;
        color:white;
            text-shadow: 0 0 10px rgba(0,243,255,0.5);
        }

        .version {
            background: rgba(10,10,18,0.8);
            border: 1px solid rgba(0,243,255,0.2);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            transition: all 0.3s ease;
        }

 

        .version-number {
            color: var(--neon-blue);
            font-size: 1.3em;
            font-weight: bold;
            padding: 5px 15px;
            border-radius: 20px;
            background: rgba(0,243,255,0.1);
            display: inline-block;
        }

        .date {
            color: #888;
            margin-left: 15px;
            font-size: 0.9em;
        }

        .changes {
            list-style-type: none;
            padding-left: 20px;
            margin-top: 15px;
        }

        .changes li {
            margin-bottom: 12px;
            display: flex;
            align-items: center;
        }

        .tag {
            padding: 4px 8px;
            border-radius: 20px;
            font-size: 0.8em;
            margin-right: 10px;
            font-weight: bold;
            min-width: 80px;
            text-align: center;
        }

        .feature { 
            background: rgba(157,0,255,0.2);
            color: var(--neon-purple);
        }
        
        .security { 
            background: rgba(255,0,0,0.2);
            color: #ff4d4d;
        }
        
        .enhancement { 
            background: rgba(0,243,255,0.2);
            color: var(--neon-blue);
        }

        .cyber-link {
            display: inline-block;
            color: white;
            text-decoration: none;
            font-size: 1.2em;
            padding: 10px 20px;
            border: 1px solid var(--neon-blue);
            border-radius: 25px;
            margin-top: 20px;
            transition: all 0.3s ease;
        }

    </style>
</head>
<body>
    <div class="container">
        <h1>System Changelog</h1>

        <div class="version">
            <span class="version-number">v2.1.0</span>
            <span class="date">2024-12-05</span>
            <ul class="changes">
                <li><span class="tag security">BUGFIX</span> Fixed bug with message deletion | fixed  inbox bug | fixed deletion blank</li>
                <li><span class="tag feature">NEW</span> add navbar menu</li>
                <li><span class="tag enhancement">ENHANCED</span> improved chatroom performance</li>
            </ul>
            <span class="version-number">v2.1.1</span>
            <span class="date">2024-12-06</span>
            <ul class="changes">
                <li><span class="tag feature">NEW</span> add control panel link manage status staf</li>
            </ul>
        </div>
        </p>
    </div>
</body>
</html>
