<?php
require_once '../confix.php';

define('DBHOST', $DBHOST); 
define('DBUSER', $DBUSER);
define('DBPASS', $DBPASS);
define('DBNAME', $DBNAME);
define('PREFIX', '');

// Check session and get user data
global $U;

// Only allow access for users with status >= 3


try {
    $db = new PDO("mysql:host=$DBHOST;dbname=$DBNAME;charset=utf8mb4", $DBUSER, $DBPASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create':
                if (isset($_POST['title'], $_POST['description'], $_POST['min_status'])) {
                    $stmt = $db->prepare('INSERT INTO ' . PREFIX . 'votes (title, description, created_by, min_status, created_at) VALUES (?, ?, ?, ?, NOW())');
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'], 
                        $U['nickname'],
                        (int)$_POST['min_status']
                    ]);
                }
                break;

            case 'vote':
                if (isset($_POST['vote_id'], $_POST['choice'])) {
                    // Check if user already voted
                    $stmt = $db->prepare('SELECT id FROM ' . PREFIX . 'vote_responses WHERE vote_id = ? AND voter = ?');
                    $stmt->execute([$_POST['vote_id'], $U['nickname']]);
                    if (!$stmt->fetch()) {
                        $stmt = $db->prepare('INSERT INTO ' . PREFIX . 'vote_responses (vote_id, voter, choice, voted_at) VALUES (?, ?, ?, NOW())');
                        $stmt->execute([
                            $_POST['vote_id'],
                            $U['nickname'],
                            $_POST['choice']
                        ]);
                    }
                }
                break;

            case 'delete':
                if (isset($_POST['vote_id']) && $U['status'] >= 5) {
                    $stmt = $db->prepare('DELETE FROM ' . PREFIX . 'votes WHERE id = ?');
                    $stmt->execute([$_POST['vote_id']]);
                    
                    $stmt = $db->prepare('DELETE FROM ' . PREFIX . 'vote_responses WHERE vote_id = ?');
                    $stmt->execute([$_POST['vote_id']]);
                }
                break;

            case 'edit':
                if (isset($_POST['vote_id'], $_POST['title'], $_POST['description'], $_POST['min_status']) && $U['status'] >= 5) {
                    $stmt = $db->prepare('UPDATE ' . PREFIX . 'votes SET title = ?, description = ?, min_status = ? WHERE id = ?');
                    $stmt->execute([
                        $_POST['title'],
                        $_POST['description'],
                        (int)$_POST['min_status'],
                        $_POST['vote_id']
                    ]);
                }
                break;
        }
        // Redirect after any action to prevent form resubmission
        header("Location: " . $_SERVER['PHP_SELF'] . "?session=" . $U['session'] . "&lang=" . $language);
        exit;
    }
}

