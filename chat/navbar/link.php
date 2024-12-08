<?php
// Enhanced security and configuration
require_once '../confix.php';
define('DBHOST', $DBHOST); 
define('DBUSER', $DBUSER);
define('DBPASS', $DBPASS);
define('DBNAME', $DBNAME);
define('PREFIX', '');
define('MAX_LINKS_PER_PAGE', 10);

// Custom exception handler
class DatabaseException extends Exception {
    public function __construct($message, $code = 0, Exception $previous = null) {
        parent::__construct($message, $code, $previous);
    }
}

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
                throw new DatabaseException('Database connection failed after multiple attempts: ' . $e->getMessage());
            }
            sleep(1);
        }
    }
}

try {
    $db = connectDatabase();

    // Get links in realtime without caching
    $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
    $offset = ($page - 1) * MAX_LINKS_PER_PAGE;
    
    $stmt = $db->prepare('
        SELECT cl.*, 
               COUNT(DISTINCT cl2.section) as total_sections,
               COUNT(DISTINCT cl2.network) as total_networks
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

    // Enhanced data organization with analytics
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
    die('Critical database error occurred. Please try again later.');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Cyber Link Hub - Your Gateway to Curated Cyber Resources">
    <meta name="keywords" content="cyber, links, security, resources">
    <title>Cyber Links | Advanced Hub</title>
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
            border: 1px solid var(--border-color);
            background: rgba(0, 0, 0, 0.7);
            border-radius: 8px;
            transition: transform 0.3s ease;
        }

        .link-section:hover {
            transform: translateY(-5px);
        }

        .link-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            animation: fadeIn 0.5s ease-in-out;
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
            animation: scanline 2s linear infinite;
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
            box-shadow: 0 0 10px var(--primary-color);
            animation: pulse 2s infinite;
        }

        .status-offline {
            background: #ff0000;
            box-shadow: 0 0 10px #ff0000;
        }

        @keyframes pulse {
            0% { transform: scale(1); opacity: 1; }
            50% { transform: scale(1.2); opacity: 0.7; }
            100% { transform: scale(1); opacity: 1; }
        }

        @keyframes scanline {
            0% { transform: translateX(-100%); }
            100% { transform: translateX(100%); }
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(20px); }
            to { opacity: 1; transform: translateY(0); }
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
            animation: blink 1s infinite;
        }
        .importants {
            color:red;
        }

    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>Link HUB</h1>
            <p class="important">System Status: <?php echo $analytics['active_links'] . '/' . $analytics['total_links']; ?> Links Active</p>
            <p class="importants">If the link is not working, please contact the administrator immediately</p>
        </div>

        <?php if(empty($sections)): ?>
            <div class="no-content">
                <p>No sections available at this time</p>
            </div>
        <?php else: ?>
            <?php foreach($sections as $section => $networks): ?>
            <div class="link-section" data-section="<?php echo htmlspecialchars($section); ?>">
                <h2><?php echo htmlspecialchars($section); ?></h2>
                <?php if(empty($networks)): ?>
                    <p class="no-networks">No networks available in this section</p>
                <?php else: ?>
                    <?php foreach($networks as $network => $links): ?>
                    <div class="network-container" data-network="<?php echo htmlspecialchars($network); ?>">
                        <h3><?php echo htmlspecialchars(ucfirst($network)); ?></h3>
                        <div class="link-grid">
                            <?php if(empty($links)): ?>
                                <p class="no-links">No links available for this network</p>
                            <?php else: ?>
                                <?php foreach($links as $link): ?>
                                <div class="link-item" data-status="<?php echo $link['status']; ?>">
                                    <?php
                                    $status_class = $link['status'] == 1 ? 'status-active' : 'status-offline';
                                    $status_text = $link['status'] == 1 ? 'Active' : 'Offline';
                                    $created_date = new DateTime($link['created_at']);
                                    ?>
                                    <div class="link-header">
                                        <span class="status-indicator <?php echo $status_class; ?>" 
                                              title="<?php echo $status_text; ?> since <?php echo $created_date->format('Y-m-d H:i:s'); ?>">
                                        </span>
                                        <a href="<?php echo htmlspecialchars($link['url']); ?>" 
                                           target="_blank" 
                                           rel="noopener noreferrer"
                                           class="link-title"
                                           data-network="<?php echo htmlspecialchars($link['network']); ?>">
                                            <?php echo htmlspecialchars($link['title']); ?>
                                        </a>
                                    </div>
                                    <div class="link-details">
                                        <p class="description"> Description: <?php echo htmlspecialchars($link['description']); ?></p>
                                        <div class="meta-info">
                                            <p>Added: <?php echo $created_date->format('Y-m-d'); ?></p>
                                            <p>Network: <?php echo htmlspecialchars(ucfirst($link['network'])); ?></p>
                                            <p>URL: <?php echo htmlspecialchars($link['url']); ?></p>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>