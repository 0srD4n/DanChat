<!-- <?php
$nickname = filter_input(INPUT_GET, 'nickname', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTF Arena</title>
    <link rel="icon" type="image/svg+xml" href="icon.svg">
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
            padding: 20px;
            line-height: 1.6;
            background-image: 
                radial-gradient(circle at 50% 50%, rgba(0,243,255,0.1) 0%, transparent 50%),
                radial-gradient(circle at 100% 0%, rgba(157,0,255,0.1) 0%, transparent 50%);
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
        }

        .header {
            text-align: center;
            padding: 30px;
            margin-bottom: 40px;
            border: 1px solid rgba(0,243,255,0.3);
            background: rgba(10,10,18,0.8);
            backdrop-filter: blur(10px);
            border-radius: 15px;
            box-shadow: 0 0 20px rgba(0,243,255,0.2);
        }

        .header h1 {
            font-size: 2.5em;
            margin: 0;
            background: linear-gradient(45deg, var(--neon-blue), var(--neon-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: 0 0 10px rgba(0,243,255,0.5);
        }

        .user-info {
            margin-top: 20px;
            font-size: 1.1em;
            color: #888;
        }

        .challenges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 25px;
            padding: 20px;
        }

        .challenge-card {
            background: rgba(10,10,18,0.9);
            border: 1px solid rgba(0,243,255,0.2);
            border-radius: 12px;
            padding: 20px;
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .challenge-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,243,255,0.3);
            border-color: var(--neon-blue);
        }

        .challenge-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }

        .challenge-title {
            font-size: 1.3em;
            color: var(--neon-blue);
        }

        .challenge-points {
            background: rgba(157,0,255,0.2);
            padding: 5px 10px;
            border-radius: 20px;
            color: var(--neon-purple);
            font-weight: bold;
        }

        .challenge-category {
            font-size: 0.9em;
            color: #888;
            margin-bottom: 10px;
        }

        .progress-section {
            margin-top: 40px;
            padding: 20px;
            background: rgba(10,10,18,0.8);
            border-radius: 12px;
            border: 1px solid rgba(157,0,255,0.2);
        }

        .progress-bar {
            width: 100%;
            height: 10px;
            background: rgba(255,255,255,0.1);
            border-radius: 5px;
            margin: 10px 0;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--neon-blue), var(--neon-purple));
            width: 35%;
            border-radius: 5px;
            transition: width 0.5s ease;
        }

        .stats {
            display: flex;
            justify-content: space-around;
            margin-top: 20px;
            text-align: center;
        }

        .stat-item {
            color: #888;
        }

        .stat-value {
            font-size: 1.5em;
            color: var(--neon-blue);
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>CTF ARENA</h1>
            <div class="user-info">
                Player: <span style="color: var(--neon-blue)"><?php echo htmlspecialchars($nickname); ?></span> | 
                Rank: <span style="color: var(--neon-purple)"><?php 
                    switch($status) {
                        case 1: echo 'Newbie'; break;
                        case 2: echo 'Explorer'; break;
                        case 3: echo 'Hacker'; break;
                        case 5: echo 'Elite'; break;
                        case 6: echo 'Master'; break;
                        case 7: echo 'Guru'; break;
                        case 8: echo 'Legend'; break;
                    }
                ?></span>
            </div>
        </div>

        <div class="challenges-grid">
            <div class="challenge-card">
                <div class="challenge-header">
                    <div class="challenge-title">Binary Exploitation</div>
                    <div class="challenge-points">500 pts</div>
                </div>
                <div class="challenge-category">Buffer Overflow</div>
                <p>Exploit the vulnerable binary to gain shell access and capture the flag.</p>
            </div>

            <div class="challenge-card">
                <div class="challenge-header">
                    <div class="challenge-title">Web Security</div>
                    <div class="challenge-points">300 pts</div>
                </div>
                <div class="challenge-category">SQL Injection</div>
                <p>Find and exploit SQL injection vulnerabilities to access admin panel.</p>
            </div>

            <div class="challenge-card">
                <div class="challenge-header">
                    <div class="challenge-title">Cryptography</div>
                    <div class="challenge-points">400 pts</div>
                </div>
                <div class="challenge-category">RSA Challenge</div>
                <p>Break the encryption and decrypt the secret message.</p>
            </div>
        </div>

        <div class="progress-section">
            <h2>Your Progress</h2>
            <div class="progress-bar">
                <div class="progress-fill"></div>
            </div>
            <div class="stats">
                <div class="stat-item">
                    <div class="stat-value">12</div>
                    Challenges Solved
                </div>
                <div class="stat-item">
                    <div class="stat-value">2,450</div>
                    Total Points
                </div>
                <div class="stat-item">
                    <div class="stat-value">#8</div>
                    Global Rank
                </div>
            </div>
        </div>
    </div>
</body>
</html> -->
echo "comming soon hallo world";