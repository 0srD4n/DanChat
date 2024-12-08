<?php
require_once '../confix.php';

// Define database connection constants
define('DBHOST', $DBHOST); // Database host
define('DBUSER', $DBUSER); // Database user
define('DBPASS', $DBPASS); // Database password
define('DBNAME', $DBNAME); // Database name
define('PREFIX', ''); // Table prefix, if any
global $U, $db;

// Check if table exists, create if not
$tableExists = $db->query("SHOW TABLES LIKE '" . PREFIX . "cyber_links'")->rowCount() > 0;

if (!$tableExists) {
    $db->exec('CREATE TABLE ' . PREFIX . 'cyber_links (
        id INT AUTO_INCREMENT PRIMARY KEY,
        url VARCHAR(255) NOT NULL,
        title VARCHAR(100) NOT NULL,
        section VARCHAR(50) NOT NULL,
        network VARCHAR(10) NOT NULL DEFAULT "clearnet",
        status TINYINT NOT NULL DEFAULT 1,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )');
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'add_link') {
            // Add new link
            $stmt = $db->prepare('INSERT INTO ' . PREFIX . 'cyber_links (url, title, section, network, status, description) VALUES (?, ?, ?, ?, ?, ?)');
            $stmt->execute([
                $_POST['url'],
                $_POST['title'], 
                $_POST['section'],
                $_POST['network'],
                $_POST['status'],
                $_POST['description']
            ]);
        } else if ($_POST['action'] === 'delete_link' && isset($_POST['id'])) {
            // Delete link
            $stmt = $db->prepare('DELETE FROM ' . PREFIX . 'cyber_links WHERE id = ?');
            $stmt->execute([$_POST['id']]);
        } else if ($_POST['action'] === 'edit_link' && isset($_POST['id'])) {
            // Update link
            $stmt = $db->prepare('UPDATE ' . PREFIX . 'cyber_links SET url = ?, title = ?, section = ?, network = ?, status = ?, description = ? WHERE id = ?');
            $stmt->execute([
                $_POST['url'],
                $_POST['title'],
                $_POST['section'], 
                $_POST['network'],
                $_POST['status'],
                $_POST['description'],
                $_POST['id']
            ]);
        }
    }
}

// Get links after any updates
$links = $db->query('SELECT * FROM ' . PREFIX . 'cyber_links ORDER BY created_at DESC')->fetchAll();

// Send proper headers
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
    <meta charset="UTF-8">
    <meta name="referrer" content="no-referrer">
    <meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=yes">
    <title>DanChat - Manage Links</title>
    <link rel="stylesheet" href="./css/control.css">
    <link rel="icon" href="./icon.svg" type="image/svg+xml">
