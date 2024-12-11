<?php
// Enhanced security and configuration
require_once '../confix.php';
define('DBHOST', $DBHOST); 
define('DBUSER', $DBUSER);
define('DBPASS', $DBPASS);
define('DBNAME', $DBNAME);
define('PREFIX', '');
define('MAX_LINKS_PER_PAGE', 10);
define('CHECK_INTERVAL', 3600); // 1 hour in seconds

// Custom exception handler
class DatabaseException extends Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

//

// Database connection with retry mechanism
function connectDatabase($retries = 3) {
    while ($retries > 0) {
        try {
            $db = new PDO(
                'mysql:host=' . DBHOST . ';dbname=' . DBNAME,
                DBUSER,
                DBPASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
            return $db;
        } catch (PDOException $e) {
            $retries--;
            if ($retries === 0) {
                throw new DatabaseException('Database connection failed: ' . $e->getMessage());
            }
            sleep(1);
        }
    }
}

try {
    $db = connectDatabase();

    // Add last_checked column if not exists
    $db->exec('ALTER TABLE ' . PREFIX . 'cyber_links ADD COLUMN IF NOT EXISTS last_checked TIMESTAMP NULL');

    // Get links that need checking
    $stmt = $db->prepare('
        SELECT id, url, network 
        FROM ' . PREFIX . 'cyber_links 
        WHERE last_checked IS NULL 
        OR last_checked < DATE_SUB(NOW(), INTERVAL 1 HOUR)
        LIMIT 10
    ');
    $stmt->execute();
    $linksToCheck = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Check URLs and update status
    foreach ($linksToCheck as $link) {
        $stmt = $db->prepare('
            UPDATE ' . PREFIX . 'cyber_links 
            SET status = ?, last_checked = NOW() 
            WHERE id = ?
        ');
        $stmt->execute([$isActive ? 1 : 0, $link['id']]);
    }

    // Get links for display
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * MAX_LINKS_PER_PAGE;
    
    $stmt = $db->prepare('
        SELECT cl.*, COUNT(DISTINCT cl2.section) as total_sections,
        COUNT(DISTINCT cl2.network) as total_networks,
        cl.last_checked
        FROM ' . PREFIX . 'cyber_links cl
        LEFT JOIN ' . PREFIX . 'cyber_links cl2 ON cl2.status = 1
        GROUP BY cl.id
        ORDER BY cl.section, cl.network, cl.created_at DESC
        LIMIT :offset, :limit
    ');
    
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->bindValue(':limit', MAX_LINKS_PER_PAGE, PDO::PARAM_INT);
    $stmt->execute();
    $links = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Organize data
    $sections = [];
    $analytics = [
        'total_links' => 0,
        'active_links' => 0,
        'networks' => []
    ];

    foreach($links as $link) {
        $sections[$link['section']][$link['network']][] = $link;
        $analytics['total_links']++;
        if ($link['status'] == 1) $analytics['active_links']++;
        if (!isset($analytics['networks'][$link['network']])) {
            $analytics['networks'][$link['network']] = 0;
        }
        $analytics['networks'][$link['network']]++;
    }

} catch (DatabaseException $e) {
    error_log($e->getMessage());
    die('Database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Link Hub</title>
    <meta http-equiv="refresh" content="3600">
    <style>
        :root {
            --primary-color: #00ff00;
            --secondary-color: #00cc00;
            --background-dark: #0a0a0a;
            --text-light: #ffffff;
            --border-color: #1a1a1a;
            --hover-color: rgba(0, 255, 0, 0.1);
        }

        body {
            background-color: var(--background-dark);
            color: var(--text-light);
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            line-height: 1.6;
            position: relative;
            min-height: 100vh;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            position: relative;
            z-index: 1;
        }

        .header {
            text-align: center;
            padding: 20px;
            border: 1px solid var(--primary-color);
            margin-bottom: 30px;
            background: rgba(0, 20, 0, 0.2);
            backdrop-filter: blur(5px);
            border-radius: 8px;
            box-shadow: 0 0 15px rgba(0, 255, 0, 0.1);
        }

        .link-section {
            margin-bottom: 40px;
            padding: 20px;
            background: rgba(0, 0, 0, 0.7);
            border-radius: 8px;
        }

      

        .link-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
        }

        .link-item {
            padding: 15px;
            background: rgba(0, 20, 0, 0.3);
            border: 1px solid var(--primary-color);
            transition: all 0.3s ease;
            border-radius: 5px;
            position: relative;
            overflow: hidden;
        }

        .link-item::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 2px;
            background: linear-gradient(90deg, transparent, var(--primary-color), transparent);
        }

        .link-item a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
            display: block;
            margin-bottom: 10px;
            text-shadow: 0 0 5px var(--primary-color);
        }

        .link-item p {
            color: var(--secondary-color);
            font-size: 0.9em;
            margin: 5px 0;
        }

        .status-indicator {
            display: inline-block;
            width: 8px;
            height: 8px;
            border-radius: 50%;
            margin-right: 10px;
            position: relative;
        }

        .status-active {
            background: var(--primary-color);
        }

        .status-offline {
            background: #ff0000;
        }


        .analytics-panel {
            position: fixed;
            right: 20px;
            top: 50%;
            transform: translateY(-50%);
            background: rgba(0, 20, 0, 0.8);
            padding: 15px;
            border: 1px solid var(--primary-color);
            border-radius: 5px;
            font-size: 0.8em;
        }

        @media (max-width: 768px) {
            .analytics-panel {
                position: static;
                margin: 20px auto;
                transform: none;
                max-width: 300px;
            }
            
            .link-grid {
                grid-template-columns: 1fr;
            }
        }

        h2 {
            text-align: center;
            color: var(--text-light);
            text-transform: uppercase;
            letter-spacing: 2px;
            text-shadow: 0 0 10px var(--primary-color);
        }

        .important {
            text-align: center;
            color: var(--primary-color);
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .importants {
            color:red;
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Link Hub</h1>
            <p>System Status: <?php echo $analytics['active_links'] . '/' . $analytics['total_links']; ?> Links Active</p>
            <p style="color:red">If link is not working, contact administrator</p>
            <p>Click on the names to open it</p>
        </div>

        <?php if(empty($sections)): ?>
            <div class="no-content">
                <p>No sections available</p>
            </div>
        <?php else: ?>
            <?php foreach($sections as $section => $networks): ?>
            <div class="link-section">
                <h2><?php echo htmlspecialchars($section); ?></h2>
                <?php foreach($networks as $network => $links): ?>
                <div class="network-container">
                    <h3><?php echo htmlspecialchars(ucfirst($network)); ?></h3>
                    <div class="link-grid">
                        <?php foreach($links as $link): ?>
                        <div class="link-item">
                            <?php
                            $status_class = $link['status'] == 1 ? 'status-active' : 'status-offline';
                            $created_date = new DateTime($link['created_at']);
                            $last_checked = $link['last_checked'] ? new DateTime($link['last_checked']) : null;
                            ?>
                            <span class="status-indicator <?php echo $status_class; ?>"></span>
                            <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo htmlspecialchars($link['title']); ?>
                            </a>
                            <p><?php echo htmlspecialchars($link['description']); ?></p>
                            <p>Added: <?php echo $created_date->format('Y-m-d'); ?></p>
                            <?php if($last_checked): ?>
                            <p class="last-checked">Last checked: <?php echo $last_checked->format('Y-m-d H:i:s'); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>