// Get all votes
$stmt = $db->prepare('SELECT v.*, 
    COUNT(DISTINCT CASE WHEN vr.choice = 1 THEN vr.voter END) as yes_votes,
    COUNT(DISTINCT CASE WHEN vr.choice = 0 THEN vr.voter END) as no_votes,
    EXISTS(SELECT 1 FROM ' . PREFIX . 'vote_responses WHERE vote_id = v.id AND voter = ?) as has_voted
    FROM ' . PREFIX . 'votes v
    LEFT JOIN ' . PREFIX . 'vote_responses vr ON v.id = vr.vote_id
    WHERE v.active = TRUE
    GROUP BY v.id
    ORDER BY v.created_at DESC');
$stmt->execute([$U['nickname']]);
$votes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get edit vote ID from query string if present
$edit_id = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
?>
<!DOCTYPE html>
<html>
<head>
    <title>Voting System</title>
    <style>
        body {
            background: #000;
            color: #0f0;
            font-family: 'Share Tech Mono', monospace;
            margin: 20px;
        }
        .vote-container {
            border: 1px solid #0f0;
            padding: 15px;
            margin: 10px 0;
            background: rgba(0,20,0,0.2);
        }
        .vote-title {
            font-size: 1.2em;
            color: #0f0;
            margin-bottom: 10px;
        }
        .vote-description {
            margin-bottom: 15px;
        }
        .vote-stats {
            display: flex;
            gap: 20px;
            margin: 10px 0;
        }
        .vote-buttons {
            margin-top: 10px;
        }
        button, input[type="submit"] {
            background: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 5px 15px;
            cursor: pointer;
            margin-right: 10px;
        }
        button:hover, input[type="submit"]:hover {
            background: #0f0;
            color: #000;
        }
        .create-form {
            border: 1px solid #0f0;
            padding: 15px;
            margin: 20px 0;
        }
        input, textarea, select {
            background: #000;
            color: #0f0;
            border: 1px solid #0f0;
            padding: 5px;
            margin: 5px 0;
            width: 100%;
        }
    </style>
</head>
<body>

<?php if ($U['status'] >= 5): ?>
<div class="create-form">
    <h3>Create New Vote</h3>
    <form method="POST">
        <input type="hidden" name="action" value="create">
        <input type="text" name="title" placeholder="Vote Title" required>
        <textarea name="description" placeholder="Vote Description" required></textarea>
        <select name="min_status" required>
            <option value="1">Guest</option>
            <option value="2">Super Guest</option>
            <option value="3">Registered</option>
            <option value="4">Moderator</option>
            <option value="5">Admin</option>
        </select>
        <input type="submit" value="Create Vote">
    </form>
</div>
<?php endif; ?>

<div class="votes-list">
    <?php foreach ($votes as $vote): ?>
        <?php if ($U['status'] >= $vote['min_status']): ?>
            <div class="vote-container">
                <?php if ($edit_id == $vote['id'] && $U['status'] >= 5): ?>
                    <form method="POST">
                        <input type="hidden" name="action" value="edit">
                        <input type="hidden" name="vote_id" value="<?= $vote['id'] ?>">
                        <input type="text" name="title" value="<?= htmlspecialchars($vote['title']) ?>" required>
                        <textarea name="description" required><?= htmlspecialchars($vote['description']) ?></textarea>
                        <select name="min_status" required>
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <option value="<?= $i ?>" <?= $vote['min_status'] == $i ? 'selected' : '' ?>>
                                    <?= $i ?>
                                </option>
                            <?php endfor; ?>
                        </select>
                        <input type="submit" value="Save Changes">
                        <a href="?session=<?= $U['session'] ?>&lang=<?= $language ?>">Cancel</a>
                    </form>
                <?php else: ?>
                    <div class="vote-title"><?= htmlspecialchars($vote['title']) ?></div>
                    <div class="vote-description"><?= nl2br(htmlspecialchars($vote['description'])) ?></div>
                    <div class="vote-stats">
                        <span>Yes: <?= $vote['yes_votes'] ?></span>
                        <span>No: <?= $vote['no_votes'] ?></span>
                        <span>Created by: <?= htmlspecialchars($vote['created_by']) ?></span>
                        <span>Min Status: <?= $vote['min_status'] ?></span>
                    </div>
                
                    <?php if (!$vote['has_voted']): ?>
                        <div class="vote-buttons">
                            <form method="POST" style="display: inline">
                                <input type="hidden" name="action" value="vote">
                                <input type="hidden" name="vote_id" value="<?= $vote['id'] ?>">
                                <input type="hidden" name="choice" value="1">
                                <input type="submit" value="Vote Yes">
                            </form>
                            <form method="POST" style="display: inline">
                                <input type="hidden" name="action" value="vote">
                                <input type="hidden" name="vote_id" value="<?= $vote['id'] ?>">
                                <input type="hidden" name="choice" value="0">
                                <input type="submit" value="Vote No">
                            </form>
                        </div>
                    <?php else: ?>
                        <div>You have already voted</div>
                    <?php endif; ?>

                    <?php if ($U['status'] >= 5): ?>
                        <div class="admin-controls" style="margin-top: 10px">
                            <form method="POST" style="display: inline">
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="vote_id" value="<?= $vote['id'] ?>">
                                <input type="submit" value="Delete" onclick="return confirm('Are you sure?')">
                            </form>
                            
                            <form method="GET" style="display: inline">
                                <input type="hidden" name="session" value="<?= $U['session'] ?>">
                                <input type="hidden" name="lang" value="<?= $language ?>">
                                <input type="hidden" name="edit" value="<?= $vote['id'] ?>">
                                <input type="submit" value="Edit">
                            </form>
                        </div>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endforeach; ?>
</div>

</body>
</html>