</head>
<body class="show_manageLink">
    <div class="manage-links">
        <h2><?php echo _('Manage Links'); ?></h2>
        
        <!-- Add new link form -->
        <form method="post" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" class="add-link-form">
            <h3><?php echo _('Add New Link'); ?></h3>
            <input type="hidden" name="action" value="add_link">
            
            <div class="form-group">
                <label for="url"><?php echo _('URL'); ?> *</label>
                <input type="url" id="url" name="url" placeholder="https://example.com" required pattern="https?://.+">
                <small class="form-help"><?php echo _('Must start with http:// or https://'); ?></small>
            </div>
            
            <div class="form-group">
                <label for="title"><?php echo _('Title'); ?> *</label>
                <input type="text" id="title" name="title" maxlength="100" required>
                <small class="form-help"><?php echo _('Maximum 100 characters'); ?></small>
            </div>
            
            <div class="form-group">
                <label for="section"><?php echo _('Section'); ?> *</label>
                <input type="text" id="section" name="section" maxlength="50" required>
                <small class="form-help"><?php echo _('Maximum 50 characters'); ?></small>
            </div>
            
            <div class="form-group">
                <label for="network"><?php echo _('Network Type'); ?> *</label>
                <select id="network" name="network" required>
                    <option value=""><?php echo '-- ' . _('Select Network') . ' --'; ?></option>
                    <option value="clearnet"><?php echo _('Clearnet'); ?></option>
                    <option value="tor"><?php echo _('Tor'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="status"><?php echo _('Status'); ?> *</label>
                <select id="status" name="status" required>
                    <option value="1"><?php echo _('Active'); ?></option>
                    <option value="0"><?php echo _('Inactive'); ?></option>
                </select>
            </div>
            
            <div class="form-group">
                <label for="description"><?php echo _('Description'); ?> *</label>
                <textarea id="description" name="description" maxlength="500" required rows="4"></textarea>
                <small class="form-help"><?php echo _('Maximum 500 characters'); ?></small>
            </div>
            
            <p class="required-note">* <?php echo _('Required fields'); ?></p>
            <button type="submit" class="btn-submit"><?php echo _('Add Link'); ?></button>
        </form>

        <!-- Display existing links -->
        <div class="links-list">
            <h3><?php echo _('Existing Links'); ?></h3>
            
            <?php if (empty($links)): ?>
                <p class="no-links"><?php echo _('No links found'); ?></p>
            <?php else: ?>
                <?php foreach ($links as $link): ?>
                    <div class="link-item">
                        <h4><?php echo htmlspecialchars($link['title']); ?></h4>
                        <p><strong><?php echo _('URL:'); ?></strong> <a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo htmlspecialchars($link['url']); ?></a></p>
                        <p><strong><?php echo _('Section:'); ?></strong> <?php echo htmlspecialchars($link['section']); ?></p>
                        <p><strong><?php echo _('Network:'); ?></strong> <?php echo _(ucfirst($link['network'])); ?></p>
                        <p><strong><?php echo _('Status:'); ?></strong> <span class="status-<?php echo $link['status'] ? 'active' : 'inactive'; ?>"><?php echo $link['status'] ? _('Active') : _('Inactive'); ?></span></p>
                        <p><strong><?php echo _('Description:'); ?></strong> <?php echo htmlspecialchars($link['description']); ?></p>
                        
                        <div class="link-actions">
                            <form method="post" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" class="edit-form">
                                <input type="hidden" name="action" value="edit_link">
                                <input type="hidden" name="id" value="<?php echo $link['id']; ?>">
                                
                                <div class="form-group">
                                    <label for="edit_url_<?php echo $link['id']; ?>">URL</label>
                                    <input type="url" id="edit_url_<?php echo $link['id']; ?>" name="url" value="<?php echo htmlspecialchars($link['url']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_title_<?php echo $link['id']; ?>">Title</label>
                                    <input type="text" id="edit_title_<?php echo $link['id']; ?>" name="title" value="<?php echo htmlspecialchars($link['title']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_section_<?php echo $link['id']; ?>">Section</label>
                                    <input type="text" id="edit_section_<?php echo $link['id']; ?>" name="section" value="<?php echo htmlspecialchars($link['section']); ?>" required>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_network_<?php echo $link['id']; ?>">Network</label>
                                    <select id="edit_network_<?php echo $link['id']; ?>" name="network" required>
                                        <option value="clearnet" <?php echo $link['network'] === 'clearnet' ? 'selected' : ''; ?>>Clearnet</option>
                                        <option value="tor" <?php echo $link['network'] === 'tor' ? 'selected' : ''; ?>>Tor</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_status_<?php echo $link['id']; ?>">Status</label>
                                    <select id="edit_status_<?php echo $link['id']; ?>" name="status" required>
                                        <option value="1" <?php echo $link['status'] == 1 ? 'selected' : ''; ?>>Active</option>
                                        <option value="0" <?php echo $link['status'] == 0 ? 'selected' : ''; ?>>Inactive</option>
                                    </select>
                                </div>
                                
                                <div class="form-group">
                                    <label for="edit_description_<?php echo $link['id']; ?>">Description</label>
                                    <textarea id="edit_description_<?php echo $link['id']; ?>" name="description" required><?php echo htmlspecialchars($link['description']); ?></textarea>
                                </div>
                                
                                <button type="submit" class="btn-edit">Update</button>
                            </form>
                            
                            <form method="post" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" class="delete-form" onsubmit="return confirm('<?php echo _('Are you sure you want to delete this link?'); ?>')">
                                <input type="hidden" name="action" value="delete_link">
                                <input type="hidden" name="id" value="<?php echo $link['id']; ?>">
                                <button type="submit" class="btn-delete">Delete</button>
                            </form>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
<!-- ini  -->
route();

//

function add_bad_word(string $word): string {
	global $db;

	$word = trim($word);
	if (empty($word)) {
		return _('Word cannot be empty');
	}

	if (mb_strlen($word) > 255) {
		return _('Word is too long (maximum 255 characters)');
	}

	try {
		$stmt = $db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'bad_words WHERE word = ?');
		$stmt->execute([$word]);
		if ($stmt->fetchColumn() > 0) {
			return _('Word already exists in bad words list');
		}

		$db->exec('CREATE TABLE IF NOT EXISTS ' . PREFIX . 'bad_words (
			id INT AUTO_INCREMENT PRIMARY KEY,
			word VARCHAR(255) NOT NULL UNIQUE
		)');

		try {
			$stmt = $db->prepare('INSERT INTO ' . PREFIX . 'bad_words (word) VALUES (?)');
			$stmt->execute([$word]);

			if ($stmt->rowCount() > 0) {
				return '<span style="color:red; font-weight: bold; padding: 5px; border-radius: 3px; background: rgba(0,20,0,0.5);">' . _('Bad name added successfully') . '</span>';
			} else {
				return '<span style="color: red; font-weight: bold; padding: 5px; border-radius: 3px; background: rgba(0,20,0,0.5);">' . _('Failed to add bad name') . '</span>';
			}
		} catch (PDOException $e) {
			error_log("Failed to insert bad word: " . $e->getMessage());
			return _('Error adding bad word - please try again');
		}

	} catch (PDOException $e) {
		error_log("Database error while adding bad word: " . $e->getMessage());
		return _('Database error - please contact administrator');
	}
}