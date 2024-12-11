<?php
require_once '../confix.php';
define('DB_HOST', $DBHOST);
define('DB_NAME', $DBNAME); 
define('DB_USER', $DBUSER);
define('DB_PASS', $DBPASS);
define('PREFIX', '');

try {
    // Create PDO connection
    $db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $nickname = filter_input(INPUT_GET, 'nickname', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $session = filter_input(INPUT_GET, 'session', FILTER_SANITIZE_SPECIAL_CHARS) ?? '';
    $is_admin = false;



    // Get challenges data
    $stmt = $db->query("SELECT c.id, c.title, c.points, c.solved_by, cat.name as category
                       FROM challenges c
                       JOIN categories cat ON c.category_id = cat.id 
                       ORDER BY cat.name, c.points DESC");
    $results = $stmt->fetchAll(PDO::FETCH_ASSOC);



    // Get leaderboard data
    $stmt = $db->query("SELECT nickname, solved 
                       FROM leaderboard 
                       ORDER BY points DESC");
    $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);


} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CTF Platform</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            background: #0a0a0a;
            color: #fff;
            font-family: 'Segoe UI', system-ui, sans-serif;
            line-height: 1.5;
            
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 2rem;
            align-items: center;
        }

        .challenges-section {
            margin-bottom: 3rem;
            text-align: center;
        }

        .category-header {
            color: #00ff88;
            font-size: 1.5rem;
            margin: 2rem 0 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 2px solid #00ff88;
        }

        .challenges-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
            justify-content: center;
        }

        .challenge-card {
            background: #111;
            border: 1px solid #333;
            border-radius: 8px;
            padding: 1.5rem;
            transition: transform 0.2s, box-shadow 0.2s;
        }


        .challenge-title {
            color: #00ff88;
            font-size: 1.2rem;
            text-align: center;
            margin-bottom: 0.5rem;
        }

        .challenge-points {
            color: #888;
            font-size: 0.9rem;
        }

        .leaderboard-section {
            background: #111;
            
            border-radius: 8px;
            padding: 2rem;
            margin-top: 3rem;
        }

        .leaderboard-title {
            color: #00ff88;
            font-size: 1.5rem;
            margin-bottom: 1.5rem;
            text-align: center;
        }

        .leaderboard-table {
            width: 100%;
            border-collapse: collapse;
        }

        .leaderboard-table th,
        .leaderboard-table td {
            padding: 1rem;
            text-align: left;
            border-bottom: 1px solid #333;
        }

        .leaderboard-table th {
            color: #00ff88;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="profile-info">
        <span class="names"><?php echo $nickname; ?></span>
        <span class="ctf-finish"><php? </span>
    </div>
    <div class="container">
        <div class="challenges-section">
            <?php foreach($challenges as $category => $category_challenges): ?>
                <h2 class="category-header"><?php echo htmlspecialchars($category); ?></h2>
                <div class="challenges-grid">
                    <?php foreach($category_challenges as $challenge): ?>
                        <div class="challenge-card">
                            <div class="challenge-title"><?php echo htmlspecialchars($challenge['title']); ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="leaderboard-section">
            <h2 class="leaderboard-title">Leaderboard</h2>
            <table class="leaderboard-table">
                <tr>
                    <th>Player</th>
                    <th>Challenges Solved</th>
                </tr>
                <?php foreach($leaderboard as $player): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($player['nickname']); ?></td>
                        <td><?php echo $player['solved']; ?></td>
                    </tr>
                <?php endforeach; ?>
            </table>
        </div>

    </div>
</body>
</html>