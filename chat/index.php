<?php
/*
* status codes
* 0 - Kicked/Banned
* 1 - Guest
* 2 - Applicant
* 3 - Member
* 4 - System message
* 5 - Moderator
* 6 - Super-Moderator
* 7 - Admin
* 8 - Super-Admin
* 9 - Private messages
*/

// Add this line
$englobal = false; // Default value for global English setting
// initialize and load variables/configuration
const LANGUAGES = [
	'ar' => ['name' => 'العربية', 'locale' => 'ar', 'dir' => 'rtl'],
	'bg' => ['name' => 'Български', 'locale' => 'bg_BG', 'dir' => 'ltr'],
	'cs' => ['name' => 'čeština', 'locale' => 'cs_CZ', 'dir' => 'ltr'],
	'de' => ['name' => 'Deutsch', 'locale' => 'de_DE', 'dir' => 'ltr'],
	'en' => ['name' => 'English', 'locale' => 'en_GB', 'dir' => 'ltr'],
	'es' => ['name' => 'Español', 'locale' => 'es_ES', 'dir' => 'ltr'],
	'fi' => ['name' => 'Suomi', 'locale' => 'fi_FI', 'dir' => 'ltr'],
	'fr' => ['name' => 'Français', 'locale' => 'fr_FR', 'dir' => 'ltr'],
	'hi' => ['name' => 'हिन्दी', 'locale' => 'hi', 'dir' => 'ltr'],
	'id' => ['name' => 'Bahasa Indonesia', 'locale' => 'id_ID', 'dir' => 'ltr'],
	'it' => ['name' => 'Italiano', 'locale' => 'it_IT', 'dir' => 'ltr'],
	'nl' => ['name' => 'Nederlands', 'locale' => 'nl_NL', 'dir' => 'ltr'],
	'pl' => ['name' => 'Polski', 'locale' => 'pl_PL', 'dir' => 'ltr'],
	'pt' => ['name' => 'Português', 'locale' => 'pt_PT', 'dir' => 'ltr'],
	'ru' => ['name' => 'Русский', 'locale' => 'ru_RU', 'dir' => 'ltr'],
	'tr' => ['name' => 'Türkçe', 'locale' => 'tr_TR', 'dir' => 'ltr'],
	'uk' => ['name' => 'Українська', 'locale' => 'uk_UA', 'dir' => 'ltr'],
	'zh-Hans' => ['name' => '简体中文', 'locale' => 'zh_CN', 'dir' => 'ltr'],
	'zh-Hant' => ['name' => '正體中文', 'locale' => 'zh_TW', 'dir' => 'ltr'],
];
load_config();
$U=[];// This user data
$db = null;// Database connection
$memcached = null;// Memcached connection
$language = LANG;// user selected language
$locale = LANGUAGES[LANG]['locale'];// user selected locale
$dir = LANGUAGES[LANG]['dir'];// user selected language direction
$scripts = []; //js enhancements
$styles = []; //css styles - prioritaskan external styles
$session = $_REQUEST['session'] ?? ''; //requested session
// set session variable to cookie if cookies are enabled
if(!isset($_REQUEST['session']) && isset($_COOKIE[COOKIENAME])){
	$session = $_COOKIE[COOKIENAME];
}
$session = preg_replace('/[^0-9a-zA-Z]/', '', $session);
load_lang();
check_db();
cron();
route();
function route(): void {
    global $U, $db;

    if (!isset($_REQUEST['action'])) {
        send_login();
    } elseif ($_REQUEST['action'] === 'view') {
        check_session();
        send_messages();
    } elseif ($_REQUEST['action'] === 'redirect' && !empty($_GET['url'])) {
        send_redirect($_GET['url']); 
    } elseif ($_REQUEST['action'] === 'wait') {
        parse_sessions();
        send_waiting_room();
    }elseif($_POST['action']==='view_vote'){
		send_votes();
	} 
	elseif ($_REQUEST['action'] === 'post') {
        check_session();
        if (isset($_POST['kick']) && isset($_POST['sendto']) && $_POST['sendto'] !== 's *') {
            if ($U['status'] >= 5 || ($U['status'] >= 3 && (get_setting('memkickalways') || (get_count_mods() == 0 && get_setting('memkick'))))) {
                if (isset($_POST['what']) && $_POST['what'] === 'purge') {
                    kick_chatter([$_POST['sendto']], $_POST['message'], true);
                } else {
                    kick_chatter([$_POST['sendto']], $_POST['message'], false);
                }
            }
        } elseif (isset($_POST['message']) && isset($_POST['sendto'])) {
            send_post(validate_input());
        }
        send_post();
		send_messages();
    } elseif ($_REQUEST['action'] === 'login') {
        check_login();
        show_fails();
        send_frameset();
    } elseif ($_REQUEST['action'] === 'controls') {
        check_session();
        send_controls();
    } elseif ($_REQUEST['action'] === 'greeting') {
        check_session();
        send_greeting();
    } elseif ($_REQUEST['action'] === 'delete') {
        check_session();
        if (!isset($_POST['what'])) {
            // No action
        } elseif ($_POST['what'] === 'all') {
            if (isset($_POST['confirm'])) {
                del_all_messages('', (int)($U['status'] == 1 ? $U['entry'] : 0));
            } else {
                send_del_confirm();
            }
        } elseif ($_POST['what'] === 'last') {
            del_last_message();
        } elseif ($_POST['what'] === 'hilite' && isset($_POST['message_id'])) {
            check_session();
            delete_message((int)$_POST['message_id']);
        }
		send_messages();
    } elseif ($_REQUEST['action'] === 'profile') {
        check_session();
        $arg = '';
        if (!isset($_POST['do'])) {
            // No action
        } elseif ($_POST['do'] === 'save') {
            $arg = save_profile();
        } elseif ($_POST['do'] === 'delete') {
            if (isset($_POST['confirm'])) {
                delete_account();
            } else {
                send_delete_account();
            }
        }
        send_profile($arg);
    } elseif ($_REQUEST['action'] === 'logout' && $_SERVER['REQUEST_METHOD'] === 'POST') {
        check_session();
        if ($U['status'] < 3 && get_setting('exitwait')) {
            $U['exiting'] = 1;
            $stmt = $db->prepare('UPDATE ' . PREFIX . 'sessions SET exiting=1 WHERE session=? LIMIT 1;');
            $stmt->execute([$U['session']]);
        } else {
            kill_session();
        }
        send_logout();
    } elseif ($_REQUEST['action'] === 'viewlinks') {
        check_session();
        show_manageLink();
    } elseif ($_REQUEST['action'] === 'colours') {
        check_session();
        send_colours();
    } elseif ($_REQUEST['action'] === 'notes') {
        check_session();
        if (!isset($_POST['do'])) {
            // No action
        } elseif ($_POST['do'] === 'admin' && $U['status'] > 6) {
            send_notes(0);
        } elseif ($_POST['do'] === 'staff' && $U['status'] >= 5) {
            send_notes(1);
        } elseif ($_POST['do'] === 'public' && $U['status'] >= 3) {
            send_notes(3);
        } elseif ($_POST['do'] === 'votes' && $U['status'] >= 3) {
            send_votes();
        }
        if ($U['status'] < 3 || (!get_setting('personalnotes') && !get_setting('publicnotes'))) {
            send_access_denied();
        }
        send_notes(2);
    } elseif ($_REQUEST['action'] === 'help') {
        check_session();
        send_help();
    } elseif ($_REQUEST['action'] === 'viewpublicnotes') {
        check_session();
        view_publicnotes();
    } elseif ($_REQUEST['action'] === 'inbox') {
        check_session();
        if (isset($_POST['do'])) {
            clean_inbox_selected();
        }
        send_inbox();
    } elseif ($_REQUEST['action'] === 'download') {
        send_download();
    } elseif ($_REQUEST['action'] === 'admin') {
        check_session();
        send_admin(route_admin());
    } elseif ($_REQUEST['action'] === 'setup') {
        route_setup();
    } elseif ($_REQUEST['action'] === 'sa_password_reset') {
        send_sa_password_reset();
    } elseif ($_REQUEST['action'] === 'send_toggle_afk') {
        check_session();
        send_toggle_afk();
    } elseif ($_REQUEST['action'] === 'add_link') {
        check_session();
        if ($U['status'] >= 5) { // Only allow admins to add links
            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $link_data = [
                    'url' => $_POST['url'] ?? '',
                    'title' => $_POST['title'] ?? '', 
                    'section' => $_POST['section'] ?? '',
                    'network' => $_POST['network'] ?? '',
                    'status' => $_POST['status'] ?? 1,
                    'description' => $_POST['description'] ?? ''
                ];
                $result = add_link($link_data);
                if (!is_string($result)) { // If no error message returned
                    header('Location: ' . $_SERVER['SCRIPT_NAME'] . '?action=viewlinks&added=1');
                    exit;
                }
                echo $result;
            } else {
                show_manageLink();
            }
        } else {
            send_access_denied();
        }
    } elseif ($_REQUEST['action'] === 'delete_link') {
        check_session();
        if ($U['status'] >= 5) { // Only allow admins to delete links
            if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
                $result = delete_link((int)$_POST['id']);
                echo $result;
            } else {
                echo '<span class="error-msg">' . _('Invalid request') . '</span>';
            }
        } else {
            send_access_denied(); 
        }
    } elseif ($_REQUEST['action'] === 'edit_link') {
        check_session();
        if ($U['status'] >= 5) { // Only allow admins to edit links
            if (isset($_REQUEST['id']) && is_numeric($_REQUEST['id'])) {
                if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                    $result = edit_link((int)$_REQUEST['id'], $_POST);
                    echo $result;
                } else {
                    show_edit_link_form((int)$_REQUEST['id']);
                }
            } else {
                echo '<span class="error-msg">' . _('Invalid link ID') . '</span>';
            }
        } else {
            send_access_denied();
        }
    } else {
        send_login();
    }
}

function delete_message(int $message_id): void {
    global $U, $db;
    
    // Check if the user has access to delete the message
    $stmt = $db->prepare('SELECT id, poster, postdate FROM ' . PREFIX . 'messages WHERE id = ?');
    $stmt->execute([$message_id]);
    $message = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Only can delete own message within 10 minutes or if admin status
    if ($message) {
        $canDelete = false;
        
        if ($U['status'] >= 5) {
            // Admin can delete all messages
            $canDelete = true;
        } else if ($message['poster'] === $U['nickname']) {
            // Sender can delete their message within 10 minutes
            $timeDiff = time() - $message['postdate']; 
            if ($timeDiff <= 600) { // 600 seconds = 10 minutes
                $canDelete = true;
            }
        }
        
        if ($canDelete) {
            // Delete message from database
            $stmt = $db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id = ?');
            $stmt->execute([$message_id]);
            
            // Delete from inbox if exists
            $stmt = $db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE postid = ?');
            $stmt->execute([$message_id]);
            
            // Hapus bagian logging karena tabel tidak ada
        }
    }
    
    // Refresh messages
    send_messages();
}
function route_admin(): string {
    global $U, $db;
    if ($U['status'] < 5) {
        send_access_denied();
    }

    if (!isset($_POST['do'])) {
        return '';
    } elseif ($_POST['do'] === 'clean') {
        if ($_POST['what'] === 'choose') {
            send_choose_messages();
        } elseif ($_POST['what'] === 'selected') {
            clean_selected((int)$U['status'], $U['nickname']); 
        } elseif ($_POST['what'] === 'room') {
            clean_room();
        } elseif ($_POST['what'] === 'nick') {
            $stmt = $db->prepare('SELECT null FROM ' . PREFIX . 'members WHERE nickname=? AND status>=?;');
            $stmt->execute([$_POST['nickname'], $U['status']]);
            if (!$stmt->fetch(PDO::FETCH_ASSOC)) {
                del_all_messages($_POST['nickname'], 0);
            }
        }
    } elseif ($_POST['do'] === 'kick') {
        if (isset($_POST['name'])) {
            if (isset($_POST['what']) && $_POST['what'] === 'purge') {
                kick_chatter($_POST['name'], $_POST['kickmessage'], true);
            } else {
                kick_chatter($_POST['name'], $_POST['kickmessage'], false);
            }
        }
    } elseif ($_POST['do'] === 'logout') {
        if (isset($_POST['name'])) {
            logout_chatter($_POST['name']);
        }
    } elseif ($_POST['do'] === 'sessions') {
        if (isset($_POST['kick']) && isset($_POST['nick'])) {
            kick_chatter([$_POST['nick']], '', false);
        } elseif (isset($_POST['logout']) && isset($_POST['nick'])) {
            logout_chatter([$_POST['nick']]);
        }
        send_sessions();
    } elseif ($_POST['do'] === 'register') {
        return register_guest(3, $_POST['name']);
    } elseif ($_POST['do'] === 'superguest') {
        return register_guest(2, $_POST['name']);
    } elseif ($_POST['do'] === 'status') {
        return change_status($_POST['name'], $_POST['set']);
    } elseif ($_POST['do'] === 'regnew') {
        return register_new($_POST['name'], $_POST['pass']);
    } elseif ($_POST['do'] === 'approve') {
        approve_session();
        send_approve_waiting();
    } elseif ($_POST['do'] === 'guestaccess') {
        if (isset($_POST['guestaccess']) && preg_match('/^[0123]$/', $_POST['guestaccess'])) {
            update_setting('guestaccess', $_POST['guestaccess']);
            change_guest_access(intval($_POST['guestaccess']));
        }
    } elseif ($_POST['do'] === 'filter') {
        send_filter(manage_filter());
    } elseif ($_POST['do'] === 'linkfilter') {
        send_linkfilter(manage_linkfilter());
    } elseif ($_POST['do'] === 'topic') {
        if (isset($_POST['topic'])) {
            update_setting('topic', htmlspecialchars($_POST['topic']));
        }
    } elseif ($_POST['do'] === 'passreset') {
        return passreset($_POST['name'], $_POST['pass']);
    } elseif ($_POST['do'] === 'add_word' && isset($_POST['new_word'])) {
        return add_bad_word($_POST['new_word']);
    } elseif ($_POST['do'] === 'delete_word' && isset($_POST['word_id'])) {
        return delete_bad_word((int)$_POST['word_id']);
    } 
    return '';
}
function add_link(array $data): string {
    global $db;
    
    // Validate required fields
    $required = ['url', 'title', 'section', 'description', 'network'];
    foreach ($required as $field) {
        if (empty($data[$field])) {
            return _("$field is required");
        }
    }

    // Validate URL
    if (!filter_var($data['url'], FILTER_VALIDATE_URL)) {
        return _('Invalid URL format');
    }

    // Validate network type
    if (!in_array($data['network'], ['clearnet', 'tor'])) {
        return _('Invalid network type');
    }

    try {
        // First check if table exists
        $tableExists = $db->query("SHOW TABLES LIKE '" . PREFIX . "cyber_links'")->rowCount() > 0;
        
        // Create table if not exists with all required columns
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
        } else {
            // Check if network column exists
            $columnExists = $db->query("SHOW COLUMNS FROM " . PREFIX . "cyber_links LIKE 'network'")->rowCount() > 0;
            
            // Add network column if it doesn't exist
            if (!$columnExists) {
                $db->exec('ALTER TABLE ' . PREFIX . 'cyber_links ADD COLUMN network VARCHAR(10) NOT NULL DEFAULT "clearnet" AFTER section');
            }
        }

        $stmt = $db->prepare('INSERT INTO ' . PREFIX . 'cyber_links
            (url, title, section, network, status, description) 
            VALUES (?, ?, ?, ?, ?, ?)');
            
        $result = $stmt->execute([
            trim($data['url']),
            trim($data['title']), 
            strtoupper(trim($data['section'])), // Convert section to uppercase
            trim($data['network']),
            (int)$data['status'],
            trim($data['description'])
        ]);

        if ($result) {
            // Redirect after successful add
            header('Location: ' . $_SERVER['SCRIPT_NAME'] . '?action=viewlinks&added=1');
            exit;
        }

        error_log('Database error: ' . json_encode($stmt->errorInfo()));
        return '<span class="error-msg">' . _('Error adding link') . '</span>';

    } catch (PDOException $e) {
        error_log("Error adding link: " . $e->getMessage());
        return '<span class="error-msg">' . _('Database error: ') . htmlspecialchars($e->getMessage()) . '</span>';
    }
}
// function delete logic
function delete_link(int $link_id): string {
    global $db;
    
    if (empty($link_id)) {
        return _('Link ID is required');
    }

    try {
        $stmt = $db->prepare('DELETE FROM ' . PREFIX . 'cyber_links WHERE id = ?');
        $stmt->execute([$link_id]);

        if ($stmt->rowCount() > 0) {
            // Redirect after successful delete
            header('Location: ' . $_SERVER['SCRIPT_NAME'] . '?action=viewlinks&deleted=1');
            exit;
        }
        
        return '<span class="error-msg">' . _('Link not found') . '</span>';

    } catch (PDOException $e) {
        error_log("Error deleting link ID $link_id: " . $e->getMessage());
        return '<span class="error-msg">' . _('Error deleting link') . '</span>';
    }
}

// function edit link
function edit_link(int $id, array $data): string {
    global $db;
    
    if (empty($id)) {
        return _('Link ID is required');
    }

    $updateFields = [];
    $params = [];
    
    // Build dynamic update query
    foreach (['url', 'title', 'section', 'status', 'description', 'network'] as $field) {
        if (isset($data[$field])) {
            $updateFields[] = "$field = ?";
            $params[] = trim($data[$field]);
        }
    }
    
    if (empty($updateFields)) {
        return _('No fields to update');
    }

    try {
        $params[] = $id;
        $sql = 'UPDATE ' . PREFIX . 'cyber_links SET ' . implode(', ', $updateFields) . ' WHERE id = ?';
        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        if ($stmt->rowCount() > 0) {
            header('Location: ' . $_SERVER['SCRIPT_NAME'] . '?action=viewlinks&updated=1');
            exit;
        }
        return '<span class="error-msg">' . _('Link not found or no changes made') . '</span>';
    } catch (PDOException $e) {
        error_log("Error updating link ID $id: " . $e->getMessage());
        return _('Error updating link');
    }

    // Redirect after successful update
    header('Location: ' . $_SERVER['SCRIPT_NAME'] . '?action=viewlinks&updated=1');
    exit;
}
function show_manageLink(): void {
	global $U, $db;
send_headers();
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
	
	// Get links after any updates
	$links = $db->query('SELECT * FROM ' . PREFIX . 'cyber_links ORDER BY created_at DESC')->fetchAll();
	
	// Send proper headers
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
		<style>
			.edit-form { display: none; }
			.edit-form.active { display: block; }
			table { width: 100%; border-collapse: collapse; }
			th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
			th { background-color: #f4f4f4; }
			.btn-edit, .btn-delete { padding: 5px 10px; margin: 2px; }
			.status-active { color: green; }
			.status-inactive { color: red; }
		</style>
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
					<label for="section"><?php echo _('Section').' ('.strtoupper(_('Section')).')'; ?> *</label>
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
					<table>
						<thead>
							<tr>
								<th><?php echo _('Title'); ?></th>
								<th><?php echo _('URL'); ?></th>
								<th><?php echo _('Section'); ?></th>
								<th><?php echo _('Network'); ?></th>
								<th><?php echo _('Status'); ?></th>
								<th><?php echo _('Description'); ?></th>
								<th><?php echo _('Actions'); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php foreach ($links as $link): ?>
							<tr>
								<td><?php echo htmlspecialchars($link['title']); ?></td>
								<td><a href="<?php echo htmlspecialchars($link['url']); ?>" target="_blank"><?php echo htmlspecialchars($link['url']); ?></a></td>
								<td><?php echo htmlspecialchars($link['section']); ?></td>
								<td><?php echo _(ucfirst($link['network'])); ?></td>
								<td><span class="status-<?php echo $link['status'] ? 'active' : 'inactive'; ?>"><?php echo $link['status'] ? _('Active') : _('Inactive'); ?></span></td>
								<td><?php echo htmlspecialchars($link['description']); ?></td>
								<td>
									<button onclick="toggleEdit(<?php echo $link['id']; ?>)" class="btn-edit"><?php echo _('Edit'); ?></button>
									<form method="post" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" class="delete-form" style="display:inline" onsubmit="return confirm('<?php echo _('Are you sure?'); ?>')">
										<input type="hidden" name="action" value="delete_link">
										<input type="hidden" name="id" value="<?php echo $link['id']; ?>">
										<button type="submit" class="btn-delete"><?php echo _('Delete'); ?></button>
									</form>
								</td>
							</tr>
							<tr>
								<td colspan="7">
									<form method="post" action="<?php echo $_SERVER['SCRIPT_NAME']; ?>" class="edit-form" id="edit-form-<?php echo $link['id']; ?>">
										<input type="hidden" name="action" value="edit_link">
										<input type="hidden" name="id" value="<?php echo $link['id']; ?>">
										
										<div class="form-group">
											<label><?php echo _('URL'); ?>:</label>
											<input type="url" name="url" value="<?php echo htmlspecialchars($link['url']); ?>" required>
										</div>
										
										<div class="form-group">
											<label><?php echo _('Title'); ?>:</label>
											<input type="text" name="title" value="<?php echo htmlspecialchars($link['title']); ?>" required>
										</div>
										
										<div class="form-group">
											<label><?php echo _('Section'); ?>:</label>
											<input type="text" name="section" value="<?php echo htmlspecialchars($link['section']); ?>" required>
										</div>
										
										<div class="form-group">
											<label><?php echo _('Network'); ?>:</label>
											<select name="network" required>
												<option value="clearnet" <?php echo $link['network'] === 'clearnet' ? 'selected' : ''; ?>><?php echo _('Clearnet'); ?></option>
												<option value="tor" <?php echo $link['network'] === 'tor' ? 'selected' : ''; ?>><?php echo _('Tor'); ?></option>
											</select>
										</div>
										
										<div class="form-group">
											<label><?php echo _('Status'); ?>:</label>
											<select name="status" required>
												<option value="1" <?php echo $link['status'] == 1 ? 'selected' : ''; ?>><?php echo _('Active'); ?></option>
												<option value="0" <?php echo $link['status'] == 0 ? 'selected' : ''; ?>><?php echo _('Inactive'); ?></option>
											</select>
										</div>
										
										<div class="form-group">
											<label><?php echo _('Description'); ?>:</label>
											<textarea name="description" required><?php echo htmlspecialchars($link['description']); ?></textarea>
										</div>
										
										<button type="submit" class="btn-submit"><?php echo _('Update'); ?></button>
									</form>
								</td>
							</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<script>
		function toggleEdit(id) {
			const form = document.getElementById('edit-form-' + id);
			form.classList.toggle('active');
		}
		</script>
	</body>
	</html>
	<?php
}

function delete_bad_word(int $word_id): string {
	global $db;

	try {
		$stmt = $db->prepare('DELETE FROM ' . PREFIX . 'bad_words WHERE id = ?');
		$stmt->execute([$word_id]);

		if ($stmt->rowCount() > 0) {
			return '<span style="color:red; font-weight: bold; padding: 5px; border-radius: 3px; background: rgba(0,20,0,0.5);">' . _('Bad Name deleted successfully') . '</span>';
		} else {
			return '<span style="color: red; font-weight: bold; padding: 5px; border-radius: 3px; background: rgba(0,20,0,0.5);">' . _('Bad Name not found') . '</span>';
		}

	} catch (PDOException $e) {
		error_log("Error deleting bad name ID $word_id: " . $e->getMessage());
		return _('Error deleting bad name');
	}
}

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
function route_setup(): void
{
	global $U;
	if(!valid_admin()){
		send_alogin();
	}
	$C['bool_settings']=[
		'suguests' => _('Enable applicants'),
		'imgembed' => _('Embed images'),
		'timestamps' => _('Show Timestamps'),
		'trackip' => _('Show session-IP'),
		'memkick' => _('Members can kick, if no moderator is present'),
		'memkickalways' => _('Members can always kick'),
		'forceredirect' => _('Force redirection'),
		'incognito' => _('Incognito mode'),
		'sendmail' => _('Send mail on new public message'),
		'modfallback' => _('Fallback to waiting room, if no moderator is present to approve guests'),
		'disablepm' => _('Disable private messages'),
		'eninbox' => _('Enable offline inbox'),
		'enablegreeting' => _('Show a greeting message before showing the messages'),
		'sortupdown' => _('Sort messages from top to bottom'),
		'hidechatters' => _('Hide list of chatters'),
		'personalnotes' => _('Personal notes'),
		'publicnotes' => _('Public notes'),
		'filtermodkick' => _('Apply kick filter on moderators'),
		'namedoers' => _('Show who kicks people or purges all messages.'),
		'hide_reload_post_box' => _('Hide reload post box button'),
		'hide_reload_messages' => _('Hide reload messages button'),
		'hide_profile' => _('Hide profile button'),
		'hide_admin' => _('Hide admin button'),
		'hide_notes' => _('Hide notes button'),
		'hide_clone' => _('Hide clone button'),
		'hide_rearrange' => _('Hide rearrange button'),
		'hide_help' => _('Hide help button'),
		'postbox_delete_globally' => _('Apply postbox delete button globally'),
	];
	$C['colour_settings']=[
		'colbg' => _('Background colour'),
		'coltxt' => _('Font colour'),
	];
	$C['msg_settings']=[
		'msgenter' => _('Entrance'),
		'msgexit' => _('Leaving'),
		'msgmemreg' => _('Member registered'),
		'msgsureg' => _('Applicant registered'),
		'msgkick' => _('Kicked'),
		'msgmultikick' => _('Multiple kicked'),
		'msgallkick' => _('All kicked'),
		'msgclean' => _('Room cleaned'),
		'msgsendall' => _('Message to all'),
		'msgsendmem' => _('Message to members only'),
		'msgsendmod' => _('Message to staff only'),
		'msgsendadm' => _('Message to admins only'),
		'msgsendprv' => _('Private message'),
		'msgattache' => _('Attachement'),
	];
	$C['number_settings']=[
		'memberexpire' => _('Member timeout (minutes)'),
		'guestexpire' => _('Guest timeout (minutes)'),
		'kickpenalty' => _('Kick penalty (minutes)'),
		'entrywait' => _('Waiting room time (seconds)'),
		'exitwait' => _('Logout delay (seconds)'),
		'captchatime' => _('Captcha timeout (seconds)'),
		'messageexpire' => _('Message timeout (minutes)'),
		'messagelimit' => _('Message limit (public)'),
		'maxmessage' => _('Maximal message length'),
		'maxname' => _('Maximal nickname length'),
		'minpass' => _('Minimal password length'),
		'defaultrefresh' => _('Default message reload time (seconds)'),
		'numnotes' => _('Number of notes revisions to keep'),
		'maxuploadsize' => _('Maximum upload size in KB'),
		'enfileupload' => _('Enable file uploads'),
		'max_refresh_rate' => _('Lowest refresh rate'),
		'min_refresh_rate' => _('Highest refresh rate'),
	];
	$C['textarea_settings']=[
		'rulestxt' => _('Rules (html)'),
		'css' => _('CSS Style'),
		'disabletext' => _('Chat disabled message (html)'),
	];
	$C['text_settings']=[
		'dateformat' => _('<a target="_blank" href="https://php.net/manual/en/function.date.php#refsect1-function.date-parameters" rel="noopener noreferrer">Date formating</a>'),
		'captchachars' => _('Characters used in Captcha'),
		'redirect' => _('Custom redirection script'),
		'chatname' => _('Chat name'),
		'mailsender' => _('Send mail using this address'),
		'mailreceiver' => _('Send mail to this address'),
		'nickregex' => _('Nickname regex'),
		'passregex' => _('Password regex'),
		'externalcss' => _('Link to external CSS file (on your own server)'),
		'metadescription' => _('Meta description (best 50 - 160 characters for SEO)'),
		'exitingtxt' => _('Show this text when a user\'s logout is delayed'),
		'sysmessagetxt' => _('Prepend this text to system messages'),
	];
	$extra_settings=[
		'guestaccess' => _('Change Guestaccess'),
		'englobalpass' => _('Enable global Password'),
		'globalpass' => _('Global Password:'),
		'captcha' => _('Captcha'),
		'dismemcaptcha' => _('Only for guests'),
		'topic' => _('Topic'),
		'guestreg' => _('Let guests register themselves'),
		'defaulttz' => _('Default time zone'),
	];
	$C['settings']=array_keys(array_merge($extra_settings, $C['bool_settings'], $C['colour_settings'], $C['msg_settings'], $C['number_settings'], $C['textarea_settings'], $C['text_settings'])); // All settings in the database
	if(!isset($_POST['do'])){
	}elseif($_POST['do']==='save'){
		save_setup($C);
	}elseif($_POST['do']==='backup' && $U['status']==8){
		send_backup($C);
	}elseif($_POST['do']==='restore' && $U['status']==8){
		restore_backup($C);
		send_backup($C);
	}elseif($_POST['do']==='destroy' && $U['status']==8){
		if(isset($_POST['confirm'])){
			destroy_chat($C);
		}else{
			send_destroy_chat();
		}
	}
	send_setup($C);
}

//  html output subs
function prepare_stylesheets(string $class): void
{
	global $U, $db,  $styles;
	if($class === 'fatal_error') {
		$styles [] = 'body{background-color:#000000;color:#FF0033}';
	}
	if($class === 'init' || ! $db instanceof PDO){
		return;
	}
	$coltxt=get_setting('coltxt');
	if(!empty($U['bgcolour'])){
		$colbg=$U['bgcolour'];
	}else{
		$colbg=get_setting('colbg');
	}
}
function print_stylesheet(string $class): void
{
	global $styles;
	
	// Try loading external CSS first
	foreach($styles as $style) {
		echo "<link rel=\"stylesheet\" type=\"text/css\" href=\"$style\">";
	}
	// Fallback inline CSS if external blocked
	echo '<style>
	body .logout {
	background:black;
	}
	
	.frame-wrapper {
    position: relative;
    width: 100%;
	background:black;
    height: 100%;
    z-index: 1;
}
	body .frame-mid {
		background:transparent;
		  color: #FFFFFF;
    font-family: "monospace", system-ui;
    font-size: 14px;
	align-items: center;
    text-align: center;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
    border: none;
    outline: none;
    box-shadow: none;
	}



	.msg { border-bottom: 1px solid #363636;}
input, select, textarea, button {padding: 0.2em; border: 1px solid #ffffff; border-radius: 0.5em; background-color:black;}
#messages small {color: #989898;}
#messages {display: block; width: 80%;}
.messages #topic {display: block; width: 80%;}
.messages #chatters {
  display: block;
  float: right;
    background: rgba(0, 20, 20, 0.8);
  width: 15%;
  overflow-y: auto;
  position: fixed;
  right: 0;
  max-height: 100%;
  top: 2em;
  text-decoration: none;
  bottom: 2em;
  border-left: 1px solid #00ff00;
  scrollbar-width: thin;
  scrollbar-color: #00ff00 #000;
  padding: 10px; 
}

.messages #chatters td, 
.messages #chatters tr, 
.messages #chatters th {
  display: table-row;
  border-bottom: 1px solid rgba(0, 255, 0, 0.1);
}
#chatters, 
#chatters table {
  font-weight: bolder;
  border-spacing: 0px;
  width: 100%;
  font-size:15px ;
}
#manualrefresh {
  display: block;
  position: fixed;
  text-align: center;
  left: 25%;
  width: 50%;
  top: -200%;
  animation: timeout_messages 25s forwards;
  z-index: 2;
  background-color: #500000;
}
.msg {
  max-height: 180px;
  overflow-y: auto;
  background: rgba(0, 20, 20, 0.9);
  padding: 15px;
  border-radius: 15px;
  margin-bottom: 15px;
  border: 1px solid rgba(0, 255, 0, 0.2);
  box-shadow: 0 0 10px rgba(0, 255, 0, 0.1);
}

.msg:hover {
  background: rgba(0, 30, 30, 0.95);
  border-color: rgba(0, 255, 0, 0.4);
  box-shadow: 0 0 15px rgba(0, 255, 0, 0.2);
  transform: translateY(-2px);
}

.messages #chatters table a {
  display: table-row;
  text-decoration: none;
  color: #ffffff;
  font-family: "Fira Code", monospace;
}
.messages #chatters .afk-badge {
  color: #ff0000; 
  font-size: 10px; 
  text-align: center;
  font-weight: bold; 
  text-transform: uppercase; 

  margin-left: 2px; 
  display: inline-flex;
  align-items: center;
}
body {
    background-color: black;
    color: #FFFFFF;
    font-family: "monospace", system-ui;
    font-size: 14px;
	align-items: center;
    text-align: center;
    width: 100%;
    height: 100%;
    margin: 0;
    padding: 0;
    border: none;
    outline: none;
    box-shadow: none;
}

.post {
    margin-top: 10px;
}
#rules-style {
    color: red;
    font-weight: bold;
    padding-left: 10px;
}
#about-style {
    color: rgb(0, 255, 0);
    font-weight: bold;
    font-size: 14px;
    padding-left: 10px;
}
#topic {
    font-size: 14px;
    font-weight: bold;
    text-align: center;
    color: white;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 10px;
}

a:visited { color: #B33CB4; }
a:link { color: #00A2D4; }
a:active { color: #55A2D4; }
input[type=text], input[type=password], input[type=submit], select, textarea {
    border: none;
    background-color: rgba(120,120,120,0.3);
    font-family: "monospace", system-ui;
    color: #fff !important;
    padding: 5px;
    border-radius: 10px;
}
.error { color: #FF0033; text-align: left; }
.delbutton { background-color: #660000; }
.backbutton { background-color: #004400; }
#exitbutton { background-color: #AA0000; }
.setup table table,
.admin table table,
.profile table table {
    width: 100%;
    text-align: left;
}
.alogin table, .init table, .destroy_chat table, .delete_account table,
.sessions table, .filter table, .linkfilter table, .notes table,
.approve_waiting table, .del_confirm table, .profile table,
.admin table, .backup table, .setup table {
    margin-left: auto;
    margin-right: auto;
}
.setup table table table,
.admin table table table,
.profile table table table {
    border-spacing: 0px;
    margin-left: auto;
    margin-right: unset;
    width: unset;
}
.setup table table td, .backup #restoresubmit, .backup #backupsubmit,
.admin table table td, .profile table table td, .login td+td,
.alogin td+td {
    text-align: right;
}
.init td, .backup #restorecheck td, .admin #clean td, .admin #regnew td,
.session td, .messages, .inbox, .approve_waiting td, .choose_messages,
.greeting, .help, .login td, .alogin td {
    text-align: left;
}
.approve_waiting #action td:only-child, .help #backcredit,
.login td:only-child, .alogin td:only-child, .init td:only-child {
    text-align: center;
}
.sessions td, .sessions th, .approve_waiting td, .approve_waiting th {
    padding: 5px;
}
.sessions td td { padding: 1px; }
.notes textarea { 
    height: 80vh; 
    width: 80%; 
    color: white; 
}

.post table, .controls table, .login table {
    border-spacing: 0px;
    margin-left: auto;
    margin-right: auto;
}


@keyframes fadeIn {
  from {
    opacity: 0;
    transform: translateY(-20px);
  }
  to {
    opacity: 1; 
    transform: translateY(0);
  }
}

@keyframes glitch {
  0% {
    text-shadow: 2px 2px #00ff00, -2px -2px #ff0000;
  }
  25% {
    text-shadow: -2px 2px #00ff00, 2px -2px #ff0000;
  }
  50% {
    text-shadow: 2px -2px #00ff00, -2px 2px #ff0000;
  }
  75% {
    text-shadow: -2px -2px #00ff00, 2px 2px #ff0000;
  }
  100% {
    text-shadow: 2px 2px #00ff00, -2px -2px #ff0000;
  }
}

@keyframes matrixBg {
  0% {
    background-position: 0% 0%;
  }
  100% {
    background-position: 0% 100%;
  }
}

.login {
  background-color: rgba(0,0,0,0.8); 
  background-image: url("./danchat.svg");
  background-size: cover;
  background-position: center;
  background-blend-mode: overlay;
  padding: 0;
  position: relative;
  z-index: 1;
}

.login::before {
  content: "";
  position: absolute;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: linear-gradient(rgba(0,0,0,0.4), rgba(0,0,0,0.6));
  z-index: -1;
}

#login_table {
  background: rgba(0,20,20,0.8);
backdrop-filter: blur(10px);
  padding: 35px;  
  padding-top: 10px;
  border-radius: 10px;
  box-shadow: 0 0 20px rgba(0,255,0,0.2);
  box-shadow: 0 0 10px rgba(57,255,20,0.5);
  animation: fadeIn 1s ease-out;
  max-width: auto;
width: 400px;
  margin: 20px auto;
}

#nickname_input,
#password_input,
#regpass_input,
#globalpass_input {
  width: 100%;
  padding: 12px;
  margin: 8px 0;
  background: rgba(0,20,20,0.6);
  border: 1px solid rgba(0,255,0,0.3);
  color: #00ff00;
  font-family: "Courier New", monospace;
  transition: all 0.3s ease;
}

#nickname_input:focus,
#password_input:focus,
#regpass_input:focus,
#globalpass_input:focus {
  border-color: #00ff00;
  box-shadow: 0 0 10px rgba(0,255,0,0.3);
  outline: none;
}

#nickname_label,
#password_label,
#regpass_label,
#globalpass_label {
  color: #00ff00;
  font-family: "Courier New", monospace;
  text-transform: uppercase;
  letter-spacing: 2px;
  font-size: 0.9em;
  text-shadow: 0 0 5px rgba(0,255,0,0.5);
}

#submit_button,
#submit_btn {
  width: 100%;
  padding: 12px;
  background: linear-gradient(45deg, #330000, #006600);
  color: #00ff00;
  border: 1px solid #00ff00;
  font-family: "Courier New", monospace;
  text-transform: uppercase;
  letter-spacing: 2px;
  cursor: pointer;
  transition: all 0.3s ease;
}

#submit_button:hover,
#submit_btn:hover {
  background: linear-gradient(45deg, #b90404, #990000);
  box-shadow: 0 0 20px rgba(255, 0, 0, 0.4);
  transform: scale(1.02);
}


.cyber-glitch-title {
    font-family: "Fira Code", monospace;
    font-weight: 900;
    font-size: 1.8em;
    position: relative;
    text-align: center;
    color: #0f0;
    text-shadow: 0 0 10px #0f0, 0 0 20px #0f0, 0 0 30px #0f0;
    padding: 20px;
    background: rgba(0,20,0,0.2);
    border-radius: 5px;
    margin: 30px auto;
    max-width: 400px;
    box-sizing: border-box;
    animation: matrixRain 2s infinite;
    overflow: hidden;
}

.cyber-glitch-title::before,
.cyber-glitch-title::after {
    content: attr(data-text);
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,10,0,0.2);
    clip-path: polygon(0 0, 100% 0, 100% 45%, 0 45%);
    padding: 20px;
    box-sizing: border-box;
}

.cyber-glitch-title::before {
    left: 3px;
    text-shadow: -2px 0 #ff0000;
    animation: glitchEffect1 750ms infinite;
    background: rgba(255,0,0,0.1);
    transform: skew(-20deg);
}

.cyber-glitch-title::after {
    left: -3px; 
    text-shadow: 2px 0 #0000ff;
    animation: glitchEffect2 375ms infinite;
    background: rgba(0,0,255,0.1);
    transform: skew(20deg);
}

@keyframes matrixRain {
    0% {
        box-shadow: 
            inset 0 0 15px #0f0,
            0 0 30px #0f0;
    }
    50% {
        box-shadow:
            inset 0 0 30px #0f0, 
            0 0 60px #0f0;
    }
    100% {
        box-shadow:
            inset 0 0 15px #0f0,
            0 0 30px #0f0;
    }
}

@keyframes glitchEffect1 {
    0% {
        clip-path: polygon(0 15%, 100% 15%, 100% 30%, 0 30%);
        transform: translate(-2px, 2px);
    }
    30% {
        clip-path: polygon(0 40%, 100% 40%, 100% 55%, 0 55%);
        transform: translate(2px, -2px);
    }
    60% {
        clip-path: polygon(0 65%, 100% 65%, 100% 80%, 0 80%);
        transform: translate(-2px, -2px);
    }
    100% {
        clip-path: polygon(0 85%, 100% 85%, 100% 100%, 0 100%);
        transform: translate(2px, 2px);
    }
}

@keyframes glitchEffect2 {
    0% {
        clip-path: polygon(0 10%, 100% 10%, 100% 35%, 0 35%);
        transform: translate(2px);
    }
    35% {
        clip-path: polygon(0 40%, 100% 40%, 100% 65%, 0 65%);
        transform: translate(-2px);
    }
    70% {
        clip-path: polygon(0 70%, 100% 70%, 100% 95%, 0 95%);
        transform: translate(2px);
    }
    100% {
        clip-path: polygon(0 85%, 100% 85%, 100% 100%, 0 100%);
        transform: translate(-2px);
    }
}

@keyframes cyberGlitch2 {
    0% {
        clip: rect(20px, 9999px, 100px, 0);
        transform: skew(-0.4deg);
    }
    25% {
        clip: rect(70px, 9999px, 15px, 0);
        transform: skew(-0.5deg);
    }
    50% {
        clip: rect(80px, 9999px, 30px, 0);
        transform: skew(-0.7deg);
    }
    75% {
        clip: rect(60px, 9999px, 80px, 0);
        transform: skew(-0.3deg);
    }
    100% {
        clip: rect(15px, 9999px, 40px, 0);
        transform: skew(-0.4deg);
    }
}
@media screen and (max-width: 768px) {
    .cyber-glitch-title {
        font-size: 1.2em; 
        padding: 10px;
        max-width: 80%; 
        letter-spacing: 2px; 
        margin: 20px auto; 
    }

    .cyber-glitch-title::before,
    .cyber-glitch-title::after {
        padding: 10px;
    }

    .cyber-glitch-title::before {
        left: 2px;
        text-shadow: -2px 0 #ff0000;
        clip: rect(20px, 450px, 80px, 0);
    }

    .cyber-glitch-title::after {
        left: -2px;
        text-shadow: 2px 0 #0000ff;
        clip: rect(70px, 450px, 120px, 0);
    }

    @keyframes cyberGlitch {
        0% {
            clip: rect(30px, 999px, 40px, 0);
            transform: skew(0.3deg);
        }
        20% {
            clip: rect(10px, 999px, 50px, 0);
            transform: skew(0.2deg);
        }
        40% {
            clip: rect(40px, 999px, 20px, 0);
            transform: skew(0.5deg);
        }
        60% {
            clip: rect(30px, 999px, 60px, 0);
            transform: skew(0.1deg);
        }
        80% {
            clip: rect(50px, 999px, 10px, 0);
            transform: skew(0.3deg);
        }
        100% {
            clip: rect(20px, 999px, 40px, 0);
            transform: skew(0.4deg);
        }
    }

    @keyframes cyberGlitch2 {
        0% {
            clip: rect(15px, 999px, 80px, 0);
            transform: skew(-0.3deg);
        }
        25% {
            clip: rect(60px, 999px, 10px, 0);
            transform: skew(-0.4deg);
        }
        50% {
            clip: rect(70px, 999px, 20px, 0);
            transform: skew(-0.5deg);
        }
        75% {
            clip: rect(50px, 999px, 70px, 0);
            transform: skew(-0.2deg);
        }
        100% {
            clip: rect(10px, 999px, 30px, 0);
            transform: skew(-0.3deg);
        }
    }
}

#system_protocols {
  font-family: "Courier New", monospace;
  color: #00ff00;
  text-transform: uppercase;
  letter-spacing: 2px;
  font-size: 20px;
  text-align: center;
  margin-top: 20px;
  position: relative;
  animation: matrix-glitch 0.3s linear infinite;
}

#system_protocols::before {
  content: attr(data-text);
  position: absolute;
  left: 2px;
  text-shadow: -2px 0 #ff00ff;
  clip: rect(0, 0, 0, 0);
  animation: matrix-glitch 0.5s linear infinite alternate-reverse;
}

#system_protocols::after {
  content: attr(data-text); 
  position: absolute;
  left: -2px;
  text-shadow: -2px 0 #00ffff;
  clip: rect(0, 0, 0, 0);
  animation: matrix-glitch 0.8s linear infinite alternate-reverse;
}

@keyframes matrix-glitch {
  0% { clip: rect(0, 900px, 0, 0); }
  20% { clip: rect(44px, 900px, 56px, 0); }
  40% { clip: rect(20px, 900px, 100px, 0); }
  60% { clip: rect(10px, 900px, 25px, 0); }
  80% { clip: rect(30px, 900px, 80px, 0); }
  100% { clip: rect(0, 900px, 0, 0); }
}

.cyber-glitch-title::before {
    content: "";
    position: absolute;
    top: -5px;
    left: -5px;
    width: calc(100% + 10px);
    height: calc(100% + 10px);
    border-radius: 10px;
}


#rules_text {
  color: #ff0000;
  font-family: "Courier New", monospace;
  line-height: 1.6;
  font-size: 14px;
  text-align: center;
  font-weight: bolder;
}

#changelang {
  margin-top: 30px;
  text-align: center;
}
#topic {
  position: block;
  background-color: #000000;
  top: 0;
  left: 0;
  right: 0;
  z-index: 100;
}

#captcha img {
width: 100%;
height: 30%;
}
#langSelect {
  background: rgba(0,20,20,0.6);
  color: #00ff00;
  border: 1px solid rgba(0,255,0,0.3);
  padding: 10px;
  font-family: "Courier New", monospace;
  cursor: pointer;
}

#chatters a {
  color:white;
  font-size: smaller;
}

.controls #donatebutton {
  background-color: #e5ff00;
  color: #000000;
  border: 1px solid #d9ff00;
  border-radius: 10px;
  font-family: "Courier New", monospace;
}
td#color_label {
  color: #00ff00;
  padding: 10px;
}
#messages div .hapus{
  color: red;
  float: right;
  border: none;
  background: none;
  cursor: pointer;
  padding: 0;
  font-size: 1em;
  font-weight: bold;
  text-shadow: 1px 1px 2px #000;
}
.cyber-container {
  padding: 2rem;
  margin: 20px;
}
.glitch-wrapper {
  margin-bottom: 2rem;
}
.cyber-glitch {
  color: #0ff;
  font-family: "Courier New", monospace;
  text-shadow: 2px 2px #ff00ff;
  position: relative;
  animation: glitch 1s infinite;
}
.cyber-glitch::before {
  content: attr(data-text);
  position: absolute;
  left: -2px;
  text-shadow: -2px 0 red;
  background: black;
  overflow: hidden;
  top: 0;
  animation: noise-1 2s linear infinite alternate-reverse;
}
.cyber-glitch::after {
  content: attr(data-text);
  position: absolute;
  left: 2px;
  text-shadow: -2px 0 blue;
  background: black;
  overflow: hidden;
  top: 0;
  animation: noise-2 3s linear infinite alternate-reverse;
}
.matrix-text {
  color: #00ff00;
  font-family: "Courier New", monospace;
  text-shadow: 0 0 5px #00ff00;
  margin: 1rem 0;
}
.cyber-warning {
  color: #ff0000;
  font-family: "Courier New", monospace;
  text-shadow: 0 0 5px #ff0000;
  margin: 1rem 0;
}
.cyber-button {
  background: transparent;
  border: 2px solid #0ff;
  color: #0ff;
  padding: 10px 20px;
  font-family: "Courier New", monospace;
  text-transform: uppercase;
  cursor: pointer;
  transition: all 0.3s;
  position: relative;
  overflow: hidden;
}
.cyber-button:hover {
  background: #0ff;
  color: #000;
  box-shadow: 0 0 20px #0ff;
}
@keyframes glitch {
  2%, 64% { transform: translate(2px,0) skew(0deg); }
  4%, 60% { transform: translate(-2px,0) skew(0deg); }
  62% { transform: translate(0,0) skew(5deg); }
}
@keyframes noise-1 {
  0%, 20%, 40%, 60%, 70%, 90% {clip-path: inset(80% 0 0 0);}
  10%, 30%, 50%, 80%, 100% {clip-path: inset(0 0 80% 0);}
}
@keyframes noise-2 {
  0%, 20%, 40%, 60%, 70%, 90% {clip-path: inset(80% 0 0 0);}
  10%, 30%, 50%, 80%, 100% {clip-path: inset(0 0 80% 0);}
}
.warnss{
color:red;
}
#frame-background {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    filter: saturate(0.3) brightness(0.7);
    background-image: url("init.jpg");
    background-size: cover;
    background-position: center;
    background-repeat: no-repeat;
    z-index: -1;
}

#frameset-mid iframe {
    position: fixed;
    top: 25.5%;
    left: 0;
    width: 100%;
    height: calc(90% - 45px);
    margin: 0;
    padding: 0;
    overflow: hidden;
    background: transparent;
    border: none;
    z-index: 1;
}
	.messages {
background: transparent;
	}

#frameset-top iframe {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 18%;
    margin: 0;
	margin-top: 3rem;
    padding: 0;
    overflow: hidden;
    border: none;
    border-bottom: 1px solid #00ff00;
    background: rgba(0,0,0,0.8);
    z-index: 2;
}

#frameset-bot iframe {
    position: fixed;
    bottom: 0;
    left: 0;
    width: 100%;
    height: 45px;
    margin: 0;
    padding: 0;
    overflow: hidden;
    border: none;
    border-top: 1px solid #00ff00;
    background: rgba(0,0,0,0.8);
    z-index: 2;
}

.filter table table {
  width: 100%;
}

.filter table table td:nth-child(1) {
  width: 8em;
  font-weight: bold;
}

.filter table table td:nth-child(2),
.filter table table td:nth-child(3) {
  width: 12em;
}

.filter table table td:nth-child(4) {
  width: 9em;
}

.filter table table td:nth-child(5),
.filter table table td:nth-child(6),
.filter table table td:nth-child(7),
.filter table table td:nth-child(8) {
  width: 5em;
}

.linkfilter table table {
  width: 100%;
}

.linkfilter table table td:nth-child(1) {
  width: 8em;
  font-weight: bold;
}

.linkfilter table table td:nth-child(2),
.linkfilter table table td:nth-child(3) {
  width: 12em;
}

.linkfilter table table td:nth-child(4),
.linkfilter table table td:nth-child(5) {
  width: 5em;
}

#chatters {
  padding: 10px;
}

#chatters, 
#chatters table {
  border-spacing: 0px;
}

#manualrefresh {
  display: block;
  position: fixed;
  text-align: center;
  left: 25%;
  width: 50%;
  top: -200%;
  animation: timeout_messages 20s forwards;
  z-index: 2;
  background-color: #500000;
}

@keyframes timeout_messages {
  0% { top: -200%; }
  99% { top: -200%; }
  100% { top: 0%; }
}


#bottom_link {
  position: fixed;
  top: 0.5em;
  right: 0.5em;
}

#top_link {
  position: fixed;
  bottom: 0.5em;
  right: 0.5em;
}

#chatters th,
#chatters td {
  vertical-align: top;
}

a img {
  width: 15%;
}

a:hover img {
  width: 35%;
}

div #messages {
  word-wrap: break-word;
  padding: 10px;
  background:rgba(0,0,0,0.5);
}
body.admin .admin-panel {
  background: #000000;
  color: #0f0;
  border: 1px solid #1a1a1a;
  padding: 20px;
  border-radius: 5px;
  box-shadow: 0 0 10px rgba(0,255,0,0.2);
}

body.admin .admin-panel h2 {
  color: rgb(255, 255, 255);
  text-transform: uppercase;
  letter-spacing: 2px;
  margin-bottom: 20px;
}

body.admin .admin-section {
  background: #111;
  border: 1px solid #222;
  padding: 15px;
  margin: 10px 0;
  border-radius: 3px;
}

body.admin .admin-section th {
  color: rgb(255, 255, 255);
  font-size: 1.1em;
  padding: 10px;
  text-transform: uppercase;
}

body.admin select, 
body.admin input[type="text"], 
body.admin input[type="password"] {
  background: #000;
  border: 1px solid #0f0;
  color: #0f0;
  padding: 8px;
  border-radius: 3px;
  margin: 5px;
}

body.admin select:focus, 
body.admin input:focus {
  box-shadow: 0 0 5px #0f0;
  outline: none;
}

body.admin input[type="submit"] {
  background: #000;
  color: #0f0;
  border: 1px solid #0f0;
  padding: 8px 15px;
  border-radius: 3px;
  cursor: pointer;
  text-transform: uppercase;
  letter-spacing: 1px;
  transition: all 0.3s;
}

body.admin input[type="submit"]:hover {
  background: #0f0;
  color: #000;
  box-shadow: 0 0 10px #0f0;
}

body.admin .delbutton {
  background: #300;
  border-color: #f00;
  color: #f00;
}

body.admin .delbutton:hover {
  background: #f00;
  color: #000;
  box-shadow: 0 0 10px #f00;
}

body.admin .status-message {
  color: rgb(255, 60, 0);
  text-align: center;
  padding: 10px;
  margin: 10px 0;
  border: 1px solid #0f0;
  background: rgba(0,20,0,0.5);
}

body.admin .badwords-table {
  width: 100%;
  border-collapse: collapse;
}

body.admin .badwords-table th,
body.admin .badwords-table td {
  padding: 10px;
  border: 1px solid #222;
}

body.admin .badwords-table tr:hover {
  background: rgba(0,255,0,0.1);
}

body.admin .btn {
  padding: 5px 10px;
  border-radius: 3px;
  cursor: pointer;
  text-transform: uppercase;
  font-size: 0.9em;
}

body.admin .btn-primary {
  background: #000;
  color: #0f0;
  border: 1px solid #0f0;
}

body.admin .btn-danger {
  background: #000;
  color: #f00;
  border: 1px solid #f00;
}

body.admin .btn:hover {
  box-shadow: 0 0 10px currentColor;
}


#navbar {
  position: relative;
  height: 50px;
  background: rgba(0, 0, 0, 0.95);
  border-bottom: 1px solid rgba(255, 255, 255, 0.1);
  box-shadow: 0 4px 30px rgba(0, 0, 0, 0.3);
  z-index: 1000;
  display: flex;
  align-items: center;
  padding: 0 5px;
}

#navbar a {
  color: #e4e4e4;
  text-decoration: none;
  font-family: "Share Tech Mono", monospace;
  font-size: 14px;
  font-weight: bolder;
  text-transform: uppercase;
  letter-spacing: 1px;
  padding: 5px 10px;
  border-radius: 6px;
  transition: all 0.2s ease;
  margin: 0 5px;
}

#navbar a:hover {
  color: #ffffff;
  background: rgba(77, 77, 77, 0.1);
  box-shadow: 0 0 20px rgba(255, 255, 255, 0.05);
  transform: translateY(-1px);
}

</style>';

	if ($class === 'init') {
		return;
	}
}

function print_end(): void
{
	echo '</body></html>';
	exit;
}

function credit() : string {
	return '<small><br><br><a target="_blank" href="https://github.com/0srD4n/DanChat" rel="noreferrer noopener">- DanChat - ' . VERSION . '</a></small>';

}
function meta_html() : string {
	global $U, $db;
	$colbg = '';
	$description = '';
	if(!empty($U['bgcolour'])){
		$colbg = $U['bgcolour'];
	}else{
		if($db instanceof PDO){
			$colbg = get_setting('colbg');
			$description = '<meta name="description" content="'.htmlspecialchars(get_setting('metadescription')).'">';
		}
	}
	return '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8"><meta name="referrer" content="no-referrer"><meta name="viewport" content="width=device-width, initial-scale=1, user-scalable=yes"><meta name="theme-color" content="#'.$colbg.'"><meta name="msapplication-TileColor" content="#'.$colbg.'">' . $description;
}

function form(string $action, string $do='') : string {
	global $language, $session;
	$form="<form action=\"$_SERVER[SCRIPT_NAME]\" enctype=\"multipart/form-data\" method=\"post\">".hidden('lang', $language).hidden('nc', substr(time(), -6)).hidden('action', $action);
	if(!empty($session)){
		$form.=hidden('session', $session);
	}
	if($do!==''){
		$form.=hidden('do', $do);
	}
	return $form;
}

function form_target(string $target, string $action, string $do='') : string {
	global $language, $session;
	$form="<form action=\"$_SERVER[SCRIPT_NAME]\" enctype=\"multipart/form-data\" method=\"post\" target=\"$target\">".hidden('lang', $language).hidden('nc', substr(time(), -6)).hidden('action', $action);
	if(!empty($session)){
		$form.=hidden('session', $session);
	}
	if($do!==''){
		$form.=hidden('do', $do);
	}
	return $form;
}

function hidden(string $name='', string $value='') : string {
	return "<input type=\"hidden\" name=\"$name\" value=\"$value\">";
}

function submit(string $value='', string $extra_attribute='') : string {
	return "<input type=\"submit\" value=\"$value\" $extra_attribute>";
}

function thr(): void
{
	echo '<tr><td><hr></td></tr>';
}

function print_start(string $class='', int $ref=0, string $url=''): void
{
	global $language, $dir;
	prepare_stylesheets($class);
	send_headers();
	if($enableMetaRefresh && $refresh > 0) {
        echo "<meta http-equiv=\"refresh\" content=\"$refresh;url=$url\">";
    }
	if(!empty($url)){
		$url=str_replace('&amp;', '&', $url);// Don't escape "&" in URLs here, it breaks some (older) browsers and js refresh!
		header("Refresh: $ref; URL=$url");
	}
	echo '<!DOCTYPE html><html lang="'.$language.'" dir="'.$dir.'"><head>'.meta_html();
	if(!empty($url)){
		echo "<meta http-equiv=\"Refresh\" content=\"$ref; URL=$url\">";
	}
	if($class==='init'){
		echo '<title>'._('Initial Setup').'</title>';
	}else{
		echo '<title>'.get_setting('chatname').'</title>';
	}
	print_stylesheet($class);
	echo "</head><body class=\"$class\">";

}

function send_redirect(string $url): void
{
	$url=trim(htmlspecialchars_decode(rawurldecode($url)));
	preg_match('~^(.*)://~u', $url, $match);
	$url=preg_replace('~^(.*)://~u', '', $url);
	$escaped=htmlspecialchars($url);
	if(isset($match[1]) && ($match[1]==='http' || $match[1]==='https')){
		print_start('redirect', 0, $match[0].$escaped);
		echo '<p>'.sprintf(_('Redirecting to: %s'), "<a href=\"$match[0]$escaped\">$match[0]$escaped</a>").'</p>';
	}else{
		print_start('redirect');
		if(!isset($match[0])){
			$match[0]='';
		}
		if(preg_match('~^(javascript|blob|data):~', $url)){
			echo '<p>'.sprintf(_('Dangerous non-http link requested, copy paste this link if you are really sure: %s'), "$match[0]$escaped").'</p>';
		} else {
			echo '<p>'.sprintf(_('Non-http link requested: %s'), "<a href=\"$match[0]$escaped\">$match[0]$escaped</a>").'</p>';
		}
		echo '<p>'.sprintf(_("If it's not working, try this one: %s"), "<a href=\"http://$escaped\">http://$escaped</a>").'</p>';
	}
	print_end();
}


function send_access_denied(): void
{
	global $U;
	http_response_code(403);
	print_start('access_denied');
	echo '<h1>'._('Access denied').'</h1>'.sprintf(_("You are logged in as %s and don't have access to this section."), style_this(htmlspecialchars($U['nickname']), $U['style'])).'<br>';
	echo form('logout');
	echo submit(_('Logout'), 'id="exitbutton"')."</form>";
	print_end();
}

function send_captcha(): void
{
	global $db, $memcached;
	$difficulty=(int) get_setting('captcha');
	if($difficulty===0 || !extension_loaded('gd')){
		return;
	}
	$captchachars=get_setting('captchachars');
	$length=strlen($captchachars)-1;
	$code='';
	for($i=0;$i<5;++$i){
		$code.=$captchachars[mt_rand(0, $length)];
	}
	$randid=mt_rand();
	$time=time();
	if(MEMCACHED){
		$memcached->set(DBNAME . '-' . PREFIX . "captcha-$randid", $code, get_setting('captchatime'));
	}else{
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'captcha (id, time, code) VALUES (?, ?, ?);');
		$stmt->execute([$randid, $time, $code]);
	}
	echo '<tr id="captcha"><td>'._('Copy:').'<br>';
	if($difficulty===1){
		$im=imagecreatetruecolor(55, 24);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5, $code, $fg);
		echo '<img alt="" width="55" height="24" src="data:image/gif;base64,';
	}elseif($difficulty===2){
		$im=imagecreatetruecolor(55, 24);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		imagestring($im, 5, 5, 5, $code, $fg);
		$line=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<2;++$i){
			imageline($im, 0, mt_rand(0, 24), 55, mt_rand(0, 24), $line);
		}
		$dots=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<100;++$i){
			imagesetpixel($im, mt_rand(0, 55), mt_rand(0, 24), $dots);
		}
		echo '<img alt="" width="55" height="24" src="data:image/gif;base64,';
	}elseif($difficulty===3){
		$im=imagecreatetruecolor(55, 24);
		$bg=imagecolorallocatealpha($im, 0, 0, 0, 127);
		$fg=imagecolorallocate($im, 255, 255, 255);
		$cc=imagecolorallocate($im, 200, 200, 200);
		$cb=imagecolorallocatealpha($im, 0, 0, 0, 127);
		imagefill($im, 0, 0, $bg);
		$line=imagecolorallocate($im, 255, 255, 255);
		$deg=(mt_rand(0,1)*2-1)*mt_rand(10, 20);

		$background=imagecreatetruecolor(120, 80);
		imagefill($background, 0, 0, $cb);

		for ($i=0; $i<20; $i++) {
			$char=imagecreatetruecolor(12, 16);
			imagestring($char, 5, 2, 2, $captchachars[mt_rand(0, $length)], $cc);
			$char = imagerotate($char, (mt_rand(0,1)*2-1)*mt_rand(10, 20), $cb);
			$char = imagescale($char, 24, 32);
			imagefilter($char, IMG_FILTER_SMOOTH, 0.6);
			imagecopy($background, $char, rand(0, 100), rand(0, 60), 0, 0, 24, 32);
		}

		imagestring($im, 5, 5, 5, $code, $fg);
		$im = imagescale($im, 110, 48);
		imagefilter($im, IMG_FILTER_SMOOTH, 0.5);
		imagefilter($im, IMG_FILTER_GAUSSIAN_BLUR);
		$im = imagerotate($im, $deg, $bg);
		$im = imagecrop($im, array('x'=>0, 'y'=>0, 'width'=>120, 'height'=>80));
		imagecopy($background, $im, 0, 0, 0, 0, 110, 80);
		imagedestroy($im);
		$im = $background;

		for($i=0;$i<1000;++$i){
			$c = mt_rand(100,230);
			$dots=imagecolorallocate($im, $c, $c, $c);
			imagesetpixel($im, mt_rand(0, 120), mt_rand(0, 80), $dots);
		}
		imagedestroy($char);
		echo '<img width="120" height="80" src="data:image/png;base64,';
	}else{
		$im=imagecreatetruecolor(150, 200);
		$bg=imagecolorallocate($im, 0, 0, 0);
		$fg=imagecolorallocate($im, 255, 255, 255);
		imagefill($im, 0, 0, $bg);
		$chars=[];
		$x = $y = 0;
		for($i=0;$i<10;++$i){
			$found=false;
			while(!$found){
				$x=mt_rand(10, 140);
				$y=mt_rand(10, 180);
				$found=true;
				foreach($chars as $char){
					if($char['x']>=$x && ($char['x']-$x)<25){
						$found=false;
					}elseif($char['x']<$x && ($x-$char['x'])<25){
						$found=false;
					}
					if(!$found){
						if($char['y']>=$y && ($char['y']-$y)<25){
							break;
						}elseif($char['y']<$y && ($y-$char['y'])<25){
							break;
						}else{
							$found=true;
						}
					}
				}
			}
			$chars[]=['x', 'y'];
			$chars[$i]['x']=$x;
			$chars[$i]['y']=$y;
			if($i<5){
				imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $captchachars[mt_rand(0, $length)], $fg);
			}else{
				imagechar($im, 5, $chars[$i]['x'], $chars[$i]['y'], $code[$i-5], $fg);
			}
		}
		$follow=imagecolorallocate($im, 200, 0, 0);
		imagearc($im, $chars[5]['x']+4, $chars[5]['y']+8, 16, 16, 0, 360, $follow);
		for($i=5;$i<9;++$i){
			imageline($im, $chars[$i]['x']+4, $chars[$i]['y']+8, $chars[$i+1]['x']+4, $chars[$i+1]['y']+8, $follow);
		}
		$line=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<5;++$i){
			imageline($im, 0, mt_rand(0, 200), 150, mt_rand(0, 200), $line);
		}
		$dots=imagecolorallocate($im, 255, 255, 255);
		for($i=0;$i<1000;++$i){
			imagesetpixel($im, mt_rand(0, 150), mt_rand(0, 200), $dots);
		}
		echo '<img alt="" width="150" height="200" src="data:image/gif;base64,';
	}
	ob_start();
	imagegif($im);
	imagedestroy($im);
	echo base64_encode(ob_get_clean()).'">';
	echo '</td><td>'.hidden('challenge', $randid).'<input type="text" name="captcha" size="15" autocomplete="off" required></td></tr>';
}

function send_setup(array $C): void
{
	global $U;
	print_start('setup');
	echo '<h2>'._('Chat Setup').'</h2>'.form('setup', 'save');
	echo '<table id="guestaccess">';
	thr();
	$ga=(int) get_setting('guestaccess');
	echo '<tr><td><table><tr><th>'._('Change Guestaccess').'</th><td>';
	echo '<select name="guestaccess">';
	echo '<option value="1"';
	if($ga===1){
		echo ' selected';
	}
	echo '>'._('Allow').'</option>';
	echo '<option value="2"';
	if($ga===2){
		echo ' selected';
	}
	echo '>'._('Allow with waitingroom').'</option>';
	echo '<option value="3"';
	if($ga===3){
		echo ' selected';
	}
	echo '>'._('Require moderator approval').'</option>';
	echo '<option value="0"';
	if($ga===0){
		echo ' selected';
	}
	echo '>'._('Only members').'</option>';
	echo '<option value="4"';
	if($ga===4){
		echo ' selected';
	}
	echo '>'._('Disable chat').'</option>';
	echo '</select></td></tr></table></td></tr>';
	thr();
	$englobal=(int) get_setting('englobalpass');
	echo '<tr><td><table id="globalpass"><tr><th>'._('Global Password:').'</th><td>';
	echo '<table>';
	echo '<tr><td><select name="englobalpass">';
	echo '<option value="0"';
	if($englobal===0){
		echo ' selected';
	}
	echo '>'._('Disabled').'</option>';
	echo '<option value="1"';
	if($englobal===1){
		echo ' selected';
	}
	echo '>'._('Enabled').'</option>';
	echo '<option value="2"';
	if($englobal===2){
		echo ' selected';
	}
	echo '>'._('Only for guests').'</option>';
	echo '</select></td><td>&nbsp;</td>';
	echo '<td><input type="text" name="globalpass" value="'.htmlspecialchars(get_setting('globalpass')).'"></td></tr>';
	echo '</table></td></tr></table></td></tr>';
	thr();
	$ga=(int) get_setting('guestreg');
	echo '<tr><td><table id="guestreg"><tr><th>'._('Let guests register themselves').'</th><td>';
	echo '<select name="guestreg">';
	echo '<option value="0"';
	if($ga===0){
		echo ' selected';
	}
	echo '>'._('Disabled').'</option>';
	echo '<option value="1"';
	if($ga===1){
		echo ' selected';
	}
	echo '>'._('As applicant').'</option>';
	echo '<option value="2"';
	if($ga===2){
		echo ' selected';
	}
	echo '>'._('As member').'</option>';
	echo '</select></td></tr></table></td></tr>';
	thr();
	echo '<tr><td><table id="sysmessages"><tr><th>'._('System messages').'</th><td>';
	echo '<table>';
	foreach($C['msg_settings'] as $setting => $title){
		echo "<tr><td>&nbsp;$title</td><td>&nbsp;<input type=\"text\" name=\"$setting\" value=\"".get_setting($setting).'"></td></tr>';
	}
	echo '</table></td></tr></table></td></tr>';
	foreach($C['text_settings'] as $setting => $title){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$title.'</th><td>';
		echo "<input type=\"text\" name=\"$setting\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['colour_settings'] as $setting => $title){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$title.'</th><td>';
		echo "<input type=\"color\" name=\"$setting\" value=\"#".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	thr();
	echo '<tr><td><table id="captcha"><tr><th>'._('Captcha').'</th><td>';
	echo '<table>';
	if(!extension_loaded('gd')){
		echo '<tr><td>'.sprintf(_('The %s extension of PHP is required for this feature. Please install it first.'), 'gd').'</td></tr>';
	}else{
		echo '<tr><td><select name="dismemcaptcha">';
		$dismemcaptcha=(bool) get_setting('dismemcaptcha');
		echo '<option value="0"';
		if(!$dismemcaptcha){
			echo ' selected';
		}
		echo '>'._('Enabled').'</option>';
		echo '<option value="1"';
		if($dismemcaptcha){
			echo ' selected';
		}
		echo '>'._('Only for guests').'</option>';
		echo '</select></td><td><select name="captcha">';
		$captcha=(int) get_setting('captcha');
		echo '<option value="0"';
		if($captcha===0){
			echo ' selected';
		}
		echo '>'._('Disabled').'</option>';
		echo '<option value="1"';
		if($captcha===1){
			echo ' selected';
		}
		echo '>'._('Simple').'</option>';
		echo '<option value="2"';
		if($captcha===2){
			echo ' selected';
		}
		echo '>'._('Moderate').'</option>';
		echo '<option value="3"';
		if($captcha===3){
			echo ' selected';
		}
		echo '>'._('Hard').'</option>';
		echo '<option value="4"';
		if($captcha===4){
			echo ' selected';
		}
		echo '>'._('Extreme').'</option>';
		echo '</select></td></tr>';
	}
	echo '</table></td></tr></table></td></tr>';
	thr();
	echo '<tr><td><table id="defaulttz"><tr><th>'._('Default time zone').'</th><td>';
	echo '<select name="defaulttz">';
	$tzs=timezone_identifiers_list();
	$defaulttz=get_setting('defaulttz');
	foreach($tzs as $tz){
		echo "<option value=\"$tz\"";
		if($defaulttz==$tz){
			echo ' selected';
		}
		echo ">$tz</option>";
	}
	echo '</select>';
	echo '</td></tr></table></td></tr>';
	foreach($C['textarea_settings'] as $setting => $title){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$title.'</th><td>';
		echo "<textarea name=\"$setting\" rows=\"4\" cols=\"60\">".htmlspecialchars(get_setting($setting)).'</textarea>';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['number_settings'] as $setting => $title){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$title.'</th><td>';
		echo "<input type=\"number\" name=\"$setting\" value=\"".htmlspecialchars(get_setting($setting)).'">';
		echo '</td></tr></table></td></tr>';
	}
	foreach($C['bool_settings'] as $setting => $title){
		thr();
		echo "<tr><td><table id=\"$setting\"><tr><th>".$title.'</th><td>';
		echo "<select name=\"$setting\">";
		$value=(bool) get_setting($setting);
		echo '<option value="0"';
		if(!$value){
			echo ' selected';
		}
		echo '>'._('Disabled').'</option>';
		echo '<option value="1"';
		if($value){
			echo ' selected';
		}
		echo '>'._('Enabled').'</option>';
		echo '</select></td></tr>';
		echo '</table></td></tr>';
	}
	thr();
	echo '<tr><td>'.submit(_('Apply')).'</td></tr></table></form><br>';
	if($U['status']==8){
		echo '<table id="actions"><tr><td>';
		echo form('setup', 'backup');
		echo submit(_('Backup and restore')).'</form></td><td>';
		echo form('setup', 'destroy');
		echo submit(_('Destroy chat'), 'class="delbutton"').'</form></td></tr></table><br>';
	}
	echo form_target('_parent', 'logout');
	echo submit(_('Logout'), 'id="exitbutton"').'</form>'.credit();
	print_end();
}

function restore_backup(array $C): void
{
	global $db, $memcached;
	if(!extension_loaded('json')){
		return;
	}
	$code=json_decode($_POST['restore'], true);
	if(isset($_POST['settings'])){
		foreach($C['settings'] as $setting){
			if(isset($code['settings'][$setting])){
				update_setting($setting, $code['settings'][$setting]);
			}
		}
	}
	if(isset($_POST['filter']) && (isset($code['filters']) || isset($code['linkfilters']))){
		$db->exec('DELETE FROM ' . PREFIX . 'filter;');
		$db->exec('DELETE FROM ' . PREFIX . 'linkfilter;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'filter (filtermatch, filterreplace, allowinpm, regex, kick, cs) VALUES (?, ?, ?, ?, ?, ?);');
		foreach($code['filters'] as $filter){
			if(!isset($filter['cs'])){
				$filter['cs']=0;
			}
			$stmt->execute([$filter['match'], $filter['replace'], $filter['allowinpm'], $filter['regex'], $filter['kick'], $filter['cs']]);
		}
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'linkfilter (filtermatch, filterreplace, regex) VALUES (?, ?, ?);');
		foreach($code['linkfilters'] as $filter){
			$stmt->execute([$filter['match'], $filter['replace'], $filter['regex']]);
		}
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
			$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
		}
	}
	if(isset($_POST['members']) && isset($code['members'])){
		$db->exec('DELETE FROM ' . PREFIX . 'inbox;');
		$db->exec('DELETE FROM ' . PREFIX . 'members;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, loginfails, timestamps, embed, incognito, style, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		foreach($code['members'] as $member){
			$new_settings=['nocache', 'tz', 'eninbox', 'sortupdown', 'hidechatters', 'nocache_old'];
			foreach($new_settings as $setting){
				if(!isset($member[$setting])){
					$member[$setting]=0;
				}
			}
			$stmt->execute([$member['nickname'], $member['passhash'], $member['status'], $member['refresh'], $member['bgcolour'], $member['regedby'], $member['lastlogin'], $member['loginfails'], $member['timestamps'], $member['embed'], $member['incognito'], $member['style'], $member['nocache'], $member['tz'], $member['eninbox'], $member['sortupdown'], $member['hidechatters'], $member['nocache_old']]);
		}
	}
	if(isset($_POST['notes']) && isset($code['notes'])){
		$db->exec('DELETE FROM ' . PREFIX . 'notes;');
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'notes (type, lastedited, editedby, text) VALUES (?, ?, ?, ?);');
		foreach($code['notes'] as $note){
			if($note['type']==='admin'){
				$note['type']=0;
			}elseif($note['type']==='staff'){
				$note['type']=1;
			}elseif($note['type']==='public'){
				$note['type']=3;
			}
			if(MSGENCRYPTED){
				try {
					$note['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($note['text'], '', AES_IV, ENCRYPTKEY));
				} catch (SodiumException $e){
					send_error($e->getMessage());
				}
			}
			$stmt->execute([$note['type'], $note['lastedited'], $note['editedby'], $note['text']]);
		}
	}
}

function send_backup(array $C): void
{
	global $db;
	$code=[];
	if($_POST['do']==='backup'){
		if(isset($_POST['settings'])){
			foreach($C['settings'] as $setting){
				$code['settings'][$setting]=get_setting($setting);
			}
		}
		if(isset($_POST['filter'])){
			$result=$db->query('SELECT * FROM ' . PREFIX . 'filter;');
			while($filter=$result->fetch(PDO::FETCH_ASSOC)){
				$code['filters'][]=['match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'allowinpm'=>$filter['allowinpm'], 'regex'=>$filter['regex'], 'kick'=>$filter['kick'], 'cs'=>$filter['cs']];
			}
			$result=$db->query('SELECT * FROM ' . PREFIX . 'linkfilter;');
			while($filter=$result->fetch(PDO::FETCH_ASSOC)){
				$code['linkfilters'][]=['match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'regex'=>$filter['regex']];
			}
		}
		if(isset($_POST['members'])){
			$result=$db->query('SELECT * FROM ' . PREFIX . 'members;');
			while($member=$result->fetch(PDO::FETCH_ASSOC)){
				$code['members'][]=$member;
			}
		}
		if(isset($_POST['notes'])){
			$result=$db->query('SELECT * FROM ' . PREFIX . "notes;");
			while($note=$result->fetch(PDO::FETCH_ASSOC)){
				if(MSGENCRYPTED){
					try {
						$note['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($note['text']), null, AES_IV, ENCRYPTKEY);
					} catch (SodiumException $e){
						send_error($e->getMessage());
					}
				}
				$code['notes'][]=$note;
			}
		}
	}
	if(isset($_POST['settings'])){
		$chksettings=' checked';
	}else{
		$chksettings='';
	}
	if(isset($_POST['filter'])){
		$chkfilters=' checked';
	}else{
		$chkfilters='';
	}
	if(isset($_POST['members'])){
		$chkmembers=' checked';
	}else{
		$chkmembers='';
	}
	if(isset($_POST['notes'])){
		$chknotes=' checked';
	}else{
		$chknotes='';
	}
	print_start('backup');
	echo '<h2>'._('Backup and restore').'</h2><table>';
	thr();
	if(!extension_loaded('json')){
		echo '<tr><td>'.sprintf(_('The %s extension of PHP is required for this feature. Please install it first.'), 'json').'</td></tr>';
	}else{
		echo '<tr><td>'.form('setup', 'backup');
		echo '<table id="backup"><tr><td id="backupcheck">';
		echo '<label><input type="checkbox" name="settings" id="backupsettings" value="1"'.$chksettings.'>'._('Settings').'</label>';
		echo '<label><input type="checkbox" name="filter" id="backupfilter" value="1"'.$chkfilters.'>'._('Filter').'</label>';
		echo '<label><input type="checkbox" name="members" id="backupmembers" value="1"'.$chkmembers.'>'._('Members').'</label>';
		echo '<label><input type="checkbox" name="notes" id="backupnotes" value="1"'.$chknotes.'>'._('Notes').'</label>';
		echo '</td><td id="backupsubmit">'.submit(_('Backup')).'</td></tr></table></form></td></tr>';
		thr();
		echo '<tr><td>'.form('setup', 'restore');
		echo '<table id="restore">';
		echo '<tr><td colspan="2"><textarea name="restore" rows="4" cols="60">'.htmlspecialchars(json_encode($code)).'</textarea></td></tr>';
		echo '<tr><td id=\"restorecheck\"><label><input type="checkbox" name="settings" id="restoresettings" value="1"'.$chksettings.'>'._('Settings').'</label>';
		echo '<label><input type="checkbox" name="filter" id="restorefilter" value="1"'.$chkfilters.'>'._('Filter').'</label>';
		echo '<label><input type="checkbox" name="members" id="restoremembers" value="1"'.$chkmembers.'>'._('Members').'</label>';
		echo '<label><input type="checkbox" name="notes" id="restorenotes" value="1"'.$chknotes.'>'._('Notes').'</label>';
		echo '</td><td id="restoresubmit">'.submit(_('Restore')).'</td></tr></table>';
		echo '</form></td></tr>';
	}
	thr();
	echo '<tr><td>'.form('setup').submit(_('Go to the Setup-Page'), 'class="backbutton"')."</form></tr></td>";
	echo '</table>';
	print_end();
}

function send_destroy_chat(): void
{
	print_start('destroy_chat');
	echo '<table><tr><td colspan="2">'._('Are you sure?').'</td></tr><tr><td>';
	echo form_target('_parent', 'setup', 'destroy').hidden('confirm', 'yes').submit(_('Yes'), 'class="delbutton"').'</form></td><td>';
	echo form('setup').submit(_('No'), 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}

function send_delete_account(): void
{
	print_start('delete_account');
	echo '<table><tr><td colspan="2">'._('Are you sure?').'</td></tr><tr><td>';
	echo form('profile', 'delete').hidden('confirm', 'yes').submit(_('Yes'), 'class="delbutton"').'</form></td><td>';
	echo form('profile').submit(_('No'), 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}
function send_votes(): void {
    // Check session and get user data
    check_session();
    global $U, $db;

    // Only allow access for users with status >= 3
    if (!isset($U['status']) || $U['status'] < 3) {
        send_access_denied();
        return;
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

    print_start('votes');
    echo '<h2>'._('Voting System').'</h2>';

    // Create vote form for admins
    if ($U['status'] >= 5) {
        echo '<div class="create-vote">';
        echo '<h3>'._('Create New Vote').'</h3>';
        echo form('notes', 'votes');
        echo '<input type="hidden" name="action" value="create">';
        echo '<p><input type="text" name="title" placeholder="'._('Vote Title').'" required></p>';
        echo '<p><textarea name="description" placeholder="'._('Vote Description').'" required></textarea></p>';
        echo '<p><select name="min_status" required>';
        echo '<option value="1">'._('Guest').'</option>';
        echo '<option value="2">'._('Super Guest').'</option>'; 
        echo '<option value="3">'._('Registered').'</option>';
        echo '<option value="4">'._('Moderator').'</option>';
        echo '<option value="5">'._('Admin').'</option>';
        echo '</select></p>';
        echo '<p>'.submit(_('Create Vote')).'</p>';
        echo '</form></div>';
    }

    // Display votes
    foreach ($votes as $vote) {
        if ($U['status'] >= $vote['min_status']) {
            echo '<div class="vote">';
            
            if ($edit_id == $vote['id'] && $U['status'] >= 5) {
                echo form('notes', 'votes');
                echo '<input type="hidden" name="action" value="edit">';
                echo '<input type="hidden" name="vote_id" value="'.$vote['id'].'">';
                echo '<p><input type="text" name="title" value="'.htmlspecialchars($vote['title']).'" required></p>';
                echo '<p><textarea name="description" required>'.htmlspecialchars($vote['description']).'</textarea></p>';
                echo '<p><select name="min_status" required>';
                for ($i = 1; $i <= 5; $i++) {
                    echo '<option value="'.$i.'"'.($vote['min_status'] == $i ? ' selected' : '').'>'.$i.'</option>';
                }
                echo '</select></p>';
                echo '<p>'.submit(_('Save Changes')).'</p>';
                echo '</form>';
            } else {
                echo '<h3>'.htmlspecialchars($vote['title']).'</h3>';
                echo '<p>'.nl2br(htmlspecialchars($vote['description'])).'</p>';
                echo '<p>'._('Yes').': '.$vote['yes_votes'].' '._('No').': '.$vote['no_votes'].'</p>';
                echo '<p>'._('Created by').': '.htmlspecialchars($vote['created_by']).'</p>';
                
                if (!$vote['has_voted']) {
                    echo form('notes', 'votes');
                    echo '<input type="hidden" name="action" value="vote">';
                    echo '<input type="hidden" name="vote_id" value="'.$vote['id'].'">';
                    echo '<input type="hidden" name="choice" value="1">';
                    echo submit(_('Vote Yes'));
                    echo '</form>';

                    echo form('notes', 'votes');
                    echo '<input type="hidden" name="action" value="vote">';
                    echo '<input type="hidden" name="vote_id" value="'.$vote['id'].'">';
                    echo '<input type="hidden" name="choice" value="0">';
                    echo submit(_('Vote No'));
                    echo '</form>';
                } else {
                    echo '<p>'._('You have already voted').'</p>';
                }

                if ($U['status'] >= 5) {
                    echo form('notes', 'votes');
                    echo '<input type="hidden" name="action" value="delete">';
                    echo '<input type="hidden" name="vote_id" value="'.$vote['id'].'">';
                    echo submit(_('Delete'), 'class="delete"');
                    echo '</form>';
                    
                    echo form('notes', 'votes');
                    echo '<input type="hidden" name="edit" value="'.$vote['id'].'">';
                    echo submit(_('Edit'));
                    echo '</form>';
                }
            }
            echo '</div>';
        }
    }

    print_end();
}

function send_init(): void
{
	print_start('init');
	echo '<h2>'._('Initial Setup').'</h2>';
	echo form('init').'<table><tr><td><h3>'._('Superadmin Login').'</h3><table>';
	echo '<tr><td>'._('Superadmin Nickname:').'</td><td><input type="text" name="sunick" size="15" autocomplete="username"></td></tr>';
	echo '<tr><td>'._('Superadmin Password:').'</td><td><input type="password" name="supass" size="15" autocomplete="new-password"></td></tr>';
	echo '<tr><td>'._('Confirm Password:').'</td><td><input type="password" name="supassc" size="15" autocomplete="new-password"></td></tr>';
	echo '</table></td></tr><tr><td><br>'.submit(_('Initialise Chat')).'</td></tr></table></form>';
	echo '<p id="changelang">'._('Change language:');
	foreach(LANGUAGES as $lang=>$data){
		echo " <a href=\"$_SERVER[SCRIPT_NAME]?action=setup&amp;lang=$lang\">$data[name]</a>";
	}
	echo '</p>'.credit();
	print_end();
}

function send_update(string $msg): void
{
	print_start('update');
	echo '<h2>'._('Database successfully updated!',).'</h2><br>'.form('setup').submit(_('Go to the Setup-Page'))."</form>$msg<br>".credit();
	print_end();
}

function send_alogin(): void
{
	print_start('alogin');
	echo form('setup').'<table>';
	echo '<tr><td>'._('Nickname:').'</td><td><input type="text" name="nick" size="15" autocomplete="username" autofocus></td></tr>';
	echo '<tr><td>'._('Password:').'</td><td><input type="password" name="pass" size="15" autocomplete="current-password"></td></tr>';
	send_captcha();
	echo '<tr><td colspan="2">'.submit(_('Login')).'</td></tr></table></form>';
	echo '<br><a href="?action=sa_password_reset">'._('Forgot login?').'</a><br>';
	echo '<p id="changelang">'._('Change language = > ');
	foreach(LANGUAGES as $lang=>$data){
		echo " <a href=\"?action=setup&amp;lang=$lang\" hreflang=\"$lang\">$data[name]</a>";
	}
	echo '</p>'.credit();
	print_end();
}

function send_sa_password_reset(): void
{
	global $db;
	print_start('sa_password_reset');
	echo '<h1>'._('Reset password').'</h1>';
	if(defined('RESET_SUPERADMIN_PASSWORD') && !empty(RESET_SUPERADMIN_PASSWORD)){
		$stmt = $db->query('SELECT nickname FROM ' . PREFIX . 'members WHERE status = 8 LIMIT 1;');
		if($user = $stmt->fetch(PDO::FETCH_ASSOC)){
			$mem_update = $db->prepare('UPDATE ' . PREFIX . 'members SET passhash = ? WHERE nickname = ? LIMIT 1;');
			$mem_update->execute([password_hash(RESET_SUPERADMIN_PASSWORD, PASSWORD_DEFAULT), $user['nickname']]);
			$sess_delete = $db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE nickname = ?;');
			$sess_delete->execute([$user['nickname']]);
			printf('<p>'._('Successfully reset password for username %s. Please remove the password reset define from the script again.').'</p>', $user['nickname']);
		}
	} else {
		echo '<p>'._("Please modify the script and put the following at the bottom of it (change the password). Then refresh this page: define('RESET_SUPERADMIN_PASSWORD', 'changeme');").'</p>';
	}
	echo '<a href="?action=setup">'._('Go to the Setup-Page').'</a>';
	echo '<p id="changelang">'._('Change language:');
	foreach(LANGUAGES as $lang=>$data){
		echo " <a href=\"?action=sa_password_reset&amp;lang=$lang\" hreflang=\"$lang\">$data[name]</a>";
	}
	echo '</p>'.credit();
	print_end();
}


function send_admin(string $arg): void
{
	global $U, $db;
	$ga=(int) get_setting('guestaccess');
	print_start('admin');
	$chlist='<select name="name[]" size="5" multiple><option value="">'._('(choose)').'</option>';
	$chlist.='<option value="s &#42;">'._('All guests').'</option>';
	$users=[];
	$stmt=$db->query('SELECT nickname, style, status FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 ORDER BY LOWER(nickname);');
	while($user=$stmt->fetch(PDO::FETCH_NUM)){
		$users[]=[htmlspecialchars($user[0]), $user[1], $user[2]];
	}
	foreach($users as $user){
		if($user[2]<$U['status']){
			$chlist.="<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
		}
	}
	$chlist.='</select>';
	echo '<div class="admin-panel">';
	echo '<h2>'._('Administrative functions')."</h2><i>$arg</i><table>";
	if($U['status']>=7){
		thr();
		echo '<tr><td>'.form_target('view', 'setup').submit(_('Go to the Setup-Page')).'</form></td></tr>';
	}
	thr();
	echo '<tr><td><div class="admin-section"><h2>'._('Clean messages').'</h2>';
	echo form('admin', 'clean');
	echo '<table><tr><td><label><input type="radio" name="what" id="room" value="room">';
	echo _('Whole room').'</label></td><td>&nbsp;</td><td><label><input type="radio" name="what" id="choose" value="choose" checked>';
	echo _('Selection').'</label></td><td>&nbsp;</td></tr><tr><td colspan="3"><label><input type="radio" name="what" id="nick" value="nick">';
	echo _('Following nickname:').'</label> <select name="nickname" size="1"><option value="">'._('(choose)').'</option>';
	$stmt=$db->prepare('SELECT DISTINCT poster FROM ' . PREFIX . "messages WHERE delstatus<? AND poster!='';");
	$stmt->execute([$U['status']]);
	while($nick=$stmt->fetch(PDO::FETCH_NUM)){
		echo '<option value="'.htmlspecialchars($nick[0]).'">'.htmlspecialchars($nick[0]).'</option>';
	}
	echo '</select></td><td>';
	echo submit(_('Clean'), 'class="delbutton"').'</td></tr></table></form></div></td></tr>';
	thr();
	echo '<tr><td><div class="admin-section"><h2>'.sprintf(_('Kick Chatter (%d minutes)'), get_setting('kickpenalty')).'</h2>';
	echo form('admin', 'kick');
	echo '<table><tr><td>'._('Kickmessage:').'</td><td><input type="text" name="kickmessage" size="30"></td><td>&nbsp;</td></tr>';
	echo '<tr><td><label><input type="checkbox" name="what" value="purge" id="purge">'._('Purge messages').'</label></td><td>'.$chlist.'</td><td>';
	echo submit(_('Kick')).'</td></tr></table></form></div></td></tr>';
	thr();
	echo '<tr><td><div class="admin-section"><h2>'._('Logout inactive Chatter').'</h2>';
	echo form('admin', 'logout');
	echo "<table><tr><td>$chlist</td><td>";
	echo submit(_('Logout')).'</td></tr></table></form></div></td></tr>';
	$views=['sessions' => _('View active sessions'), 'filter' => _('Filter'), 'linkfilter' => _('Linkfilter')];
	foreach($views as $view => $title){
		thr();
		echo "<tr><td><div class=\"admin-section\"><h2>".$title.'</h2>';
		echo form('admin', $view);
		echo submit(_('View')).'</form></div></td></tr>';
	}
	thr();
	echo '<tr><td><div class="admin-section"><h2>' . _('Link Panel') . '</h2>';
	echo form_target('_blank', 'viewlinks');
	echo submit(_('Open Link Panel'), 'class="admin-button"') . '</form></div></td></tr>';
	thr();
	echo '<tr><td><div class="admin-section"><h2>'._('Topic').'</h2>';
	echo form('admin', 'topic');
	echo '<table><tr><td><input type="text" name="topic" size="20" value="'.get_setting('topic').'"></td><td>';
	echo submit(_('Change')).'</td></tr></table></form></div></td></tr>';
	thr();
	echo '<tr><td><div class="admin-section"><h2>'._('Change Guestaccess').'</h2>';
	echo form('admin', 'guestaccess');
	echo '<table>';
	echo '<tr><td><select name="guestaccess">';
	echo '<option value="1"';
	if($ga===1){
		echo ' selected';
	}
	echo '>'._('Allow').'</option>';
	echo '<option value="2"';
	if($ga===2){
		echo ' selected';
	}
	echo '>'._('Allow with waitingroom').'</option>';
	echo '<option value="3"';
	if($ga===3){
		echo ' selected';
	}
	echo '>'._('Require moderator approval').'</option>';
	echo '<option value="0"';
	if($ga===0){
		echo ' selected';
	}
	echo '>'._('Only members').'</option>';
	if($ga===4){
		echo '<option value="4" selected';
		echo '>'._('Disable chat').'</option>';
	}
	echo '</select></td><td>'.submit(_('Change')).'</td></tr></table></form></div></td></tr>';
	thr();
	if(get_setting('suguests')){
		echo '<tr><td><div class="admin-section"><h2>'._('Register applicant').'</h2>';
		echo form('admin', 'superguest');
		echo '<table><tr><td><select name="name" size="1"><option value="">'._('(choose)').'</option>';
		foreach($users as $user){
			if($user[2]==1){
				echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
		echo '</select></td><td>'.submit(_('Register')).'</td></tr></table></form></div></td></tr>';
		thr();
	}
	if($U['status']>=7){
		echo '<tr><td><div class="admin-section"><h2>'._('Members').'</h2>';
		echo form('admin', 'status');
		echo '<table><tr><td><select name="name" size="1"><option value="">'._('(choose)').'</option>';
		$members=[];
		$result=$db->query('SELECT nickname, style, status FROM ' . PREFIX . 'members ORDER BY LOWER(nickname);');
		while($temp=$result->fetch(PDO::FETCH_NUM)){
			$members[]=[htmlspecialchars($temp[0]), $temp[1], $temp[2]];
		}
		foreach($members as $member){
			echo "<option value=\"$member[0]\" style=\"$member[1]\">$member[0]";
			if($member[2]==0){
				echo ' (!)';
			}elseif($member[2]==2){
				echo ' (SG)';
			}elseif($member[2]==3){
			}elseif($member[2]==5){
				echo ' (M)';
			}elseif($member[2]==6){
				echo ' (SM)';
			}elseif($member[2]==7){
				echo ' (A)';
			}else{
				echo ' (SA)';
			}
			echo '</option>';
		}
		echo '</select><select name="set" size="1"><option value="">'._('(choose)').'</option><option value="-">'._('Delete from database').'</option><option value="0">'._('Deny access (!)').'</option>';
		if(get_setting('suguests')){
			echo '<option value="2">'._('Set to applicant (SG)').'</option>';
		}
		echo '<option value="3">'._('Set to regular member').'</option>';
		echo '<option value="5">'._('Set to moderator (M)').'</option>';
		echo '<option value="6">'._('Set to supermod (SM)').'</option>';
		if($U['status']>=8){
			echo '<option value="7">'._('Set to admin (A)').'</option>';
		}
		echo '</select></td><td>'.submit(_('Change')).'</td></tr></table></form></div></td></tr>';
		thr();
		echo '<tr><td><div class="admin-section"><h2>'._('Reset password').'</h2>';
		echo form('admin', 'passreset');
		echo '<table><tr><td><select name="name" size="1"><option value="">'._('(choose)').'</option>';
		foreach($members as $member){
			echo "<option value=\"$member[0]\" style=\"$member[1]\">$member[0]</option>";
		}
		echo '</select></td><td><input type="password" name="pass" autocomplete="off"></td><td>'.submit(_('Change')).'</td></tr></table></form></div></td></tr>';
		thr();
		echo '<tr><td><div class="admin-section"><h2>'._('Register Guest').'</h2>';
		echo form('admin', 'register');
		echo '<table><tr><td><select name="name" size="1"><option value="">'._('(choose)').'</option>';
		foreach($users as $user){
			if($user[2]==1){
				echo "<option value=\"$user[0]\" style=\"$user[1]\">$user[0]</option>";
			}
		}
		echo '</select></td><td>'.submit(_('Register')).'</td></tr></table></form></div></td></tr>';
		thr();
		echo '<tr><td><div class="admin-section"><h2>'._('Register new Member').'</h2>';
		echo form('admin', 'regnew');
		echo '<table><tr><td>'._('Nickname:').'</td><td>&nbsp;</td><td><input type="text" name="name" size="20"></td><td>&nbsp;</td></tr>';
		echo '<tr><td>'._('Password:').'</td><td>&nbsp;</td><td><input type="password" name="pass" size="20" autocomplete="off"></td><td>';
		echo submit(_('Register')).'</td></tr></table></form></div></td></tr>';
		thr();
	}
	// Handle bad words management
	// Create bad_words table if not exists
	$db->exec('CREATE TABLE IF NOT EXISTS ' . PREFIX . 'bad_words (
		id INT AUTO_INCREMENT PRIMARY KEY,
		word VARCHAR(255) NOT NULL UNIQUE
	)');

	// Display bad words management form
	echo '<tr><td><div class="admin-section"><h2>'._('Manage Bad Name').'</h2>';
	
	// Add new word form
	echo form('admin', 'add_word');
	echo '<table><tr>';
	echo '<td><input type="text" name="new_word" placeholder="'._('Add new bad word').'" maxlength="255" required /></td>';
	echo '<td><input type="submit" value="'._('Add').'" class="btn btn-primary" /></td>';
	echo '</tr></table>';
	echo '</form>';

	// Display status message if exists
	if (isset($_SESSION['bad_word_status'])) {
		echo '<div class="status-message">';
		echo htmlspecialchars($_SESSION['bad_word_status']);
		echo '</div>';
		unset($_SESSION['bad_word_status']);
	}

	// Table header for bad words list  
	echo '<table class="badwords-table">';
	echo '<tr>';
	echo '<th>'._('No').'</th>';
	echo '<th>'._('Bad Name').'</th>';
	echo '<th>'._('Action').'</th>';
	echo '</tr>';

	try {
		// Display existing bad words in table
		$stmt = $db->query('SELECT id, word FROM ' . PREFIX . 'bad_words ORDER BY word ASC');
		$number = 1;
		while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
			echo '<tr>';
			echo '<td>' . $number . '</td>';
			echo '<td>' . htmlspecialchars($row['word']) . '</td>';
			echo '<td>';
			echo form('admin', 'delete_word');
			echo '<input type="hidden" name="word_id" value="' . $row['id'] . '"/>';
			echo '<input type="submit" value="'._('Delete').'" class="btn btn-danger" onclick="return confirm(\''._('Are you sure you want to delete this word?').'\')"/>';
			echo '</form>';
			echo '</td>';
			echo '</tr>';
			$number++;
		}
	} catch (PDOException $e) {
		error_log("Error fetching bad words: " . $e->getMessage());
		echo '<tr><td colspan="3">'._('Error loading bad words list').'</td></tr>';
	}
	
	echo '</table></div></td></tr>';
	thr();
	echo "</table><br>";
	echo form('admin').submit(_('Reload')).'</form>';
	echo '</div>';
	print_end();
}
function send_sessions(): void
{
	global $U, $db;
	$stmt=$db->prepare('SELECT nickname, style, lastpost, status, useragent, ip FROM ' . PREFIX . 'sessions WHERE entry!=0 AND (incognito=0 OR status<? OR nickname=?) ORDER BY status DESC, lastpost DESC;');
	$stmt->execute([$U['status'], $U['nickname']]);
	if(!$lines=$stmt->fetchAll(PDO::FETCH_ASSOC)){
		$lines=[];
	}
	print_start('sessions');
	echo '<h1>'._('Active Sessions').'</h1><table>';
	echo '<tr><th>'._('Nickname').'</th><th>'._('Timeout in').'</th><th>'._('User-Agent').'</th>';
	$trackip=(bool) get_setting('trackip');
	$memexpire=(int) get_setting('memberexpire');
	$guestexpire=(int) get_setting('guestexpire');
	if($trackip) echo '<th>'._('IP-Address').'</th>';
	echo '<th>'._('Actions').'</th></tr>';
	foreach($lines as $temp){
		if($temp['status']==0){
			$s=' (K)';
		}elseif($temp['status']<=1){
			$s=' (G)';
		}elseif($temp['status']==2){
			$s=' (SG)';
		}elseif($temp['status']==3){
			$s='';
		}elseif($temp['status']==5){
			$s=' (M)';
		}elseif($temp['status']==6){
			$s=' (SM)';
		}elseif($temp['status']==7){
			$s=' (A)';
		}else{
			$s=' (SA)';
		}
		echo '<tr><td class="nickname">'.style_this(htmlspecialchars($temp['nickname']).$s, $temp['style']).'</td><td class="timeout">';
		if($temp['status']>2){
			get_timeout((int) $temp['lastpost'], $memexpire);
		}else{
			get_timeout((int) $temp['lastpost'], $guestexpire);
		}
		echo '</td>';
		if($U['status']>$temp['status'] || $U['nickname']===$temp['nickname']){
			echo "<td class=\"ua\">$temp[useragent]</td>";
			if($trackip){
				echo "<td class=\"ip\">$temp[ip]</td>";
			}
			echo '<td class="action">';
			if($temp['nickname']!==$U['nickname']){
				echo '<table><tr>';
				if($temp['status']!=0){
					echo '<td>';
					echo form('admin', 'sessions');
					echo hidden('kick', '1').hidden('nick', htmlspecialchars($temp['nickname'])).submit(_('Kick')).'</form>';
					echo '</td>';
				}
				echo '<td>';
				echo form('admin', 'sessions');
				echo hidden('logout', '1').hidden('nick', htmlspecialchars($temp['nickname'])).submit($temp['status']==0 ? _('Unban') : _('Logout')).'</form>';
				echo '</td></tr></table>';
			}else{
				echo '-';
			}
			echo '</td></tr>';
		}else{
			echo '<td class="ua">-</td>';
			if($trackip){
				echo '<td class="ip">-</td>';
			}
			echo '<td class="action">-</td></tr>';
		}
	}
	echo "</table><br>";
	echo form('admin', 'sessions').submit(_('Reload')).'</form>';
	print_end();
}
function check_filter_match(int &$reg) : string {
	$_POST['match']=htmlspecialchars($_POST['match']);
	if(isset($_POST['regex']) && $_POST['regex']==1){
		if(!valid_regex($_POST['match'])){
			return _('Incorrect regular expression!').'<br>'.sprintf(_('Your match was as follows: %s'), htmlspecialchars($_POST['match']));
		}
		$reg=1;
	}else{
		$_POST['match']=preg_replace('/([^\w\d])/u', "\\\\$1", $_POST['match']);
		$reg=0;
	}
	if(mb_strlen($_POST['match'])>255){
		return _('Your match was too long. You can use max. 255 characters. Try splitting it up.')."<br>".sprintf(_('Your match was as follows: %s'), htmlspecialchars($_POST['match']));
	}
	return '';
}

function manage_filter() : string {
	global $db, $memcached;
	if(isset($_POST['id'])){
		$reg=0;
		if(($tmp=check_filter_match($reg)) !== ''){
			return $tmp;
		}
		if(isset($_POST['allowinpm']) && $_POST['allowinpm']==1){
			$pm=1;
		}else{
			$pm=0;
		}
		if(isset($_POST['kick']) && $_POST['kick']==1){
			$kick=1;
		}else{
			$kick=0;
		}
		if(isset($_POST['cs']) && $_POST['cs']==1){
			$cs=1;
		}else{
			$cs=0;
		}
		if(preg_match('/^[0-9]+$/', $_POST['id'])){
			if(empty($_POST['match'])){
				$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'filter WHERE id=?;');
				$stmt->execute([$_POST['id']]);
			}else{
				$stmt=$db->prepare('UPDATE ' . PREFIX . 'filter SET filtermatch=?, filterreplace=?, allowinpm=?, regex=?, kick=?, cs=? WHERE id=?;');
				$stmt->execute([$_POST['match'], $_POST['replace'], $pm, $reg, $kick, $cs, $_POST['id']]);
			}
		}elseif($_POST['id']==='+'){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'filter (filtermatch, filterreplace, allowinpm, regex, kick, cs) VALUES (?, ?, ?, ?, ?, ?);');
			$stmt->execute([$_POST['match'], $_POST['replace'], $pm, $reg, $kick, $cs]);
		}
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
		}
	}
	return '';
}

function manage_linkfilter() : string {
	global $db, $memcached;
	if(isset($_POST['id'])){
		$reg=0;
		if(($tmp=check_filter_match($reg)) !== ''){
			return $tmp;
		}
		if(preg_match('/^[0-9]+$/', $_POST['id'])){
			if(empty($_POST['match'])){
				$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'linkfilter WHERE id=?;');
				$stmt->execute([$_POST['id']]);
			}else{
				$stmt=$db->prepare('UPDATE ' . PREFIX . 'linkfilter SET filtermatch=?, filterreplace=?, regex=? WHERE id=?;');
				$stmt->execute([$_POST['match'], $_POST['replace'], $reg, $_POST['id']]);
			}
		}elseif($_POST['id']==='+'){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'linkfilter (filtermatch, filterreplace, regex) VALUES (?, ?, ?);');
			$stmt->execute([$_POST['match'], $_POST['replace'], $reg]);
		}
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
		}
	}
	return '';
}

function get_filters() : array {
	global $db, $memcached;
	$filters=[];
	if(MEMCACHED){
		$filters=$memcached->get(DBNAME . '-' . PREFIX . 'filter');
	}
	if(!MEMCACHED || $memcached->getResultCode()!==Memcached::RES_SUCCESS){
		$filters=[];
		$result=$db->query('SELECT id, filtermatch, filterreplace, allowinpm, regex, kick, cs FROM ' . PREFIX . 'filter;');
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=['id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'allowinpm'=>$filter['allowinpm'], 'regex'=>$filter['regex'], 'kick'=>$filter['kick'], 'cs'=>$filter['cs']];
		}
		if(MEMCACHED){
			$memcached->set(DBNAME . '-' . PREFIX . 'filter', $filters);
		}
	}
	return $filters;
}

function get_linkfilters() : array {
	global $db, $memcached;
	$filters=[];
	if(MEMCACHED){
		$filters=$memcached->get(DBNAME . '-' . PREFIX . 'linkfilter');
	}
	if(!MEMCACHED || $memcached->getResultCode()!==Memcached::RES_SUCCESS){
		$filters=[];
		$result=$db->query('SELECT id, filtermatch, filterreplace, regex FROM ' . PREFIX . 'linkfilter;');
		while($filter=$result->fetch(PDO::FETCH_ASSOC)){
			$filters[]=['id'=>$filter['id'], 'match'=>$filter['filtermatch'], 'replace'=>$filter['filterreplace'], 'regex'=>$filter['regex']];
		}
		if(MEMCACHED){
			$memcached->set(DBNAME . '-' . PREFIX . 'linkfilter', $filters);
		}
	}
	return $filters;
}

function send_filter(string $arg=''): void
{
	global $U;
	print_start('filter');
	echo '<h2>'._('Filter')."</h2><i>$arg</i><table>";
	thr();
	echo '<tr><th><table><tr>';
	echo '<td>'._('Filter ID:').'</td>';
	echo '<td>'._('Match').'</td>';
	echo '<td>'._('Replace').'</td>';
	echo '<td>'._('Allow in PM').'</td>';
	echo '<td>'._('Regex').'</td>';
	echo '<td>'._('Kick').'</td>';
	echo '<td>'._('Case sensitive').'</td>';
	echo '<td>'._('Apply').'</td>';
	echo '</tr></table></th></tr>';
	$filters=get_filters();
	foreach($filters as $filter){
		if($filter['allowinpm']==1){
			$check=' checked';
		}else{
			$check='';
		}
		if($filter['regex']==1){
			$checked=' checked';
		}else{
			$checked='';
			$filter['match']=preg_replace('/(\\\\(.))/u', "$2", $filter['match']);
		}
		if($filter['kick']==1){
			$checkedk=' checked';
		}else{
			$checkedk='';
		}
		if($filter['cs']==1){
			$checkedcs=' checked';
		}else{
			$checkedcs='';
		}
		echo '<tr><td>';
		echo form('admin', 'filter').hidden('id', $filter['id']);
		echo '<table><tr><td>'._('Filter')." $filter[id]:</td>";
		echo '<td><input type="text" name="match" value="'.$filter['match'].'" size="20" style="'.$U['style'].'"></td>';
		echo '<td><input type="text" name="replace" value="'.htmlspecialchars($filter['replace']).'" size="20" style="'.$U['style'].'"></td>';
		echo '<td><label><input type="checkbox" name="allowinpm" value="1"'.$check.'>'._('Allow in PM').'</label></td>';
		echo '<td><label><input type="checkbox" name="regex" value="1"'.$checked.'>'._('Regex').'</label></td>';
		echo '<td><label><input type="checkbox" name="kick" value="1"'.$checkedk.'>'._('Kick').'</label></td>';
		echo '<td><label><input type="checkbox" name="cs" value="1"'.$checkedcs.'>'._('Case sensitive').'</label></td>';
		echo '<td class="filtersubmit">'.submit(_('Change')).'</td></tr></table></form></td></tr>';
	}
	echo '<tr><td>';
	echo form('admin', 'filter').hidden('id', '+');
	echo '<table><tr><td>'._('New filter:').'</td>';
	echo '<td><input type="text" name="match" value="" size="20" style="'.$U['style'].'"></td>';
	echo '<td><input type="text" name="replace" value="" size="20" style="'.$U['style'].'"></td>';
	echo '<td><label><input type="checkbox" name="allowinpm" id="allowinpm" value="1">'._('Allow in PM').'</label></td>';
	echo '<td><label><input type="checkbox" name="regex" id="regex" value="1">'._('Regex').'</label></td>';
	echo '<td><label><input type="checkbox" name="kick" id="kick" value="1">'._('Kick').'</label></td>';
	echo '<td><label><input type="checkbox" name="cs" id="cs" value="1">'._('Case sensitive').'</label></td>';
	echo '<td class="filtersubmit">'.submit(_('Add')).'</td></tr></table></form></td></tr>';
	echo '</table><br>';
	echo form('admin', 'filter').submit(_('Reload')).'</form>';
	print_end();
}

function send_linkfilter(string $arg=''): void
{
	global $U;
	print_start('linkfilter');
	echo '<h2>'._('Linkfilter')."</h2><i>$arg</i><table>";
	thr();
	echo '<tr><th><table><tr>';
	echo '<td>'._('Filter ID:').'</td>';
	echo '<td>'._('Match').'</td>';
	echo '<td>'._('Replace').'</td>';
	echo '<td>'._('Regex').'</td>';
	echo '<td>'._('Apply').'</td>';
	echo '</tr></table></th></tr>';
	$filters=get_linkfilters();
	foreach($filters as $filter){
		if($filter['regex']==1){
			$checked=' checked';
		}else{
			$checked='';
			$filter['match']=preg_replace('/(\\\\(.))/u', "$2", $filter['match']);
		}
		echo '<tr><td>';
		echo form('admin', 'linkfilter').hidden('id', $filter['id']);
		echo '<table><tr><td>'._('Filter')." $filter[id]:</td>";
		echo '<td><input type="text" name="match" value="'.$filter['match'].'" size="20" style="'.$U['style'].'"></td>';
		echo '<td><input type="text" name="replace" value="'.htmlspecialchars($filter['replace']).'" size="20" style="'.$U['style'].'"></td>';
		echo '<td><label><input type="checkbox" name="regex" value="1"'.$checked.'>'._('Regex').'</label></td>';
		echo '<td class="filtersubmit">'.submit(_('Change')).'</td></tr></table></form></td></tr>';
	}
	echo '<tr><td>';
	echo form('admin', 'linkfilter').hidden('id', '+');
	echo '<table><tr><td>'._('New filter:').'</td>';
	echo '<td><input type="text" name="match" value="" size="20" style="'.$U['style'].'"></td>';
	echo '<td><input type="text" name="replace" value="" size="20" style="'.$U['style'].'"></td>';
	echo '<td><label><input type="checkbox" name="regex" value="1">'._('Regex').'</label></td>';
	echo '<td class="filtersubmit">'.submit(_('Add')).'</td></tr></table></form></td></tr>';
	echo '</table><br>';
	echo form('admin', 'linkfilter').submit(_('Reload')).'</form>';
	print_end();
}
function send_frameset(): void
{
	global $U, $db, $language, $dir;
	prepare_stylesheets('frameset');
	send_headers();
	echo '<!DOCTYPE html><html lang="'.$language.'" dir="'.$dir.'"><head>'.meta_html();
	echo '<title>'.get_setting('chatname').'</title>';
	echo '<link rel="icon" href="./icon.svg" type="image/svg+xml">';

	print_stylesheet('frameset');
    echo "<link rel=\"preconnect\" href=\"https://fonts.googleapis.com\">";
    echo "<link rel=\"preconnect\" href=\"https://fonts.gstatic.com\" crossorigin>";
	echo '</head><body>';
	
	// Add navbar
	echo "<div id=\"navbar\">";
	echo "<a href=\"navbar/link.php?session=$U[session]&lang=$language\" class=\"cyber-link\" target=\"_blank\">LINK</a>";
	echo "<a href=\"navbar/ctf.php?session=$U[session]&lang=$language\" class=\"cyber-link\" target=\"_blank\">CTF</a>"; 
	echo "<a href=\"navbar/command.php?session=$U[session]&lang=$language\" class=\"cyber-link\" target=\"_blank\">COMMAND</a>";
	echo "<a href=\"navbar/changelog.php?session=$U[session]&lang=$language\" class=\"cyber-link\" target=\"_blank\">CHANGELOG</a>";
	if ($U['status'] >= 5) {
		echo  form('view_vote').submit(_('Votes'));	
	}
	echo "</div>";

	if(isset($_POST['sort'])){
		if($_POST['sort']==1){
			$U['sortupdown']=1;
		}else{
			$U['sortupdown']=0;
		}
		$tmp=$U['nocache'];
		$U['nocache']=$U['nocache_old'];
		$U['nocache_old']=$tmp;
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET sortupdown=?, nocache=?, nocache_old=? WHERE nickname=?;');
		$stmt->execute([$U['sortupdown'], $U['nocache'], $U['nocache_old'], $U['nickname']]);
		if($U['status']>1){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET sortupdown=?, nocache=?, nocache_old=? WHERE nickname=?;');
			$stmt->execute([$U['sortupdown'], $U['nocache'], $U['nocache_old'], $U['nickname']]);
		}
	}
	$bottom='';
	if(get_setting('enablegreeting')){
		$action_mid='greeting';
	} else {
		if($U['sortupdown']){
			$bottom='#bottom';
		}
		$action_mid='view';
	}
	if((!isset($_REQUEST['sort']) && !$U['sortupdown']) || (isset($_REQUEST['sort']) && $_REQUEST['sort']==0)){
		$action_top='post';
		$action_bot='controls';
		$sort_bot='&sort=1';
	}else{
		$action_top='controls';
		$action_bot='post';
		$sort_bot='';
	}
	
	// Build common URL parameters
	$base_url = "$_SERVER[SCRIPT_NAME]?session=$U[session]&lang=$language";
	
	// Add background frame div that appears behind all frames
	echo '<div id="frame-background" class="cyber-background">';
	echo '<div class="matrix-effect"></div>';
	echo '</div>';
	echo '<div id="frameset-mid">';
	echo '<iframe   name="view" src="' . $base_url . '&action=' . $action_mid . $bottom . '">';
	echo noframe_html();
	echo '</iframe></div>';
	
	echo '<div id="frameset-top">';
	echo '<iframe name="' . $action_top . '" src="' . $base_url . '&action=' . $action_top . '">';
	echo noframe_html(); 
	echo '</iframe></div>';
	
	echo '<div id="frameset-bot">';
	echo '<iframe name="' . $action_bot . '" src="' . $base_url . '&action=' . $action_bot . $sort_bot . '">';
	echo noframe_html();
	echo '</iframe></div>';
	
	echo '</body></html>';
	exit;
}

function noframe_html() : string {
	return _('This chat uses <b>frames</b>. Please enable frames in your browser or use a suitable one!').form_target('_parent', '').submit(_('Back to the login page.'), 'class="backbutton"').'</form>';
}


function send_messages(): void
{
	global $U, $language;

	$nocache = $U['nocache'] ? '&nc=' . substr(time(), -6) : '';
	$sort = $U['sortupdown'] ? '#bottom' : '';
	
	print_start(
		'messages', 
		(int) $U['refresh'], 
		"{$_SERVER['SCRIPT_NAME']}?action=view&session={$U['session']}&lang=$language$nocache$sort",
true	
	);
	
	echo '<a id="top"></a>';
	echo '<a id="bottom_link" style="text-decoration:none" href="#bottom">' . _('Bottom') . '</a>';
	echo '<div id="manualrefresh"><br>' . _('Manual refresh required') . '<br>';
	echo form('view') . submit(_('Reload')) . '</form><br></div>';
	
	if (!$U['sortupdown']) {
		
		print_chatters();
		print_notifications();
		print_messages();
	} else {
		print_messages();
		print_notifications();
		print_chatters();

	}

	echo '<a id="top_link">' . _('Top') . '</a>';
	print_end();
}

function send_inbox(): void
{
	global $U, $db;
	print_start('inbox');
	echo form('inbox', 'clean').submit(_('Delete selected messages'), 'class="delbutton"').'<br><br>';
	$dateformat=get_setting('dateformat');
	if(!$U['embed'] && get_setting('imgembed')){
		$removeEmbed=true;
	}else{
		$removeEmbed=false;
	}
	if($U['timestamps'] && !empty($dateformat)){
		$timestamps=true;
	}else{
		$timestamps=false;
	}
	if($U['sortupdown']){
		$direction='ASC';
	}else{
		$direction='DESC';
	}
	$stmt=$db->prepare('SELECT id, postdate, text FROM ' . PREFIX . "inbox WHERE recipient=? ORDER BY id $direction;");
	$stmt->execute([$U['nickname']]);
	while($message=$stmt->fetch(PDO::FETCH_ASSOC)){
		prepare_message_print($message, $removeEmbed);
		echo "<div class=\"msg\"><label><input type=\"checkbox\" name=\"mid[]\" value=\"$message[id]\">";
		if($timestamps){
			echo ' <small>'.date($dateformat, $message['postdate']).' - </small>';
		}
		echo " $message[text]</label></div>";
	}
	echo '</form><br>'.form('view').submit(_('Back to the chat.'), 'class="backbutton"').'</form>';
	print_end();
}

function send_notes(int $type): void
{
	global $U, $db;
	print_start('notes');
	$personalnotes=(bool) get_setting('personalnotes');
	$publicnotes=(bool) get_setting('publicnotes');
	if($U['status']>=3 && ($personalnotes || $publicnotes)){
		echo '<table><tr>';
		if($U['status']>6){
			echo '<td>'.form_target('view', 'notes', 'admin').submit(_('Admin notes')).'</form></td>';
		}
		if($U['status']>=5){
			echo '<td>'.form_target('view', 'notes', 'staff').submit(_('Staff notes')).'</form></td>';
		}
		if($personalnotes){
			echo '<td>'.form_target('view', 'notes').submit(_('Personal notes')).'</form></td>';
		}
		if($publicnotes){
			echo '<td>'.form_target('view', 'notes', 'public').submit(_('Public notes')).'</form></td>';
		}
		echo '</tr></table>';
	}
	if($type===1){
		echo '<h2>'._('Staff notes').'</h2><p>';
		$hiddendo=hidden('do', 'staff');
	}elseif($type===0){
		echo '<h2>'._('Admin notes').'</h2><p>';
		$hiddendo=hidden('do', 'admin');
	}elseif($type===2){
		echo '<h2>'._('Personal notes').'</h2><p>';
		$hiddendo='';
	}elseif($type===3){
		echo '<h2>'._('Public notes').'</h2><p>';
		$hiddendo=hidden('do', 'public');
	}
	if(isset($_POST['text'])){
		if(MSGENCRYPTED){
			try {
				$_POST['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($_POST['text'], '', AES_IV, ENCRYPTKEY));
			} catch (SodiumException $e){
				send_error($e->getMessage());
			}
		}
		$time=time();
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'notes (type, lastedited, editedby, text) VALUES (?, ?, ?, ?);');
		$stmt->execute([$type, $time, $U['nickname'], $_POST['text']]);
		echo '<b>'._('Notes saved!').'</b> ';
	}
	$dateformat=get_setting('dateformat');
	if(($type!==2) && ($type !==3)){
		$stmt=$db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'notes WHERE type=?;');
		$stmt->execute([$type]);
	}else{
		$stmt=$db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'notes WHERE type=? AND editedby=?;');
		$stmt->execute([$type, $U['nickname']]);
	}
	$num=$stmt->fetch(PDO::FETCH_NUM);
	if(!empty($_POST['revision'])){
		$revision=intval($_POST['revision']);
	}else{
		$revision=0;
	}
	if(($type!==2) && ($type !==3)){
		$stmt=$db->prepare('SELECT * FROM ' . PREFIX . "notes WHERE type=? ORDER BY id DESC LIMIT 1 OFFSET $revision;");
		$stmt->execute([$type]);
	}else{
		$stmt=$db->prepare('SELECT * FROM ' . PREFIX . "notes WHERE type=? AND editedby=? ORDER BY id DESC LIMIT 1 OFFSET $revision;");
		$stmt->execute([$type, $U['nickname']]);
	}
	if($note=$stmt->fetch(PDO::FETCH_ASSOC)){
		printf(_('Last edited by %1$s at %2$s'), htmlspecialchars($note['editedby']), date($dateformat, $note['lastedited']));
	}else{
		$note['text']='';
	}
	if(MSGENCRYPTED){
		try {
			$note['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($note['text']), null, AES_IV, ENCRYPTKEY);
		} catch (SodiumException $e){
			send_error($e->getMessage());
		}
	}
	echo "</p>".form('notes');
	echo "$hiddendo<textarea name=\"text\">".htmlspecialchars($note['text']).'</textarea><br>';
	echo submit(_('Save notes')).'</form><br>';
	if($num[0]>1){
		echo '<br><table><tr><td>'._('Revisions:').'</td>';
		if($revision<$num[0]-1){
			echo '<td>'.form('notes').hidden('revision', $revision+1);
			echo $hiddendo.submit(_('Older')).'</form></td>';
		}
		if($revision>0){
			echo '<td>'.form('notes').hidden('revision', $revision-1);
			echo $hiddendo.submit(_('Newer')).'</form></td>';
		}
		echo '</tr></table>';
	}
	print_end();
}

function send_approve_waiting(): void
{
	global $db;
	print_start('approve_waiting');
	echo '<h2>'._('Waiting room').'</h2>';
	$result=$db->query('SELECT * FROM ' . PREFIX . 'sessions WHERE entry=0 AND status=1 ORDER BY id LIMIT 100;');
	if($tmp=$result->fetchAll(PDO::FETCH_ASSOC)){
		echo form('admin', 'approve');
		echo '<table>';
		echo '<tr><th>'._('Nickname').'</th><th>'._('User-Agent').'</th></tr>';
		foreach($tmp as $temp){
			echo '<tr>'.hidden('alls[]', htmlspecialchars($temp['nickname']));
			echo '<td><label><input type="checkbox" name="csid[]" value="'.htmlspecialchars($temp['nickname']).'">';
			echo style_this(htmlspecialchars($temp['nickname']), $temp['style']).'</label></td>';
			echo "<td>$temp[useragent]</td></tr>";
		}
		echo '</table><br><table id="action"><tr><td><label><input type="radio" name="what" value="allowchecked" id="allowchecked" checked>'._('Allow checked').'</label></td>';
		echo '<td><label><input type="radio" name="what" value="allowall" id="allowall">'._('Allow all').'</label></td>';
		echo '<td><label><input type="radio" name="what" value="denychecked" id="denychecked">'._('Deny checked').'</label></td>';
		echo '<td><label><input type="radio" name="what" value="denyall" id="denyall">'._('Deny all').'</label></td></tr><tr><td colspan="8">'._('Send message to denied:').' <input type="text" name="kickmessage" size="45"></td>';
		echo '</tr><tr><td colspan="8">'.submit(_('Submit')).'</td></tr></table></form>';
	}else{
		echo _('No more entry requests to approve.').'<br>';
	}
	echo '<br>'.form('view').submit(_('Back to the chat.'), 'class="backbutton"').'</form>';
	print_end();
}

function send_waiting_room(): void
{
	global $U, $db, $language;
	$ga=(int) get_setting('guestaccess');
	if($ga===3 && (get_count_mods()>0 || !get_setting('modfallback'))){
		$wait=false;
	}else{
		$wait=true;
	}
	check_expired();
	check_kicked();
	$timeleft=get_setting('entrywait')-(time()-$U['lastpost']);
	if($wait && ($timeleft<=0 || $ga===1)){
		$U['entry']=$U['lastpost'];
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=lastpost WHERE session=?;');
		$stmt->execute([$U['session']]);
		send_frameset();
	}elseif(!$wait && $U['entry']!=0){
		send_frameset();
	}else{
		$refresh=(int) get_setting('defaultrefresh');
		print_start('waitingroom', $refresh, "$_SERVER[SCRIPT_NAME]?action=wait&session=$U[session]&lang=$language&nc=".substr(time(),-6));
		echo '<h2>'._('Waiting room').'</h2><p>';
		if($wait){
			printf(_('Welcome %1$s, your login has been delayed, you can access the chat in %2$d seconds.'), style_this(htmlspecialchars($U['nickname']), $U['style']), $timeleft);
		}else{
			printf(_('Welcome %1$s, your login has been delayed, you can access the chat as soon, as a moderator lets you in.'), style_this(htmlspecialchars($U['nickname']), $U['style']));
		}
		echo '</p><br><p>';
		printf(_("If this page doesn't refresh every %d seconds, use the button below to reload it manually!"), $refresh);
		echo '</p><br><br>';
		echo '<hr>'.form('wait');
		echo submit(_('Reload')).'</form><br>';
		echo form('logout');
		echo submit(_('Exit Chat'), 'id="exitbutton"').'</form>';
		$rulestxt=get_setting('rulestxt');
		if(!empty($rulestxt)){
			echo '<div id="rules"><h2>'._('Rules')."</h2><b>$rulestxt</b></div>";
		}
		print_end();
	}
}

function send_choose_messages(): void
{
    global $U, $db;
    print_start('choose_messages');
    echo form('admin', 'clean');
    echo hidden('what', 'selected').submit(_('Delete selected messages'), 'class="delbutton"').'<br><br>';
    
    // Ambil pesan dari database
    $stmt = $db->prepare('SELECT id, text FROM ' . PREFIX . 'messages WHERE status=? ORDER BY id DESC;');
    $stmt->execute([(int) $U['status']]);
    
    echo '<br>'.submit(_('Delete selected messages'), 'class="delbutton"')."</form>";
    print_end();
}
function send_del_confirm(): void
{
	print_start('del_confirm');
	echo '<table><tr><td colspan="2">'._('Are you sure?').'</td></tr><tr><td>'.form('delete');
	if(isset($_POST['multi'])){
		echo hidden('multi', 'on');
	}
	if(isset($_POST['sendto'])){
		echo hidden('sendto', $_POST['sendto']);
	}
	echo hidden('confirm', 'yes').hidden('what', $_POST['what']).submit(_('Yes'), 'class="delbutton"').'</form></td><td>'.form('post');
	if(isset($_POST['multi'])){
		echo hidden('multi', 'on');
	}
	if(isset($_POST['sendto'])){
		echo hidden('sendto', $_POST['sendto']);
	}
	echo submit(_('No'), 'class="backbutton"').'</form></td><tr></table>';
	print_end();
}

function send_post(string $rejected = ''): void
{
    global $U, $db;
    print_start('post');
	if(!isset($_REQUEST['sendto'])){
		$_REQUEST['sendto']='';
	}
	echo '<table><tr><td>'.form('post');
	echo hidden('postid', $U['postid']);
	if(isset($_POST['multi'])){
		echo hidden('multi', 'on');
	}
	echo '<table><tr><td><table><tr id="firstline"><td>'.style_this(htmlspecialchars($U['nickname']), $U['style']).'</td><td>:</td>';
	if(isset($_POST['multi'])){
		echo "<td><textarea name=\"message\" rows=\"3\" cols=\"40\" style=\"$U[style]\" autofocus>$rejected</textarea></td>";
	}else{
		echo "<td><input type=\"text\" name=\"message\" value=\"$rejected\" size=\"40\" style=\"$U[style]\" autofocus></td>";
	}
	echo '<td>'.submit(_('Send to')).'</td><td><select name="sendto" size="1">';
	echo '<option ';
	if($_REQUEST['sendto']==='s *'){
		echo 'selected ';
	}
	echo 'value="s *">-'._('All chatters').'-</option>';
	if($U['status']>=3){
		echo '<option ';
		if($_REQUEST['sendto']==='s ?'){
			echo 'selected ';
		}
		echo 'value="s ?">-'._('Members only').'-</option>';
	}
	if($U['status']>=5){
		echo '<option ';
		if($_REQUEST['sendto']==='s %'){
			echo 'selected ';
		}
		echo 'value="s %">-'._('Staff only').'-</option>';
	}
	if($U['status']>=6){
		echo '<option ';
		if($_REQUEST['sendto']==='s _'){
			echo 'selected ';
		}
		echo 'value="s _">-'._('Admin only').'-</option>';
	}
    //
    // Check for private message settings
    $disablepm = (bool)get_setting('disablepm');
    if (!$disablepm) {
        $users = [];
        $stmt = $db->prepare('SELECT * FROM (SELECT nickname, style, exiting, 0 AS offline FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND incognito=0 UNION SELECT nickname, style, 0, 1 AS offline FROM ' . PREFIX . 'members WHERE eninbox!=0 AND eninbox<=? AND nickname NOT IN (SELECT nickname FROM ' . PREFIX . 'sessions WHERE incognito=0)) AS t WHERE nickname NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=? UNION SELECT ignby FROM ' . PREFIX . 'ignored WHERE ign=?) ORDER BY LOWER(nickname);');
        $stmt->execute([$U['status'], $U['nickname'], $U['nickname']]);
        while ($tmp = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $statusLabel = $tmp['offline'] ? _('(offline)') : ($tmp['exiting'] ? _('(logging out)') : '');
            $users[] = ["$tmp[nickname] $statusLabel", $tmp['style'], $tmp['nickname']];
        }
        foreach ($users as $user) {
            if ($U['nickname'] !== $user[2]) {
                echo '<option value="' . htmlspecialchars($user[2]) . '" style="' . $user[1] . '">' . htmlspecialchars($user[0]) . '</option>';
            }
        }
    }
    echo '</select></td>';

    // File upload option
    if (get_setting('enfileupload') > 0 && get_setting('enfileupload') <= $U['status']) {
        if (!$disablepm && ($U['status'] >= 5 || ($U['status'] >= 3 && (get_setting('memkickalways') || (get_count_mods() == 0 && get_setting('memkick')))))) {
            echo '</tr></table><table><tr id="secondline">';
        }
        printf('<td><input type="file" name="file"><small>' . _('Max %d KB') . '</small></td>', get_setting('maxuploadsize'));
    }

    // Kick and purge options
    if (!$disablepm && ($U['status'] >= 5 || ($U['status'] >= 3 && (get_setting('memkickalways') || (get_count_mods() == 0 && get_setting('memkick')))))) {
        echo '<td><label><input type="checkbox" name="kick" id="kick" value="kick">' . _('Kick') . '</label></td>';
        echo '<td><label><input type="checkbox" name="what" id="what" value="purge" checked>' . _('Also purge messages') . '</label></td>';
    }
    echo '</tr></table></td></tr></table></form></td></tr><tr><td><table><tr id="thirdline"><td>' . form('delete');
    if (isset($_POST['multi'])) {
        echo hidden('multi', 'on');
    }
    echo hidden('sendto', htmlspecialchars($_REQUEST['sendto'])) . hidden('what', 'last');
    echo submit(_('Delete last message'), 'class="delbutton"') . '</form></td><td>' . form('delete');
    if (isset($_POST['multi'])) {
        echo hidden('multi', 'on');
    }
    echo hidden('sendto', htmlspecialchars($_REQUEST['sendto'])) . hidden('what', 'all');
    echo submit(_('Delete all messages'), 'class="delbutton"') . '</form></td><td class="spacer"></td><td>' . form('post');
    if (isset($_POST['multi'])) {
        echo submit(_('Switch to single-line'));
    } else {
        echo hidden('multi', 'on') . submit(_('Switch to multi-line'));
    }
    echo hidden('sendto', htmlspecialchars($_REQUEST['sendto'])) . '</form></td>';
    echo '</tr></table></td></tr></table>';
    print_end();
}

function send_greeting(): void
{
	global $U, $language;
	print_start('greeting', (int) $U['refresh'], "$_SERVER[SCRIPT_NAME]?action=view&session=$U[session]&lang=$language");
	printf('<h1>'._('Welcome %s!').'</h1>', style_this(htmlspecialchars($U['nickname']), $U['style']));
	printf('<hr><small>'._('If this frame does not reload in %d seconds, you\'ll have to enable automatic redirection (meta refresh) in your browser. Also make sure no web filter, local proxy tool or browser plugin is preventing automatic refreshing! This could be for example "Polipo", "NoScript", etc.<br>As a workaround (or in case of server/proxy reload errors) you can always use the buttons at the bottom to refresh manually.').'</small>', $U['refresh']);
	$rulestxt=get_setting('rulestxt');
	if(!empty($rulestxt)){
		echo '<hr><div id="rules"><h2>'._('Rules')."</h2>$rulestxt</div>";
	}
	print_end();
}

function send_help(): void {
    global $U;
    print_start('help');

    // Tampilkan aturan chat jika ada
    $rulestxt = get_setting('rulestxt');
    if (!empty($rulestxt)) {
        echo '<div class="help-section rules">
                <h2 class="help-title">'._('Rules').'</h2>
                <div class="help-content">'.$rulestxt.'</div>
              </div>
              <hr class="help-divider">';
    }

    // Tampilkan bagian bantuan utama
    echo '<div class="help-section">
            <h2 class="help-title">'._('Help').'</h2>
            <div class="help-content">';
    
    // Informasi dasar
    echo '<div class="help-item">
            <p>'._("Semua fungsi sudah cukup jelas, cukup gunakan tombol yang tersedia. Di profil Anda dapat mengatur kecepatan refresh dan warna font, serta mengabaikan pengguna lain.").'</p>
            <p class="help-note"><strong>'._("Catatan:").'</strong> '._("Ini adalah chat, jadi jika Anda tidak aktif mengobrol, Anda akan otomatis keluar setelah beberapa waktu.").'</p>
          </div>';

    // Fitur embed gambar
    if (get_setting('imgembed')) {
        echo '<div class="help-item">
                <h3>'._('Embed Gambar').'</h3>
                <p>'._('Untuk menyematkan gambar dalam posting Anda, cukup tambahkan [img] di depan URL gambar.').'</p>
                <code>Contoh: [img]http://example.com/images/file.jpg</code>
              </div>';
    }

    // Informasi untuk member
    if ($U['status'] >= 3) {
        echo '<div class="help-item member-info">
                <h3>'._('Fitur Member').'</h3>
                <p>'._("Member memiliki opsi tambahan di profil: mengubah font, mengganti password, dan menghapus akun.").'</p>';
        
        // Informasi untuk moderator        
        if ($U['status'] >= 5) {
            echo '<h3>'._('Fitur Moderator').'</h3>
                  <p>'._("Moderator dapat mengakses tombol Admin untuk membersihkan ruangan, mengeluarkan pengguna, melihat sesi aktif dan menonaktifkan akses tamu.").'</p>';
            
            // Informasi untuk admin
            if ($U['status'] >= 7) {
                echo '<h3>'._('Fitur Admin').'</h3>
                      <p>'._("Admin dapat mendaftarkan tamu, mengedit member dan mendaftarkan nickname baru.").'</p>';
            }
        }
        echo '</div>';
    }

    echo '</div></div>';

    // Tombol kembali dan credit
    echo '<div class="help-footer">
            <hr class="help-divider">'.
            form('view').
            submit(_('Back to the chat.'), 'class="btn-back"').
            '</form>'.
            credit().
          '</div>';

    print_end();
}

function view_publicnotes(): void
{
	global $db;
	$dateformat = get_setting('dateformat');
	print_start('publicnotes');
	echo '<div class="public-notes-container" style="background:black;">';
	echo '<h2>'._('Public notes').'</h2><p>';
	$query = $db->query('SELECT lastedited, editedby, text FROM ' . PREFIX . 'notes INNER JOIN (SELECT MAX(id) AS latest FROM ' . PREFIX . 'notes WHERE type=3 GROUP BY editedby) AS t ON t.latest = id;');
	while($result = $query->fetch(PDO::FETCH_OBJ)){
		if (!empty($result->text)) {
			if(MSGENCRYPTED){
				try {
					$result->text = sodium_crypto_aead_aes256gcm_decrypt(base64_decode($result->text), null, AES_IV, ENCRYPTKEY);
				} catch (SodiumException $e){
					send_error($e->getMessage());
				}
			}
			echo '<hr>';
			printf(_('Last edited by %1$s at %2$s'), htmlspecialchars($result->editedby), date($dateformat, $result->lastedited));
			echo '<br>';
			echo '<textarea cols="80" rows="9" readonly="true">' . htmlspecialchars($result->text) . '</textarea>';
			echo '<br>';
		}
	}
	echo '</div>';
	print_end();
}
function send_profile(string $arg=''): void
{
	global $U, $db, $language;
	print_start('profile');
	echo form('profile', 'save').'<h2 class="cyber-title">'._('Your Profile')."</h2><i class=\"cyber-alert\">$arg</i><table class=\"cyber-table\">";
	thr();
	$ignored=[];
	$stmt=$db->prepare('SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=? ORDER BY LOWER(ign);');
	$stmt->execute([$U['nickname']]);
	while($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
		$ignored[]=htmlspecialchars($tmp['ign']);
	}
	if(count($ignored)>0){
		echo '<tr><td><table id="unignore" class="cyber-inner-table"><tr><th class="cyber-header">'._("Don't ignore anymore").'</th><td>';
		echo '<select name="unignore" class="cyber-select" size="1"><option value="">'._('(choose)').'</option>';
		foreach($ignored as $ign){
			echo "<option value=\"$ign\">$ign</option>";
		}
		echo '</select></td></tr></table></td></tr>';
		thr();
	}
	echo '<tr><td><table id="ignore" class="cyber-inner-table"><tr><th class="cyber-header">'._('Ignore').'</th><td>';
	echo '<select name="ignore" class="cyber-select" size="1"><option value="">'._('(choose)').'</option>';
	$stmt=$db->prepare('SELECT DISTINCT poster, style FROM ' . PREFIX . 'messages INNER JOIN (SELECT nickname, style FROM ' . PREFIX . 'sessions UNION SELECT nickname, style FROM ' . PREFIX . 'members) AS t ON (' . PREFIX . 'messages.poster=t.nickname) WHERE poster!=? AND poster NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) ORDER BY LOWER(poster);');
	$stmt->execute([$U['nickname'], $U['nickname']]);
	while($nick=$stmt->fetch(PDO::FETCH_NUM)){
		echo '<option value="'.htmlspecialchars($nick[0])."\" style=\"$nick[1]\">".htmlspecialchars($nick[0]).'</option>';
	}
	echo '</select></td></tr></table></td></tr>';
	thr();
	$max_refresh_rate = get_setting('max_refresh_rate');
	$min_refresh_rate = get_setting('min_refresh_rate');
	echo '<tr><td><table id="refresh" class="cyber-inner-table"><tr><th class="cyber-header">'.sprintf(_('Refresh rate (%1$d-%2$d seconds)'), $min_refresh_rate, $max_refresh_rate).'</th><td>';
	echo '<input type="number" name="refresh" class="cyber-input" size="3" min="'.$min_refresh_rate.'" max="'.$max_refresh_rate.'" value="'.$U['refresh'].'"></td></tr></table></td></tr>';
	thr();
	preg_match('/#([0-9a-f]{6})/i', $U['style'], $matches);
	echo '<tr><td><table id="colour" class="cyber-inner-table"><tr><th class="cyber-header">'._('Font colour')." (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours&amp;session=$U[session]&amp;lang=$language\" target=\"view\" class=\"cyber-link\">"._('View examples').'</a>)</th><td>';
	echo "<input type=\"color\" value=\"#$matches[1]\" name=\"colour\" class=\"cyber-color\"></td></tr></table></td></tr>";
	thr();
	echo '<tr><td><table id="bgcolour" class="cyber-inner-table"><tr><th class="cyber-header">'._('Background colour')." (<a href=\"$_SERVER[SCRIPT_NAME]?action=colours&amp;session=$U[session]&amp;lang=$language\" target=\"view\" class=\"cyber-link\">"._('View examples').'</a>)</th><td>';
	echo "<input type=\"color\" value=\"#$U[bgcolour]\" name=\"bgcolour\" class=\"cyber-color\"></td></tr></table></td></tr>";
	thr();
	if($U['status']>=3){
		echo '<tr><td><table id="font" class="cyber-inner-table"><tr><th class="cyber-header">'._('Fontface').'</th><td><table>';
		echo '<tr><td>&nbsp;</td><td><select name="font" class="cyber-select" size="1"><option value="">* '._('Room Default').' *</option>';
		$F=load_fonts();
		foreach($F as $name=>$font){
			echo "<option style=\"$font\" ";
			if(strpos($U['style'], $font)!==false){
				echo 'selected ';
			}
			echo "value=\"$name\">$name</option>";
		}
		echo '</select></td><td>&nbsp;</td><td><label class="cyber-checkbox"><input type="checkbox" name="bold" id="bold" value="on"';
		if(strpos($U['style'], 'font-weight:bold;')!==false){
			echo ' checked';
		}
		echo '><b>'._('Bold').'</b></label></td><td>&nbsp;</td><td><label class="cyber-checkbox"><input type="checkbox" name="italic" id="italic" value="on"';
		if(strpos($U['style'], 'font-style:italic;')!==false){
			echo ' checked';
		}
		echo '><i>'._('Italic').'</i></label></td><td>&nbsp;</td><td><label class="cyber-checkbox"><input type="checkbox" name="small" id="small" value="on"';
		if(strpos($U['style'], 'font-size:smaller;')!==false){
			echo ' checked';
		}
		echo '><small>'._('Small').'</small></label></td></tr></table></td></tr></table></td></tr>';
		thr();
	}
	echo '<tr><td class="cyber-preview">'.style_this(htmlspecialchars($U['nickname'])." : "._('Example for your chosen font'), $U['style']).'</td></tr>';
	thr();
	$bool_settings=[
		'timestamps' => _('Show Timestamps'),
		'nocache' => _('Autoscroll (for old browsers or top-to-bottom sort).'),
		'sortupdown' => _('Sort messages from top to bottom'),
		'hidechatters' => _('Hide list of chatters'),
	];
	if(get_setting('imgembed')){
		$bool_settings['embed'] = _('Embed images');
	}
	if($U['status']>=5 && get_setting('incognito')){
		$bool_settings['incognito'] = _('Incognito mode');
	}
	foreach($bool_settings as $setting => $title){
		echo "<tr><td><table id=\"$setting\" class=\"cyber-inner-table\"><tr><th class=\"cyber-header\">".$title.'</th><td>';
		echo "<label class=\"cyber-checkbox\"><input type=\"checkbox\" name=\"$setting\" value=\"on\"";
		if($U[$setting]){
			echo ' checked';
		}
		echo '><b>'._('Enabled').'</b></label></td></tr></table></td></tr>';
		thr();
	}
	if($U['status']>=2 && get_setting('eninbox')){
		echo '<tr><td><table id="eninbox" class="cyber-inner-table"><tr><th class="cyber-header">'._('Enable offline inbox').'</th><td>';
		echo '<select name="eninbox" id="eninbox" class="cyber-select">';
		echo '<option value="0"';
		if($U['eninbox']==0){
			echo ' selected';
		}
		echo '>'._('Disabled').'</option>';
		echo '<option value="1"';
		if($U['eninbox']==1){
			echo ' selected';
		}
		echo '>'._('For everyone').'</option>';
		echo '<option value="3"';
		if($U['eninbox']==3){
			echo ' selected';
		}
		echo '>'._('For members only').'</option>';
		echo '<option value="5"';
		if($U['eninbox']==5){
			echo ' selected';
		}
		echo '>'._('For staff only').'</option>';
		echo '</select></td></tr></table></td></tr>';
		thr();
	}
	echo '<tr><td><table id="tz" class="cyber-inner-table"><tr><th class="cyber-header">'._('Time zone').'</th><td>';
	echo '<select name="tz" class="cyber-select">';
	$tzs=timezone_identifiers_list();
	foreach($tzs as $tz){
		echo "<option value=\"$tz\"";
		if($U['tz']==$tz){
			echo ' selected';
		}
		echo ">$tz</option>";
	}
	echo '</select></td></tr></table></td></tr>';
	thr();
	if($U['status']>=2){
		echo '<tr><td><table id="changepass" class="cyber-inner-table"><tr><th class="cyber-header">'._('Change Password').'</th></tr>';
		echo '<tr><td><table>';
		echo '<tr><td>&nbsp;</td><td>'._('Old password:').'</td><td><input type="password" name="oldpass" class="cyber-input" size="20" autocomplete="current-password"></td></tr>';
		echo '<tr><td>&nbsp;</td><td>'._('New password:').'</td><td><input type="password" name="newpass" class="cyber-input" size="20" autocomplete="new-password"></td></tr>';
		echo '<tr><td>&nbsp;</td><td>'._('Confirm new password:').'</td><td><input type="password" name="confirmpass" class="cyber-input" size="20" autocomplete="new-password"></td></tr>';
		echo '</table></td></tr></table></td></tr>';
		thr();
		echo '<tr><td><table id="changenick" class="cyber-inner-table"><tr><th class="cyber-header">'._('Change Nickname').'</th><td><table>';
		echo '<tr><td>&nbsp;</td><td>'._('New nickname:').'</td><td><input type="text" name="newnickname" class="cyber-input" size="20" autocomplete="username">';
		echo '</table></td></tr></table></td></tr>';
		thr();
	}
	echo '<tr><td>'.submit(_('Save changes'), 'class="cyber-button"').'</td></tr></table></form>';
	if($U['status']>1 && $U['status']<8){
		echo '<br>'.form('profile', 'delete').submit(_('Delete account'), 'class="cyber-delete-button"').'</form>';
	}
	echo '</p><br>'.form('view').submit(_('Back to the chat.'), 'class="cyber-back-button"').'</form>';
	print_end();
}
function send_controls(): void
{
	global $U;
	print_start('controls');
	$personalnotes=(bool) get_setting('personalnotes');
	$publicnotes=(bool) get_setting('publicnotes');
	$hide_reload_post_box=(bool) get_setting('hide_reload_post_box');
	$hide_reload_messages=(bool) get_setting('hide_reload_messages');
	$hide_profile=(bool) get_setting('hide_profile');
	$hide_admin=(bool) get_setting('hide_admin');
	$hide_notes=(bool) get_setting('hide_notes');
	$hide_clone=(bool) get_setting('hide_clone');
	$hide_rearrange=(bool) get_setting('hide_rearrange');
	$hide_help=(bool) get_setting('hide_help');
	echo '<table><tr>';
	if(!$hide_reload_post_box) {
		echo '<td>' . form_target( 'post', 'post' ) . submit( _('Reload Post Box') ) . '</form></td>';

	}
	if(!$hide_reload_messages) {
		echo '<td>' . form_target( 'view', 'view' ) . submit( _('Reload Messages') ) . '</form></td>';
	}
	if(!$hide_profile) {
		echo '<td>' . form_target( 'view', 'profile' ) . submit( _('Profile') ) . '</form></td>';
	}
	if($U['status']>=5){
		if(!$hide_admin) {
			echo '<td>' . form_target( 'view', 'admin' ) . submit( _('Admin') ) . '</form></td>';
		}
		if(!$personalnotes && !$hide_notes){
			echo '<td>'.form_target('view', 'notes', 'staff').submit(_('Notes')).'</form></td>';
		}
	}
	if($publicnotes){
		echo '<td>'.form_target('view', 'viewpublicnotes').submit(_('View public notes')).'</form></td>';
	}
	
	if($U['status']>=3){
		if($personalnotes || $publicnotes){
			echo '<td>'.form_target('view', 'notes').submit(_('Notes')).'</form></td>';
		}
		if(!$hide_clone) {
			echo '<td>' . form_target( '_blank', 'login' ) . submit( _('Clone') ) . '</form></td>';
		}

	}
	if(!isset($_GET['sort'])){
		$sort=0;
	}else{
		$sort=1;
	}
	if(!$hide_rearrange) {
		echo '<td>' . form_target( '_parent', 'login' ) . hidden( 'sort', $sort ) . submit( _('Rearrange') ) . '</form></td>';
	}
	if(!$hide_help) {
		echo '<td>' . form_target( 'view', 'help' ) . submit( _('Rules & Help') ) . '</form></td>';
	}
	echo '<td>' . form_target('_parent', 'send_toggle_afk') . submit(_('Toggle AFK')) . '</form></td>';
	echo '<td>'.form_target('_parent', 'logout').submit(_('Exit Chat'), 'id="exitbutton"').'</form></td>';
	echo '<td><a href="./payment/index.php" target="_blank"><button type="button" id="donatebutton">'._('Donate Me!').'</button></a></td>';	echo '</tr></table>';
	print_end();
}

function send_toggle_afk(): void {
	global $U, $db;
	
	// Create afk_status table if it doesn't exist
	$db->exec('CREATE TABLE IF NOT EXISTS ' . PREFIX . 'afk_status (
		nickname VARCHAR(64) PRIMARY KEY,
		is_afk BOOLEAN DEFAULT FALSE
	);');

	// Get current nickname and AFK status
	$nickname = $U['nickname'];
	$stmt = $db->prepare('SELECT is_afk FROM ' . PREFIX . 'afk_status WHERE nickname = ?');
	$stmt->execute([$nickname]);
	$result = $stmt->fetch(PDO::FETCH_ASSOC);
	
	if ($result) {
		// Toggle existing AFK status
		$new_afk = !$result['is_afk'];
		$stmt = $db->prepare('DELETE FROM ' . PREFIX . 'afk_status WHERE nickname = ?');
		$stmt->execute([$nickname]);
		$new_afk = !$result['is_afk']; // Set new_afk value after update
	} else {
		// Insert new AFK status as true
		$stmt = $db->prepare('INSERT INTO ' . PREFIX . 'afk_status (nickname, is_afk) VALUES (?, TRUE)');
		$stmt->execute([$nickname]);
		$new_afk = true; // Set new_afk value after insert
	}

	// Add AFK system mess
	// Redirect back to chat
	send_frameset();
}

function send_download(): void
{
	global $db;
	if(isset($_GET['id'])){
		$stmt=$db->prepare('SELECT filename, type, data FROM ' . PREFIX . 'files WHERE hash=?;');
		$stmt->execute([$_GET['id']]);
		if($data=$stmt->fetch(PDO::FETCH_ASSOC)){
			send_headers();
			header("Content-Type: $data[type]");
			header("Content-Disposition: filename=\"$data[filename]\"");
			header("Content-Security-Policy: default-src 'none'");
			echo base64_decode($data['data']);
		}else{
			http_response_code(404);
			send_error(_('File not found!'));
		}
	}else{
		http_response_code(404);
		send_error(_('File not found!'));
	}
}

function send_logout(): void
{
	global $U;
	print_start('logout');
	echo '<div class="continaer_logout">';
	echo '<div class="glitch_logout">';
	$nicknameStyled = style_this(htmlspecialchars($U['nickname']), $U['style']);
	$disconnectMessage = sprintf(_('DISCONNECTED: %s'), $nicknameStyled);
	echo "<h1 data-text='{$disconnectMessage}'>{$disconnectMessage}</h1>";
	echo '</div>';
	echo '<div class="cyber-message">';
	echo '<p class="matrix-text">'._('Neural link terminated. Return to access point?').'</p>';
	echo '<p class="cyber-warning">'._('Don\'t forget to encrypt this node location in your memory banks.').'</p>';
	echo '<p class="warnss">'._('dont wory about your chat the chat is encrypt with RSA').'</p>';
	echo '</div>';
	echo form_target('_parent', '').submit(_('REINITIALIZE CONNECTION'), 'class="cyber-button glitch-effect"').'</form>';
	echo '</div>';
	print_end();
}

function send_colours(): void
{
	print_start('colours');
	echo '<h2>'._('Colourtable').'</h2><kbd><b>';
	for($red=0x00;$red<=0xFF;$red+=0x33){
		for($green=0x00;$green<=0xFF;$green+=0x33){
			for($blue=0x00;$blue<=0xFF;$blue+=0x33){
				$hcol=sprintf('%02X%02X%02X', $red, $green, $blue);
				echo "<span style=\"color:#$hcol\">$hcol</span> ";
			}
			echo '<br>';
		}
		echo '<br>';
	}
	echo '</b></kbd>'.form('profile').submit(_('Back to your Profile'), ' class="backbutton"').'</form>';
	print_end();
}
function send_login(): void
{

	 $ga=(int) get_setting('guestaccess');
    if($ga===4){
        send_chat_disabled();
    }
    print_start('login');

	echo '<div id="judul-title">';
	echo '<h1 id="chatname" class="cyber-glitch-title" data-text="'.get_setting('chatname').'">'.get_setting('chatname').'</h1>';
	echo '</div>';
    
	echo form_target('_parent', 'login');
	if($englobal===1 && isset($_POST['globalpass'])){
		echo hidden('globalpass', htmlspecialchars($_POST['globalpass']));
	}
	echo '<table id="login_table">';
	if($englobal!==1 || (isset($_POST['globalpass']) && $_POST['globalpass']==get_setting('globalpass'))){
		echo '<tr><td id="nickname_label">'._('Nickname:').'</td><td><input type="text" name="nick" size="15" autocomplete="username" autofocus id="nickname_input"></td></tr>';
		echo '<tr><td id="password_label">'._('Password:').'</td><td><input type="password" name="pass" size="15" autocomplete="current-password" id="password_input"></td></tr>';
		send_captcha();
		if($ga!==0){
			if(get_setting('guestreg')!=0){
				echo '<tr><td id="regpass_label">'._('Repeat password<br>to register').'</td><td><input type="password" name="regpass" size="15" placeholder="'._('(optional)').'" autocomplete="new-password" id="regpass_input"></td></tr>';
			}
			if($englobal===2){
				echo '<tr><td id="globalpass_label">'._('Global Password:').'</td><td><input type="password" name="globalpass" size="15" id="globalpass_input"></td></tr>';
			}
			echo '<tr><td colspan="2" id="color_label">'._('Guests, choose a colour:').'<br><select name="colour" id="color_select"><option value="">* '._('Random Colour').' *</option>';
			print_colours();
			echo '</select></td></tr>';
		}else{
			echo '<tr><td colspan="2" id="members_only">'._('Sorry, currently members only!').'</td></tr>';
		}
		echo '<tr><td colspan="2"><input type="submit" value="'._('INITIALIZE CONNECTION').'" id="submit_button"></td></tr></table></form>';
		get_nowchatting();
		$rulestxt=get_setting('rulestxt');
		if(!empty($rulestxt)){
			echo '<div id="rules"><h2 id="rules_title">SYSTEM PROTOCOLS</h2>'.$rulestxt.'</div>';
		}
	}else{
		echo '<tr><td id="global_pass_label">'._('Global Password:').'</td><td><input type="password" name="globalpass" size="15" autofocus id="global_pass_input"></td></tr>';
		if($ga===0){
			echo '<tr><td colspan="2" id="members_only_msg">'._('Sorry, currently members only!').'</td></tr>';
		}
		echo '<tr><td colspan="2"><input type="submit" value="'._('INITIALIZE CONNECTION').'" id="submit_btn"></td></tr></table></form>';
	}
	echo '<h4 id="system_protocols">SYSTEM PROTOCOLS</h4>';
	echo '<p id="rules_text">'._('No CP - No Spamming - No Gore/other illegal activity').'</p>';
	echo '<span id="createdby">'._('Made With ❤️ By  ').'</span>'._("<strong style='color: #ff0000;'>XplDan</strong>");
	echo '<div id="changelang">';
	echo '<div id="lang_label">'._('Select System Language').'</div>';
	echo '<div id="lang_grid">';
	echo '<select id="langSelect" onchange="window.location.href=this.value">';
	echo '<option value="" disabled selected>'._('Select Language').'</option>';
	foreach(LANGUAGES as $lang=>$data){
		echo '<option value="'.$_SERVER['SCRIPT_NAME'].'?lang='.$lang.'">'.$data['name'].'</option>';
	}
	echo '</select>';
	echo '</div></div>';
	echo '</p>'.credit();
	print_end();
}

function send_chat_disabled(): void
{
	print_start('disabled');
	echo get_setting('disabletext');
	print_end();
}

function send_error(string $err): void
{
	print_start('error');
	echo '<h2>'.sprintf(_('Error: %s'),  $err).'</h2>'.form_target('_parent', '').submit(_('Back to the login page.'), 'class="backbutton"').'</form>';
	print_end();
}

function send_fatal_error(string $err): void
{
	global $language, $styles, $dir;
	prepare_stylesheets('fatal_error');
	send_headers();
	echo '<!DOCTYPE html><html lang="'.$language.'" dir="'.$dir.'"><head>'.meta_html();
	echo '<title>'._('Fatal error').'</title>';
	echo "<style>$styles[fatal_error]</style>";
	echo '</head><body>';
	echo '<h2>'.sprintf(_('Fatal error: %s'),  $err).'</h2>';
	print_end();
}

function print_notifications(): void
{
	global $U, $db;
	echo '<span id="notifications">';
	$stmt=$db->prepare('SELECT loginfails FROM ' . PREFIX . 'members WHERE nickname=?;');
	$stmt->execute([$U['nickname']]);
	$temp=$stmt->fetch(PDO::FETCH_NUM);
	if($temp && $temp[0]>0){
		echo '<p align="middle">' . $temp[0] . "&nbsp;" . _('Failed login attempt(s)') . "</p>";
	}
	if($U['status']>=2 && $U['eninbox']!=0){
		$stmt=$db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'inbox WHERE recipient=?;');
		$stmt->execute([$U['nickname']]);
		$tmp=$stmt->fetch(PDO::FETCH_NUM);
		if($tmp[0]>0){
			echo '<p>'.form('inbox').submit(sprintf(_('Read %d messages in your inbox'), $tmp[0])).'</form></p>';
		}
	}
	if($U['status']>=5 && get_setting('guestaccess')==3){
		$result=$db->query('SELECT COUNT(*) FROM ' . PREFIX . 'sessions WHERE entry=0 AND status=1;');
		$temp=$result->fetch(PDO::FETCH_NUM);
		if($temp[0]>0){
			echo '<p>';
			echo form('admin', 'approve');
			echo submit(sprintf(_('%d new guests to approve'), $temp[0])).'</form></p>';
		}
	}
	echo '</span>';
}
function check_bad_nickname(string $nickname): bool
{
	global $U, $db;
	if ($U['nickname'] === 'XplDan' || $U['nickname'] === 'bot') {
		return false;
	}
	
	try {

		
		// Get bad words from database
		$stmt = $db->query('SELECT word FROM ' . PREFIX . 'bad_words');
		$bad_words = $stmt->fetchAll(PDO::FETCH_COLUMN);

		foreach ($bad_words as $word) {
			if (stripos($nickname, $word) !== false) {
				kick_chatter([$nickname], 'Dont Use the bad name here, or you will be kicked ~XplDan_bot', true);
				return true;
			}
		}
		return false;

	} catch (PDOException $e) {
		error_log("Database error in check_bad_nickname: " . $e->getMessage());
		return false;
	}
}

function print_chatters(): void
{
	global $U, $db, $language;
	if(!$U['hidechatters']){
		echo '<div id="chatters"><table><tr>';
		$stmt=$db->prepare('SELECT s.nickname, s.style, s.status, s.exiting, a.is_afk FROM ' . PREFIX . 'sessions s LEFT JOIN ' . PREFIX . 'afk_status a ON s.nickname = a.nickname WHERE s.entry!=0 AND s.status>0 AND s.incognito=0 AND s.nickname NOT IN (SELECT ign FROM '. PREFIX . 'ignored WHERE ignby=? UNION SELECT ignby FROM '. PREFIX . 'ignored WHERE ign=?) ORDER BY s.status DESC, s.lastpost DESC;');
		$stmt->execute([$U['nickname'], $U['nickname']]);
		$nc=substr(time(), -6);
		$G=$M=$S=$A=[];
		$channellink="<a class=\"channellink\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=";
		$nicklink="<a class=\"nicklink\" href=\"$_SERVER[SCRIPT_NAME]?action=post&amp;session=$U[session]&amp;lang=$language&amp;nc=$nc&amp;sendto=";
		while($user=$stmt->fetch(PDO::FETCH_NUM)){
			
			if( $user[2] >= 3 && check_bad_nickname($user[0])) {
				continue;
			}
			$link=$nicklink.urlencode($user[0]).'" target="post"><span style="display:inline-flex;align-items:center;gap:5px">'.style_this(htmlspecialchars($user[0]), $user[1]);
			if ($user[3]>0) {
				$link .= '<span class="sysmsg" title="'._('logging out').'">'.get_setting('exitingtxt').'</span>';
			}
			// Add [AFK] indicator if user is AFK
			if ($user[4]) {
				$link .= '<span class="afk-badge">[AFK]</span>';
			}
			$link .= '</span></a>';
			if($user[2]<3){ // guest or superguest
				$G[]=$link;
			} elseif($user[2]>=7){ // admin or superadmin
				$A[]=$link;
			} elseif(($user[2]>=5) && ($user[2]<=6)){ // moderator or supermoderator
				$S[]=$link;
			} elseif($user[2]=3){ // member
				$M[]=$link;
			}
		}
		if($U['status']>5){ // can chat in admin channel
				echo '<th>' . $channellink . 's _" target="post">' . _('Admin') . ':</a></th><td style:"font-size:10px;>&nbsp;</td><td>'.implode(' &nbsp; ', $A).'</td>';
			} else {
				echo '<th>'._('Admin:').'</th><td>&nbsp;</td><td>'.implode(' &nbsp; ', $A).'</td>';
		}
		if($U['status']>4){ // can chat in staff channel
				echo '<th>' . $channellink . 's &#37;" target="post">' . _('Staff') . ':</a></th><td style:"font-size:10px;>&nbsp;</td><td>'.implode(' &nbsp; ', $S).'</td>';
			} else {
				echo '<th>'._('Staff:').'</th><td>&nbsp;</td><td>'.implode(' &nbsp; ', $S).'</td>';
		}
		if($U['status']>=3){ // can chat in member channel
			echo '<th>' . $channellink . 's ?" target="post">' . _('Members') . ':</a></th><td>&nbsp;</td><td class="chattername" style:"font-size:10px;>'.implode(' &nbsp; ', $M).'</td>';
		} else {
			echo '<th>'._('Members:').'</th><td>&nbsp;</td><td>'.implode(' &nbsp; ', $M).'</td>';
		}
		echo '<th>' . $channellink . 's *" target="post">' . _('Guests') . ':</a></th><td>&nbsp;</td><td class="chattername" style:"font-size:10px;" >'.implode(' &nbsp; ', $G).'</td>';
		echo '</tr></table></div>';
	}
}

function create_session(bool $setup, string $nickname, string $password): void
{
	global $U;
	$U['nickname']=preg_replace('/\s/', '', $nickname);
	if(check_member($password)){
		if($setup && $U['status']>=7){
			$U['incognito']=1;
		}
		$U['entry']=$U['lastpost']=time();
	}else{
		add_user_defaults($password);
		check_captcha($_POST['challenge'] ?? '', $_POST['captcha'] ?? '');
		$ga=(int) get_setting('guestaccess');
		if(!valid_nick($U['nickname'])){
			send_error(sprintf(_('Invalid nickname (%1$d characters maximum and has to match the regular expression "%2$s")'), get_setting('maxname'), get_setting('nickregex')));
		}
		if(!valid_pass($password)){
			send_error(sprintf(_('Invalid password (At least %1$d characters and has to match the regular expression "%2$s")'), get_setting('minpass'), get_setting('passregex')));
		}
		if($ga===0){
			send_error(_('Sorry, currently members only!'));
		}elseif(in_array($ga, [2, 3], true)){
			$U['entry'] = 0;
		}
		if(get_setting('englobalpass')!=0 && isset($_POST['globalpass']) && $_POST['globalpass']!=get_setting('globalpass')){
			send_error(_('Wrong global Password!'));
		}
	}
	$U['exiting']=0;
	try {
		$U[ 'postid' ] = bin2hex( random_bytes( 3 ) );
	} catch(Exception $e) {
		send_error($e->getMessage());
	}
	write_new_session($password);
}

function check_captcha(string $challenge, string $captcha_code): void
{
	global $db, $memcached;
	$captcha=(int) get_setting('captcha');
	if($captcha!==0){
		if(empty($challenge)){
			send_error(_('Wrong Captcha'));
		}
		$code = '';
		if(MEMCACHED){
			if(!$code=$memcached->get(DBNAME . '-' . PREFIX . "captcha-$_POST[challenge]")){
				send_error(_('Captcha already used or timed out.'));
			}
			$memcached->delete(DBNAME . '-' . PREFIX . "captcha-$_POST[challenge]");
		}else{
			$stmt=$db->prepare('SELECT code FROM ' . PREFIX . 'captcha WHERE id=?;');
			$stmt->execute([$challenge]);
			$stmt->bindColumn(1, $code);
			if(!$stmt->fetch(PDO::FETCH_BOUND)){
				send_error(_('Captcha already used or timed out.'));
			}
			$time=time();
			$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'captcha WHERE id=? OR time<(?-(SELECT value FROM ' . PREFIX . "settings WHERE setting='captchatime'));");
			$stmt->execute([$challenge, $time]);
		}
		if($captcha_code!==$code){
			if($captcha!==3 || strrev($captcha_code)!==$code){
				send_error(_('Wrong Captcha Try Again'));
			}
		}
	}
}

function is_definitely_ssl() : bool {
	if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
		return true;
	}
	if (isset($_SERVER['SERVER_PORT']) && ('443' == $_SERVER['SERVER_PORT'])) {
		return true;
	}
	if (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && ('https' === $_SERVER['HTTP_X_FORWARDED_PROTO'])) {
		return true;
	}
	return false;
}

function set_secure_cookie(string $name, string $value): void
{
	if (version_compare(PHP_VERSION, '7.3.0') >= 0) {
		setcookie($name, $value, ['expires' => 0, 'path' => '/', 'domain' => '', 'secure' => is_definitely_ssl(), 'httponly' => true, 'samesite' => 'Strict']);
	}else{
		setcookie($name, $value, 0, '/', '', is_definitely_ssl(), true);
	}
}

function write_new_session(string $password): void
{
	global $U, $db, $session;

	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE nickname=?;');
	$stmt->execute([$U['nickname']]);
	if($temp=$stmt->fetch(PDO::FETCH_ASSOC)){
		// check whether alrady logged in
		   // Tambahkan rate limiting
    if(!check_rate_limit()) {
        send_error(_('Too many login attempts. Please try again later. hahah dont brutce force my website !'));
    }
    
		if(password_verify($password, $temp['passhash'])){
			$U=$temp;
			check_kicked();
			set_secure_cookie(COOKIENAME, $U['session']);
		}else{
			send_error(_('A user with this nickname is already logged in. Dont to be imposter')."<br>"._('Wrong Password!'));
		}
	}else{
		// create new session
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'sessions WHERE session=?;');
		do{
			try {
				$U[ 'session' ] = bin2hex( random_bytes( 16 ) );
			} catch(Exception $e) {
				send_error($e->getMessage());
			}
			$stmt->execute([$U['session']]);
		}while($stmt->fetch(PDO::FETCH_NUM)); // check for hash collision
		if(isset($_SERVER['HTTP_USER_AGENT'])){
			$useragent=htmlspecialchars($_SERVER['HTTP_USER_AGENT']);
		}else{
			$useragent='';
		}
		if(get_setting('trackip')){
			$ip=$_SERVER['REMOTE_ADDR'];
		}else{
			$ip='';
		}
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'sessions (session, nickname, status, refresh, style, lastpost, passhash, useragent, bgcolour, entry, exiting, timestamps, embed, incognito, ip, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old, postid) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute([$U['session'], $U['nickname'], $U['status'], $U['refresh'], $U['style'], $U['lastpost'], $U['passhash'], $useragent, $U['bgcolour'], $U['entry'], $U['exiting'], $U['timestamps'], $U['embed'], $U['incognito'], $ip, $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['nocache_old'], $U['postid']]);
		$session = $U['session'];
		set_secure_cookie(COOKIENAME, $U['session']);
		if($U['status']>=3 && !$U['incognito']){
			add_system_message(sprintf(get_setting('msgenter'), style_this(htmlspecialchars($U['nickname']), $U['style'])), '');
		}
	}
}
function check_rate_limit(): bool {
    global $db;
    
    // Create login_attempts table if it doesn't exist
    $db->query('CREATE TABLE IF NOT EXISTS ' . PREFIX . 'login_attempts (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ip VARCHAR(45) NOT NULL,
        timestamp INT NOT NULL,
        INDEX (ip, timestamp)
    )');
    
    $ip = $_SERVER['REMOTE_ADDR'];
    $time = time() - 300; // 5 menit window
    
    // Delete old attempts
    $stmt = $db->prepare('DELETE FROM ' . PREFIX . 'login_attempts WHERE timestamp <= ?');
    $stmt->execute([$time]);
    
    $stmt = $db->prepare('SELECT COUNT(*) FROM ' . PREFIX . 'login_attempts WHERE ip = ? AND timestamp > ?');
    $stmt->execute([$ip, $time]);
    $attempts = $stmt->fetchColumn();
    
    if($attempts > 5) {
        return false;
    }
    
    $stmt = $db->prepare('INSERT INTO ' . PREFIX . 'login_attempts (ip, timestamp) VALUES (?, ?)');
    $stmt->execute([$ip, time()]);
    
    return true;
}

function show_fails(): void
{
	global $db, $U;
	$stmt=$db->prepare('SELECT loginfails FROM ' . PREFIX . 'members WHERE nickname=?;');
	$stmt->execute([$U['nickname']]);
	$temp=$stmt->fetch(PDO::FETCH_NUM);
	if($temp && $temp[0]>0){
		print_start('failednotice');
		echo $temp[0] . "&nbsp;" . _('Failed login attempt(s) ') . "<br>" . "you need change your password" . "<br>";
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET loginfails=? WHERE nickname=?;');
		$stmt->execute([0, $U['nickname']]);
		echo form_target('_self', 'login').submit(_('Dismiss')).'</form></td>';
		print_end();
	}
}

function approve_session(): void
{
	global $db;
	if(isset($_POST['what'])){
		if($_POST['what']==='allowchecked' && isset($_POST['csid'])){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=lastpost WHERE nickname=?;');
			foreach($_POST['csid'] as $nick){
				$stmt->execute([$nick]);
			}
		}elseif($_POST['what']==='allowall' && isset($_POST['alls'])){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET entry=lastpost WHERE nickname=?;');
			foreach($_POST['alls'] as $nick){
				$stmt->execute([$nick]);
			}
		}elseif($_POST['what']==='denychecked' && isset($_POST['csid'])){
			$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=? AND status=1;');
			foreach($_POST['csid'] as $nick){
				$stmt->execute([$time, $_POST['kickmessage'], $nick]);
			}
		}elseif($_POST['what']==='denyall' && isset($_POST['alls'])){
			$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=? AND status=1;');
			foreach($_POST['alls'] as $nick){
				$stmt->execute([$time, $_POST['kickmessage'], $nick]);
			}
		}
	}
}

function check_login(): void
{
	global $U, $db;
	$ga=(int) get_setting('guestaccess');
	parse_sessions();
	if(isset($U['session'])){
		if($U['exiting']==1){
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET exiting=0 WHERE session=? LIMIT 1;');
			$stmt->execute([$U['session']]);
		}
		check_kicked();
	}elseif(get_setting('englobalpass')==1 && (!isset($_POST['globalpass']) || $_POST['globalpass']!=get_setting('globalpass'))){
		send_error(_('Wrong global Password!'));
	}elseif(!isset($_POST['nick']) || !isset($_POST['pass'])){
		send_login();
	}else{
		if($ga===4){
			send_chat_disabled();
		}
		if(!empty($_POST['regpass']) && $_POST['regpass']!==$_POST['pass']){
			send_error(_('Password confirmation does not match!'));
		}
		create_session(false, $_POST['nick'], $_POST['pass']);
		if(!empty($_POST['regpass'])){
			$guestreg=(int) get_setting('guestreg');
			if($guestreg===1){
				register_guest(2, $_POST['nick']);
				$U['status']=2;
			}elseif($guestreg===2){
				register_guest(3, $_POST['nick']);
				$U['status']=3;
			}
		}
	}
	// status imi untuk members saja yang guest hilang
	if($U['status']==1){
		if(in_array($ga, [2, 3], true)){
			send_waiting_room();
		}
	}
}

function kill_session(): void
{
	global $U, $db, $session;
	parse_sessions();
	check_expired();
	check_kicked();
	setcookie(COOKIENAME, false);
	$session = '';
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE session=?;');
	$stmt->execute([$U['session']]);
	if($U['status']>=3 && !$U['incognito']){
		add_system_message(sprintf(get_setting('msgexit'), style_this(htmlspecialchars($U['nickname']), $U['style'])), '');
	}
}

function kick_chatter(array $names, string $mes, bool $purge) : bool {
	global $U, $db;
	$lonick='';
	$time=60*(get_setting('kickpenalty')-get_setting('guestexpire'))+time();
	$check=$db->prepare('SELECT style, entry FROM ' . PREFIX . 'sessions WHERE nickname=? AND status!=0 AND (status<? OR nickname=?);');
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, status=0, kickmessage=? WHERE nickname=?;');
	$all=false;
	if($names[0]==='s *'){
		$tmp=$db->query('SELECT nickname FROM ' . PREFIX . 'sessions WHERE status=1;');
		$names=[];
		while($name=$tmp->fetch(PDO::FETCH_NUM)){
			$names[]=$name[0];
		}
		$all=true;
	}
	$i=0;
	foreach($names as $name){
		$check->execute([$name, $U['status'], $U['nickname']]);
		if($temp=$check->fetch(PDO::FETCH_ASSOC)){
			$stmt->execute([$time, $mes, $name]);
			if($purge){
				del_all_messages($name, (int) $temp['entry']);
			}
			$lonick.=style_this(htmlspecialchars($name), $temp['style']).', ';
			++$i;
		}
	}
	if($i>0){
		if($all){
			add_system_message(get_setting('msgallkick'), $U['nickname']);
		}else{
			$lonick=substr($lonick, 0, -2);
			if($i>1){
				add_system_message(sprintf(get_setting('msgmultikick'), $lonick), $U['nickname']);
			}else{
				add_system_message(sprintf(get_setting('msgkick'), $lonick), $U['nickname']);
			}
		}
		return true;
	}
	return false;
}

function logout_chatter(array $names): void
{
	global $U, $db;
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE nickname=? AND status<?;');
	if($names[0]==='s *'){
		$tmp=$db->query('SELECT nickname FROM ' . PREFIX . 'sessions WHERE status=1;');
		$names=[];
		while($name=$tmp->fetch(PDO::FETCH_NUM)){
			$names[]=$name[0];
		}
	}
	foreach($names as $name){
		$stmt->execute([$name, $U['status']]);
	}
}

function check_session(): void
{
	global $U;
	parse_sessions();
	check_expired();
	check_kicked();
	if($U['entry']==0){
		send_waiting_room();
	}
}

function check_expired(): void
{
	global $U, $session;
	if(!isset($U['session'])){
		setcookie(COOKIENAME, false);
		$session = '';
		send_error(_('Invalid/expired session'));
	}
}

function get_count_mods() : int {
	global $db;
	$c=$db->query('SELECT COUNT(*) FROM ' . PREFIX . 'sessions WHERE status>=5')->fetch(PDO::FETCH_NUM);
	return (int) $c[0];
}

function check_kicked(): void
{
	global $U, $session;
	if($U['status']==0){
		setcookie(COOKIENAME, false);
		$session = '';
		$kickmessage = empty($U['kickmessage']) ? 'You have been kicked from the chat' : $U['kickmessage'];
		header("Location:bot/kicked_page.php?nickname=" . urlencode($U['nickname']) . "&kickmessage=" . urlencode($kickmessage));
		exit();
	}
}

function get_nowchatting(): void
{
	global $db;
	parse_sessions();
	$stmt=$db->query('SELECT COUNT(*) FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND incognito=0;');
	$count=$stmt->fetch(PDO::FETCH_NUM);
	echo '<div id="chatters">'.sprintf(_('Chatters Available: %d'), $count[0]).'<br>';
	if(!get_setting('hidechatters')){
		$stmt=$db->query('SELECT nickname, style FROM ' . PREFIX . 'sessions WHERE entry!=0 AND status>0 AND incognito=0 ORDER BY status DESC, lastpost DESC;');
		while($user=$stmt->fetch(PDO::FETCH_NUM)){
			echo style_this(htmlspecialchars($user[0]), $user[1]).' &nbsp; ';
		}
	}
	echo '</div>';
}

function parse_sessions(): void
{
	global $U, $db, $session;
	// look for our session
	if(!empty($session)){
		$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE session=?;');
		$stmt->execute([$session]);
		if($tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			$U=$tmp;
		}
	}
	set_default_tz();
}

//  member handling

function check_member(string $password) : bool {
	global $U, $db;
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'members WHERE nickname=?;');
	$stmt->execute([$U['nickname']]);
	if($temp=$stmt->fetch(PDO::FETCH_ASSOC)){
		if(get_setting('dismemcaptcha')==0){
			check_captcha($_POST['challenge'] ?? '', $_POST['captcha'] ?? '');
		}
		if($temp['passhash']===md5(sha1(md5($U['nickname'].$password)))){
			// old hashing method, update on the fly
			$temp['passhash']=password_hash($password, PASSWORD_DEFAULT);
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET passhash=? WHERE nickname=?;');
			$stmt->execute([$temp['passhash'], $U['nickname']]);
		}
		if(password_verify($password, $temp['passhash'])){
			$U=$temp;
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET lastlogin=? WHERE nickname=?;');
			$stmt->execute([time(), $U['nickname']]);
			return true;
		}else{
			$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET loginfails=? WHERE nickname=?;');
			$stmt->execute([$temp['loginfails']+1, $temp['nickname']]);
			send_error(_('This nickname is a registered member.')."<br>"._('Wrong Password!')."<br>"._('You are Imposter?'));
		}
	}
	return false;
}

function delete_account(): void
{
	global $U, $db;
	if($U['status']<8){
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=1, incognito=0 WHERE nickname=?;');
		$stmt->execute([$U['nickname']]);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'members WHERE nickname=?;');
		$stmt->execute([$U['nickname']]);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE recipient=?;');
		$stmt->execute([$U['nickname']]);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'notes WHERE (type=2 OR type=3) AND editedby=?;');
		$stmt->execute([$U['nickname']]);
		$U['status']=1;
	}
}

function register_guest(int $status, string $nick) : string {
	global $U, $db;
	$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'members WHERE nickname=?');
	$stmt->execute([$nick]);
	if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
		return sprintf(_('%s is already registered.'), style_this(htmlspecialchars($nick), $tmp[0]));
	}
	$stmt=$db->prepare('SELECT * FROM ' . PREFIX . 'sessions WHERE nickname=? AND status=1;');
	$stmt->execute([$nick]);
	if($reg=$stmt->fetch(PDO::FETCH_ASSOC)){
		$reg['status']=$status;
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=? WHERE session=?;');
		$stmt->execute([$reg['status'], $reg['session']]);
	}else{
		return sprintf(_("Can't register %s"), htmlspecialchars($nick));
	}
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, timestamps, embed, style, incognito, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
	$stmt->execute([$reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $U['nickname'], $reg['timestamps'], $reg['embed'], $reg['style'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox'], $reg['sortupdown'], $reg['hidechatters'], $reg['nocache_old']]);
	if($reg['status']==3){
		add_system_message(sprintf(get_setting('msgmemreg'), style_this(htmlspecialchars($reg['nickname']), $reg['style'])), $U['nickname']);
	}else{
		add_system_message(sprintf(get_setting('msgsureg'), style_this(htmlspecialchars($reg['nickname']), $reg['style'])), $U['nickname']);
	}
	return sprintf(_('%s successfully registered.'), style_this(htmlspecialchars($reg['nickname']), $reg['style']));
}

function register_new(string $nick, string $pass) : string {
	global $U, $db;
	$nick=preg_replace('/\s/', '', $nick);
	if(empty($nick)){
		return '';
	}
	$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'sessions WHERE nickname=?');
	$stmt->execute([$nick]);
	if($stmt->fetch(PDO::FETCH_NUM)){
		return sprintf(_("Can't register %s"), htmlspecialchars($nick));
	}
	if(!valid_nick($nick)){
		return sprintf(_('Invalid nickname (%1$d characters maximum and has to match the regular expression "%2$s")'), get_setting('maxname'), get_setting('nickregex'));
	}
	if(!valid_pass($pass)){
		return sprintf(_('Invalid password (At least %1$d characters and has to match the regular expression "%2$s")'), get_setting('minpass'), get_setting('passregex'));
	}
	$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'members WHERE nickname=?');
	$stmt->execute([$nick]);
	if($stmt->fetch(PDO::FETCH_NUM)){
		return sprintf(_('%s is already registered.'), htmlspecialchars($nick));
	}
	$reg=[
		'nickname'	=>$nick,
		'passhash'	=>password_hash($pass, PASSWORD_DEFAULT),
		'status'	=>3,
		'refresh'	=>get_setting('defaultrefresh'),
		'bgcolour'	=>get_setting('colbg'),
		'regedby'	=>$U['nickname'],
		'timestamps'	=>get_setting('timestamps'),
		'style'		=>'color:#'.get_setting('coltxt').';',
		'embed'		=>1,
		'incognito'	=>0,
		'nocache'	=>0,
		'nocache_old'	=>1,
		'tz'		=>get_setting('defaulttz'),
		'eninbox'	=>0,
		'sortupdown'	=>get_setting('sortupdown'),
		'hidechatters'	=>get_setting('hidechatters'),
	];
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, timestamps, style, embed, incognito, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
	$stmt->execute([$reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['regedby'], $reg['timestamps'], $reg['style'], $reg['embed'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox'], $reg['sortupdown'], $reg['hidechatters'], $reg['nocache_old']]);
	return sprintf(_('%s successfully registered.'), htmlspecialchars($reg['nickname']));
}

function change_status(string $nick, string $status) : string {
	global $U, $db;
	if(empty($nick)){
		return '';
	}elseif($U['status']<=$status || !preg_match('/^[023567\-]$/', $status)){
		return sprintf(_("Can't change status of %s"), htmlspecialchars($nick));
	}
	$stmt=$db->prepare('SELECT incognito, style FROM ' . PREFIX . 'members WHERE nickname=? AND status<?;');
	$stmt->execute([$nick, $U['status']]);
	if(!$old=$stmt->fetch(PDO::FETCH_NUM)){
		return sprintf(_("Can't change status of %s"), htmlspecialchars($nick));
	}
	if($status==='-'){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'members WHERE nickname=?;');
		$stmt->execute([$nick]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=1, incognito=0 WHERE nickname=?;');
		$stmt->execute([$nick]);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE recipient=?;');
		$stmt->execute([$nick]);
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'notes WHERE (type=2 OR type=3) AND editedby=?;');
		$stmt->execute([$nick]);
		return sprintf(_('%s successfully deleted from database.'), style_this(htmlspecialchars($nick), $old[1]));
	}else{
		if($status<5){
			$old[0]=0;
		}
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET status=?, incognito=? WHERE nickname=?;');
		$stmt->execute([$status, $old[0], $nick]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET status=?, incognito=? WHERE nickname=?;');
		$stmt->execute([$status, $old[0], $nick]);
		return sprintf(_('Status of %s successfully changed.'), style_this(htmlspecialchars($nick), $old[1]));
	}
}

function passreset(string $nick, string $pass) : string {
	global $U, $db;
	if(empty($nick)){
		return '';
	}
	$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'members WHERE nickname=? AND status<?;');
	$stmt->execute([$nick, $U['status']]);
	if($stmt->fetch(PDO::FETCH_ASSOC)){
		$passhash=password_hash($pass, PASSWORD_DEFAULT);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET passhash=? WHERE nickname=?;');
		$stmt->execute([$passhash, $nick]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET passhash=? WHERE nickname=?;');
		$stmt->execute([$passhash, $nick]);
		return sprintf(_('Successfully reset password for %s'), htmlspecialchars($nick));
	}else{
		return sprintf(_("Can't reset password for %s"), htmlspecialchars($nick));
	}
}

function amend_profile(): void
{
	global $U;
	if(isset($_POST['refresh'])){
		$U['refresh']=$_POST['refresh'];
	}
	if($U['refresh']<5){
		$U['refresh']=5;
	}elseif($U['refresh']>150){
		$U['refresh']=150;
	}
	if(preg_match('/^#([a-f0-9]{6})$/i', $_POST['colour'], $match)){
		$colour=$match[1];
	}else{
		preg_match('/#([0-9a-f]{6})/i', $U['style'], $matches);
		$colour=$matches[1];
	}
	if(preg_match('/^#([a-f0-9]{6})$/i', $_POST['bgcolour'], $match)){
		$U['bgcolour']=$match[1];
	}
	$U['style']="color:#$colour;";
	if($U['status']>=3){
		$F=load_fonts();
		if(isset($F[$_POST['font']])){
			$U['style'].=$F[$_POST['font']];
		}
		if(isset($_POST['small'])){
			$U['style'].='font-size:smaller;';
		}
		if(isset($_POST['italic'])){
			$U['style'].='font-style:italic;';
		}
		if(isset($_POST['bold'])){
			$U['style'].='font-weight:bold;';
		}
	}
	if($U['status']>=5 && isset($_POST['incognito']) && get_setting('incognito')){
		$U['incognito']=1;
	}else{
		$U['incognito']=0;
	}
	if(isset($_POST['tz'])){
		$tzs=timezone_identifiers_list();
		if(in_array($_POST['tz'], $tzs)){
			$U['tz']=$_POST['tz'];
		}
	}
	if(isset($_POST['eninbox']) && $_POST['eninbox']>=0 && $_POST['eninbox']<=5){
		$U['eninbox']=$_POST['eninbox'];
	}
	$bool_settings=['timestamps', 'embed', 'nocache', 'sortupdown', 'hidechatters'];
	foreach($bool_settings as $setting){
		if(isset($_POST[$setting])){
			$U[$setting]=1;
		}else{
			$U[$setting]=0;
		}
	}
}

function save_profile() : string {
	global $U, $db;
	amend_profile();
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET refresh=?, style=?, bgcolour=?, timestamps=?, embed=?, incognito=?, nocache=?, tz=?, eninbox=?, sortupdown=?, hidechatters=? WHERE session=?;');
	$stmt->execute([$U['refresh'], $U['style'], $U['bgcolour'], $U['timestamps'], $U['embed'], $U['incognito'], $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['session']]);
	if($U['status']>=2){
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET refresh=?, bgcolour=?, timestamps=?, embed=?, incognito=?, style=?, nocache=?, tz=?, eninbox=?, sortupdown=?, hidechatters=? WHERE nickname=?;');
		$stmt->execute([$U['refresh'], $U['bgcolour'], $U['timestamps'], $U['embed'], $U['incognito'], $U['style'], $U['nocache'], $U['tz'], $U['eninbox'], $U['sortupdown'], $U['hidechatters'], $U['nickname']]);
	}
	if(!empty($_POST['unignore'])){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'ignored WHERE ign=? AND ignby=?;');
		$stmt->execute([$_POST['unignore'], $U['nickname']]);
	}
	if(!empty($_POST['ignore'])){
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'messages WHERE poster=? AND poster NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?);');
		$stmt->execute([$_POST['ignore'], $U['nickname']]);
		if($U['nickname']!==$_POST['ignore'] && $stmt->fetch(PDO::FETCH_NUM)){
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'ignored (ign, ignby) VALUES (?, ?);');
			$stmt->execute([$_POST['ignore'], $U['nickname']]);
		}
	}
	if($U['status']>1 && !empty($_POST['newpass'])){
		if(!valid_pass($_POST['newpass'])){
			return sprintf(_('Invalid password (At least %1$d characters and has to match the regular expression "%2$s")'), get_setting('minpass'), get_setting('passregex'));
		}
		if(!isset($_POST['oldpass'])){
			$_POST['oldpass']='';
		}
		if(!isset($_POST['confirmpass'])){
			$_POST['confirmpass']='';
		}
		if($_POST['newpass']!==$_POST['confirmpass']){
			return _('Password confirmation does not match!');
		}else{
			$U['newhash']=password_hash($_POST['newpass'], PASSWORD_DEFAULT);
		}
		if(!password_verify($_POST['oldpass'], $U['passhash'])){
			return _('Wrong Password!');
		}
		$U['passhash']=$U['newhash'];
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET passhash=? WHERE session=?;');
		$stmt->execute([$U['passhash'], $U['session']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET passhash=? WHERE nickname=?;');
		$stmt->execute([$U['passhash'], $U['nickname']]);
	}
	if($U['status']>1 && !empty($_POST['newnickname'])){
		$msg=set_new_nickname();
		if($msg!==''){
			return $msg;
		}
	}
	return _('Your profile has successfully been saved.');
}

function set_new_nickname() : string {
	global $U, $db;
	$_POST['newnickname']=preg_replace('/\s/', '', $_POST['newnickname']);
	if(!valid_nick($_POST['newnickname'])){
		return sprintf(_('Invalid nickname (%1$d characters maximum and has to match the regular expression "%2$s")'), get_setting('maxname'), get_setting('nickregex'));
	}
	$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'sessions WHERE nickname=? UNION SELECT id FROM ' . PREFIX . 'members WHERE nickname=?;');
	$stmt->execute([$_POST['newnickname'], $_POST['newnickname']]);
	if($stmt->fetch(PDO::FETCH_NUM)){
		return _('Nickname is already taken');
	}else{
		// Make sure members can not read private messages of previous guests with the same name
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET poster = "" WHERE poster = ? AND poststatus = 9;');
		$stmt->execute([$_POST['newnickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET recipient = "" WHERE recipient = ? AND poststatus = 9;');
		$stmt->execute([$_POST['newnickname']]);
		// change names in all tables
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET nickname=? WHERE nickname=?;');
		$stmt->execute([$_POST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET nickname=? WHERE nickname=?;');
		$stmt->execute([$_POST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET poster=? WHERE poster=?;');
		$stmt->execute([$_POST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET recipient=? WHERE recipient=?;');
		$stmt->execute([$_POST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'ignored SET ignby=? WHERE ignby=?;');
		$stmt->execute([$_POST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'ignored SET ign=? WHERE ign=?;');
		$stmt->execute([$_POST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'inbox SET poster=? WHERE poster=?;');
		$stmt->execute([$_POST['newnickname'], $U['nickname']]);
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'notes SET editedby=? WHERE editedby=?;');
		$stmt->execute([$_POST['newnickname'], $U['nickname']]);
		$U['nickname']=$_POST['newnickname'];
	}
	return '';
}

//sets default settings for guests
function add_user_defaults(string $password): void
{
	global $U;
	$U['refresh']=get_setting('defaultrefresh');
	$U['bgcolour']=get_setting('colbg');
	if(!isset($_POST['colour']) || !preg_match('/^[a-f0-9]{6}$/i', $_POST['colour']) || abs(greyval($_POST['colour'])-greyval(get_setting('colbg')))<75){
		do{
			$colour=sprintf('%06X', mt_rand(0, 16581375));
		}while(abs(greyval($colour)-greyval(get_setting('colbg')))<75);
	}else{
		$colour=$_POST['colour'];
	}
	$U['style']="color:#$colour;";
	$U['timestamps']=get_setting('timestamps');
	$U['embed']=1;
	$U['incognito']=0;
	$U['status']=1;
	$U['nocache']=get_setting('sortupdown');
	if($U['nocache']){
		$U['nocache_old']=0;
	}else{
		$U['nocache_old']=1;
	}
	$U['loginfails']=0;
	$U['tz']=get_setting('defaulttz');
	$U['eninbox']=0;
	$U['sortupdown']=get_setting('sortupdown');
	$U['hidechatters']=get_setting('hidechatters');
	$U['passhash']=password_hash($password, PASSWORD_DEFAULT);
	$U['entry']=$U['lastpost']=time();
	$U['exiting']=0;
}


function validate_input() : string {
	global $U, $db;
	$inbox=false;
	$maxmessage=get_setting('maxmessage');
	$message=mb_substr($_POST['message'], 0, $maxmessage);
	$rejected=mb_substr($_POST['message'], $maxmessage);
	if(!isset($_POST['postid'])){ // auto-kick spammers not setting a postid
		kick_chatter([$U['nickname']], '', false);
	}
	if($U['postid'] !== $_POST['postid'] || (time() - $U['lastpost']) <= 1){ // reject bogus messages
		$rejected=$_POST['message'];
		$message='';
	}
	if(!empty($rejected)){
		$rejected=trim($rejected);
		$rejected=htmlspecialchars($rejected);
	}
	$message=htmlspecialchars($message);
	$message=preg_replace("/(\r?\n|\r\n?)/u", '<br>', $message);
	if(isset($_POST['multi'])){
		$message=preg_replace('/\s*<br>/u', '<br>', $message);
		$message=preg_replace('/<br>(<br>)+/u', '<br><br>', $message);
		$message=preg_replace('/<br><br>\s*$/u', '<br>', $message);
		$message=preg_replace('/^<br>\s*$/u', '', $message);
	}else{
		$message=str_replace('<br>', ' ', $message);
	}
	$message=trim($message);
	$message=preg_replace('/\s+/u', ' ', $message);
	$recipient='';
	if($_POST['sendto']==='s *'){
		$poststatus=1;
		$displaysend=sprintf(get_setting('msgsendall'), style_this(htmlspecialchars($U['nickname']), $U['style']));
	}elseif($_POST['sendto']==='s ?' && $U['status']>=3){
		$poststatus=3;
		$displaysend=sprintf(get_setting('msgsendmem'), style_this(htmlspecialchars($U['nickname']), $U['style']));
	}elseif($_POST['sendto']==='s %' && $U['status']>=5){
		$poststatus=5;
		$displaysend=sprintf(get_setting('msgsendmod'), style_this(htmlspecialchars($U['nickname']), $U['style']));
	}elseif($_POST['sendto']==='s _' && $U['status']>=6){
		$poststatus=6;
		$displaysend=sprintf(get_setting('msgsendadm'), style_this(htmlspecialchars($U['nickname']), $U['style']));
	}elseif($_POST['sendto'] === $U['nickname']){ // message to yourself?
		return '';
	}else{ // known nick in room?
		if(get_setting('disablepm')){
			//PMs disabled
			return '';
		}
		$stmt=$db->prepare('SELECT null FROM ' . PREFIX . 'ignored WHERE (ignby=? AND ign=?) OR (ign=? AND ignby=?);');
		$stmt->execute([$_POST['sendto'], $U['nickname'], $_POST['sendto'], $U['nickname']]);
		if($stmt->fetch(PDO::FETCH_NUM)){
			//ignored
			return '';
		}
		$stmt=$db->prepare('SELECT s.style, 0 AS inbox FROM ' . PREFIX . 'sessions AS s LEFT JOIN ' . PREFIX . 'members AS m ON (m.nickname=s.nickname) WHERE s.nickname=? AND (s.incognito=0 OR (m.eninbox!=0 AND m.eninbox<=?));');
		$stmt->execute([$_POST['sendto'], $U['status']]);
		if(!$tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
			$stmt=$db->prepare('SELECT style, 1 AS inbox FROM ' . PREFIX . 'members WHERE nickname=? AND eninbox!=0 AND eninbox<=?;');
			$stmt->execute([$_POST['sendto'], $U['status']]);
			if(!$tmp=$stmt->fetch(PDO::FETCH_ASSOC)){
				//nickname left or disabled offline inbox for us
				return '';
			}
		}
		$recipient=$_POST['sendto'];
		$poststatus=9;
		$displaysend=sprintf(get_setting('msgsendprv'), style_this(htmlspecialchars($U['nickname']), $U['style']), style_this(htmlspecialchars($recipient), $tmp['style']));
		$inbox=$tmp['inbox'];
	}
	if($poststatus!==9 && preg_match('~^/me~iu', $message)){
		$displaysend=style_this(htmlspecialchars("$U[nickname] "), $U['style']);
		$message=preg_replace("~^/me\s?~iu", '', $message);
	}
	$message=apply_filter($message, $poststatus, $U['nickname']);
	$message=create_hotlinks($message);
	$message=apply_linkfilter($message);
	if(isset($_FILES['file']) && get_setting('enfileupload')>0 && get_setting('enfileupload')<=$U['status']){
		if($_FILES['file']['error']===UPLOAD_ERR_OK && $_FILES['file']['size']<=(1024*get_setting('maxuploadsize'))){
			$hash=sha1_file($_FILES['file']['tmp_name']);
			$name=htmlspecialchars($_FILES['file']['name']);
			$message=sprintf(get_setting('msgattache'), "<a class=\"attachement\" href=\"$_SERVER[SCRIPT_NAME]?action=download&amp;id=$hash\" target=\"_blank\">$name</a>", $message);
		}
	}
	if(add_message($message, $recipient, $U['nickname'], (int) $U['status'], $poststatus, $displaysend, $U['style'])){
		$U['lastpost']=time();
		try {
			$U[ 'postid' ] = bin2hex( random_bytes( 3 ) );
		} catch(Exception $e) {
			$U['postid'] = substr(time(), -6);
		}
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'sessions SET lastpost=?, postid=? WHERE session=?;');
		$stmt->execute([$U['lastpost'], $U['postid'], $U['session']]);
		$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'messages WHERE poster=? ORDER BY id DESC LIMIT 1;');
		$stmt->execute([$U['nickname']]);
		$id=$stmt->fetch(PDO::FETCH_NUM);
		if($inbox && $id){
			$newmessage=[
				'postdate'	=>time(),
				'poster'	=>$U['nickname'],
				'recipient'	=>$recipient,
				'text'		=>"<span class=\"usermsg\">$displaysend".style_this($message, $U['style']).'</span>'
			];
			if(MSGENCRYPTED){
				try {
					$newmessage[ 'text' ] = base64_encode( sodium_crypto_aead_aes256gcm_encrypt( $newmessage[ 'text' ], '', AES_IV, ENCRYPTKEY ) );
				} catch (SodiumException $e){
					send_error($e->getMessage());
				}
			}
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'inbox (postdate, postid, poster, recipient, text) VALUES(?, ?, ?, ?, ?)');
			$stmt->execute([$newmessage['postdate'], $id[0], $newmessage['poster'], $newmessage['recipient'], $newmessage['text']]);
		}
		if(isset($hash) && $id){
			if(function_exists('mime_content_type')){
				$type = mime_content_type($_FILES['file']['tmp_name']);
			}elseif(!empty($_FILES['file']['type']) && preg_match('~^[a-z0-9/\-.+]*$~i', $_FILES['file']['type'])){
				$type = $_FILES['file']['type'];
			}else{
				$type = 'application/octet-stream';
			}
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'files (postid, hash, filename, type, data) VALUES (?, ?, ?, ?, ?);');
			$stmt->execute([$id[0], $hash, str_replace('"', '\"', $_FILES['file']['name']), $type, base64_encode(file_get_contents($_FILES['file']['tmp_name']))]);
			unlink($_FILES['file']['tmp_name']);
		}
	}
	return $rejected;
}

function apply_filter(string $message, int $poststatus, string $nickname) : string {
	global $U, $session;
	$message=str_replace('<br>', "\n", $message);
	$message=apply_mention($message);
	$filters=get_filters();
	foreach($filters as $filter){
		if($poststatus!==9 || !$filter['allowinpm']){
			if($filter['cs']){
				$message=preg_replace("/$filter[match]/u", $filter['replace'], $message, -1, $count);
			}else{
				$message=preg_replace("/$filter[match]/iu", $filter['replace'], $message, -1, $count);
			}
		}
		if(isset($count) && $count>0 && $filter['kick'] && ($U['status']<5 || get_setting('filtermodkick'))){
			kick_chatter([$nickname], $filter['replace'], false);
			setcookie(COOKIENAME, false);
			$session = '';
			send_error(_('You have been kicked!')."<br>$filter[replace]");
		}
	}
	$message=str_replace("\n", '<br>', $message);
	return $message;
}

function apply_linkfilter(string $message) : string {
	$filters=get_linkfilters();
	foreach($filters as $filter){
		$message=preg_replace_callback("/<a href=\"([^\"]+)\" target=\"_blank\" rel=\"noreferrer noopener\">([^<]*)<\/a>/iu",
			function ($matched) use(&$filter){
				return "<a href=\"$matched[1]\" target=\"_blank\" rel=\"noreferrer noopener\">".preg_replace("/$filter[match]/iu", $filter['replace'], $matched[2]).'</a>';
			}
		, $message);
	}
	$redirect=get_setting('redirect');
	if(get_setting('imgembed')){
		$message=preg_replace_callback('/\[img]\s?<a href="([^"]+)" target="_blank" rel="noreferrer noopener">([^<]*)<\/a>/iu',
			function ($matched){
				return str_ireplace('[/img]', '', "<br><a href=\"$matched[1]\" target=\"_blank\" rel=\"noreferrer noopener\"><img src=\"$matched[1]\" rel=\"noreferrer\" loading=\"lazy\"></a><br>");
			}
		, $message);
	}
	if(empty($redirect)){
		$redirect="$_SERVER[SCRIPT_NAME]?action=redirect&amp;url=";
	}
	if(get_setting('forceredirect')){
		$message=preg_replace_callback('/<a href="([^"]+)" target="_blank" rel="noreferrer noopener">([^<]*)<\/a>/u',
			function ($matched) use($redirect){
				return "<a href=\"$redirect".rawurlencode($matched[1])."\" target=\"_blank\" rel=\"noreferrer noopener\">$matched[2]</a>";
			}
		, $message);
	}elseif(preg_match_all('/<a href="([^"]+)" target="_blank" rel="noreferrer noopener">([^<]*)<\/a>/u', $message, $matches)){
		foreach($matches[1] as $match){
			if(!preg_match('~^http(s)?://~u', $match)){
				$message=preg_replace_callback('/<a href="('.preg_quote($match, '/').')\" target=\"_blank\" rel=\"noreferrer noopener\">([^<]*)<\/a>/u',
					function ($matched) use($redirect){
						return "<a href=\"$redirect".rawurlencode($matched[1])."\" target=\"_blank\" rel=\"noreferrer noopener\">$matched[2]</a>";
					}
				, $message);
			}
		}
	}
	return $message;
}

function create_hotlinks(string $message) : string {
	//Make hotlinks for URLs, redirect through dereferrer script to prevent session leakage
	// 1. all explicit schemes with whatever xxx://yyyyyyy
	$message=preg_replace('~(^|[^\w"])(\w+://[^\s<>]+)~iu', "$1<<$2>>", $message);
	// 2. valid URLs without scheme:
	$message=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d*)?/[^\s<>]*)(?![^<>]*>)~iu', "<<$1>>", $message); // server/path given
	$message=preg_replace('~((?:[^\s<>]*:[^\s<>]*@)?[a-z0-9\-]+(?:\.[a-z0-9\-]+)+:\d+)(?![^<>]*>)~iu', "<<$1>>", $message); // server:port given
	$message=preg_replace('~([^\s<>]*:[^\s<>]*@[a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?::\d+)?)(?![^<>]*>)~iu', "<<$1>>", $message); // au:th@server given
	// 3. likely servers without any hints but not filenames like *.rar zip exe etc.
	$message=preg_replace('~((?:[a-z0-9\-]+\.)*(?:[a-z2-7]{55}d|[a-z2-7]{16})\.onion)(?![^<>]*>)~iu', "<<$1>>", $message);// *.onion
	$message=preg_replace('~([a-z0-9\-]+(?:\.[a-z0-9\-]+)+(?:\.(?!rar|zip|exe|gz|7z|bat|doc)[a-z]{2,}))(?=[^a-z0-9\-.]|$)(?![^<>]*>)~iu', "<<$1>>", $message);// xxx.yyy.zzz
	// Convert every <<....>> into proper links:
	$message=preg_replace_callback('/<<([^<>]+)>>/u',
		function ($matches){
			if(strpos($matches[1], '://')===false){
				return "<a href=\"http://$matches[1]\" target=\"_blank\" rel=\"noreferrer noopener\">$matches[1]</a>";
			}else{
				return "<a href=\"$matches[1]\" target=\"_blank\" rel=\"noreferrer noopener\">$matches[1]</a>";
			}
		}
	, $message);
	return $message;
}

function apply_mention(string $message) : string {
	return preg_replace_callback('/@([^\s]+)/iu', function ($matched){
		global $db;
		$nick=htmlspecialchars_decode($matched[1]);
		$rest='';
		for($i=0;$i<=3;++$i){
			//match case-sensitive present nicknames
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'sessions WHERE nickname=?;');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			//match case-insensitive present nicknames
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'sessions WHERE LOWER(nickname)=LOWER(?);');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			//match case-sensitive members
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'members WHERE nickname=?;');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			//match case-insensitive members
			$stmt=$db->prepare('SELECT style FROM ' . PREFIX . 'members WHERE LOWER(nickname)=LOWER(?);');
			$stmt->execute([$nick]);
			if($tmp=$stmt->fetch(PDO::FETCH_NUM)){
				return style_this(htmlspecialchars("@$nick"), $tmp[0]).$rest;
			}
			if(strlen($nick)===1){
				break;
			}
			$rest=mb_substr($nick, -1).$rest;
			$nick=mb_substr($nick, 0, -1);
		}
		return $matched[0];
	}, $message);
}

function add_message(string $message, string $recipient, string $poster, int $delstatus, int $poststatus, string $displaysend, string$style) : bool {
	global $db;
	if($message===''){
		return false;
	}
	$newmessage=[
		'postdate'	=>time(),
		'poststatus'	=>$poststatus,
		'poster'	=>$poster,
		'recipient'	=>$recipient,
		'text'		=>"<span class=\"usermsg\">$displaysend".style_this($message, $style).'</span>',
		'delstatus'	=>$delstatus
	];
	//prevent posting the same message twice, if no other message was posted in-between.
	$stmt=$db->prepare('SELECT id FROM ' . PREFIX . 'messages WHERE poststatus=? AND poster=? AND recipient=? AND text=? AND id IN (SELECT * FROM (SELECT id FROM ' . PREFIX . 'messages ORDER BY id DESC LIMIT 1) AS t);');
	$stmt->execute([$newmessage['poststatus'], $newmessage['poster'], $newmessage['recipient'], $newmessage['text']]);
	if($stmt->fetch(PDO::FETCH_NUM)){
		return false;
	}
	write_message($newmessage);
	return true;
}

function add_system_message(string $mes, string $doer): void
{
	if($mes===''){
		return;
	}
	if($doer==='' || !get_setting('namedoers')){
		$sysmessage=[
			'postdate'	=>time(),
			'poststatus'	=>4,
			'poster'	=>'',
			'recipient'	=>'',
			'text'		=>"$mes",
			'delstatus'	=>4
		];

	} else {
		$sysmessage=[
			'postdate'	=>time(),
			'poststatus'	=>4,
			'poster'	=>'',
			'recipient'	=>'',
			'text'		=>"$mes ($doer)",
			'delstatus'	=>4
		];
	}
	write_message($sysmessage);
}

function write_message(array $message): void
{
	global $db;
	if(MSGENCRYPTED){
		try {
			$message['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($message['text'], '', AES_IV, ENCRYPTKEY));
		} catch (SodiumException $e){
			send_error($e->getMessage());
		}
	}
	$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'messages (postdate, poststatus, poster, recipient, text, delstatus) VALUES (?, ?, ?, ?, ?, ?);');
	$stmt->execute([$message['postdate'], $message['poststatus'], $message['poster'], $message['recipient'], $message['text'], $message['delstatus']]);
	if($message['poststatus']<9 && get_setting('sendmail')){
		$subject='New Chat message';
		$headers='From: '.get_setting('mailsender')."\r\nX-Mailer: PHP/".phpversion()."\r\nContent-Type: text/html; charset=UTF-8\r\n";
		$body='<html><body style="background-color:#'.get_setting('colbg').';color:#'.get_setting('coltxt').";\">$message[text]</body></html>";
		mail(get_setting('mailreceiver'), $subject, $body, $headers);
	}
}

function clean_room(): void
{
	global $U, $db;
	$db->query('DELETE FROM ' . PREFIX . 'messages;');
	add_system_message(sprintf(get_setting('msgclean'), get_setting('chatname')), $U['nickname']);
}

function clean_selected(int $status, string $nick): void
{
	global $db;
	if(isset($_POST['mid'])){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id=? AND (poster=? OR recipient=? OR (poststatus<? AND delstatus<?));');
		foreach($_POST['mid'] as $mid){
			$stmt->execute([$mid, $nick, $nick, $status, $status]);
		}
	}
}

function clean_inbox_selected(): void
{
	global $U, $db;
	if(isset($_POST['mid'])){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'inbox WHERE id=? AND recipient=?;');
		foreach($_POST['mid'] as $mid){
			$stmt->execute([$mid, $U['nickname']]);
		}
	}
}


function del_all_messages(string $nick, int $entry): void
{
	global $db, $U;
	$globally = (bool) get_setting('postbox_delete_globally');
	if($globally && $U['status'] > 4){
		$stmt = $db->prepare( 'DELETE FROM ' . PREFIX . 'messages;' );
		$stmt->execute();
	} else {
		if ( $nick === '' ) {
			$nick = $U[ 'nickname' ];
		}
		$stmt = $db->prepare( 'DELETE FROM ' . PREFIX . 'messages WHERE poster=? AND postdate>=?;' );
		$stmt->execute( [ $nick, $entry ] );
		$stmt = $db->prepare( 'DELETE FROM ' . PREFIX . 'inbox WHERE poster=? AND postdate>=?;' );
		$stmt->execute( [ $nick, $entry ] );
	}
}

function del_last_message(): void
{
	global $U, $db;
	if($U['status']>1){
		$entry=0;
	}else{
		$entry=$U['entry'];
	}
	$globally = (bool) get_setting('postbox_delete_globally');
	if($globally && $U['status'] > 4) {
		$stmt = $db->prepare( 'SELECT id FROM ' . PREFIX . 'messages WHERE postdate>=? ORDER BY id DESC LIMIT 1;' );
		$stmt->execute( [ $entry ] );
	} else {
		$stmt = $db->prepare( 'SELECT id FROM ' . PREFIX . 'messages WHERE poster=? AND postdate>=? ORDER BY id DESC LIMIT 1;' );
		$stmt->execute( [ $U[ 'nickname' ], $entry ] );
	}
	if ( $id = $stmt->fetch( PDO::FETCH_NUM ) ) {
		$stmt = $db->prepare( 'DELETE FROM ' . PREFIX . 'messages WHERE id=?;' );
		$stmt->execute( $id );
		$stmt = $db->prepare( 'DELETE FROM ' . PREFIX . 'inbox WHERE postid=?;' );
		$stmt->execute( $id );
	}
}
function print_messages(int $delstatus=0): void
{
    global $U, $db;

    $dateformat = get_setting('dateformat');
    $removeEmbed = !$U['embed'] && get_setting('imgembed');
    $timestamps = $U['timestamps'] && !empty($dateformat);
    $direction = $U['sortupdown'] ? 'ASC' : 'DESC';
    $entry = $U['status'] > 1 ? 0 : $U['entry'];

    echo '<div id="messages">';
    if ($delstatus > 0) {
        $stmt = $db->prepare('SELECT postdate, id, text, poster FROM ' . PREFIX . 'messages WHERE ' .
        "(poststatus<? AND delstatus<?) OR ((poster=? OR recipient=?) AND postdate>=?) ORDER BY id $direction;");
        $stmt->execute([$U['status'], $delstatus, $U['nickname'], $U['nickname'], $entry]);
        while ($message = $stmt->fetch(PDO::FETCH_ASSOC)) {
            prepare_message_print($message, $removeEmbed);
            echo "<div class=\"msg\" id=\"message-$message[id]\"><label><input type=\"checkbox\" name=\"mid[]\" value=\"$message[id]\">";

            if (strpos($message['text'], '@' . $U['nickname']) !== false && (time() - $message['postdate']) <= 10) {
                echo '<audio autoplay><source src="music.mp3" type="audio/mpeg"></audio>';
            }

            if ($timestamps) {
				if ($message['poststatus'] != 4) {
					if ($message['poster'] === $U['nickname'] && (time() - $message['postdate']) <= 600) {
						echo form('delete','postbox') . hidden('what', 'hilite') . hidden('message_id', $message['id']) . submit('❌', 'style="color: red; float: right; border: none; background: none; cursor: pointer; padding: 0; font-size: 1em; font-weight: bold; text-shadow: 1px 1px 2px #000;"') . '</form>';
					} elseif ($U['status'] >= 5) {
						echo form('delete','postbox') . hidden('what', 'hilite') . hidden('message_id', $message['id']) . submit('❌', 'style="color: red; float: right; border: none; background: none; cursor: pointer; padding: 0; font-size: 1em; font-weight: bold; text-shadow: 1px 1px 2px #000;"') . '</form>';
					}
                }
                echo '<small>' . date($dateformat, $message['postdate']) . ' - </small>';
            }
            echo " $message[text]</label></div>";
        }
    } else {
        $stmt = $db->prepare('SELECT id, postdate, poststatus, text, poster FROM ' . PREFIX . 'messages WHERE (poststatus<=? OR poststatus=4 OR ' .
        '(poststatus=9 AND ( (poster=? AND recipient NOT IN (SELECT ign FROM ' . PREFIX . 'ignored WHERE ignby=?) ) OR recipient=?) AND postdate>=?)' .
        ') AND poster NOT IN (SELECT ign FROM ' . PREFIX . "ignored WHERE ignby=?) ORDER BY id $direction;");
        $stmt->execute([$U['status'], $U['nickname'], $U['nickname'], $U['nickname'], $entry, $U['nickname']]);
        while ($message = $stmt->fetch(PDO::FETCH_ASSOC)) {
            prepare_message_print($message, $removeEmbed);
            echo "<div class=\"msg\" id=\"message-$message[id]\">";

            if (strpos($message['text'], '@' . $U['nickname']) !== false && (time() - $message['postdate']) <= 10) {
                echo '<audio autoplay><source src="music.mp3" type="audio/mpeg"></audio>';
            }

            if ($timestamps) {
				if ($message['poststatus'] != 4) {
					if ($message['poster'] === $U['nickname'] && (time() - $message['postdate']) <= 600) {
						echo form('delete','post') . hidden('what', 'hilite') . hidden('message_id', $message['id']) . submit('❌', 'style="color: red; float: right; border: none; background: none; cursor: pointer; padding: 0; font-size: 1em; font-weight: bold; text-shadow: 1px 1px 2px #000;"') . '</form>';
					} elseif ($U['status'] >= 5) {
						echo form('delete','post') . hidden('what', 'hilite') . hidden('message_id', $message['id']) . submit('❌', 'style="color: red; float: right; border: none; background: none; cursor: pointer; padding: 0; font-size: 1em; font-weight: bold; text-shadow: 1px 1px 2px #000;"') . '</form>';
					}
                }
                echo '<small>' . date($dateformat, $message['postdate']) . ' - </small>';
            }
            echo " $message[text]</label></div>";
        }
    }
    echo '</div>';
}

function prepare_message_print(array &$message, bool $removeEmbed): void
{
	if(MSGENCRYPTED){
		try {
			$message['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($message['text']), null, AES_IV, ENCRYPTKEY);
		} catch (SodiumException $e){
			send_error($e->getMessage());
		}
	}
	if($removeEmbed){
		$message['text']=preg_replace_callback('/<img src="([^"]+)" rel="noreferrer" loading="lazy"><\/a>/u',
			function ($matched){
				return "$matched[1]</a>";
			}
		, $message['text']);
	}
}

// this and that
function send_headers(): void
{
	global $U, $scripts, $styles;
	header('Content-Type: text/html; charset=UTF-8');
	header('Pragma: no-cache');
	header('Cache-Control: no-cache, no-store, must-revalidate, max-age=0, private');
	header('Expires: 0');
	header('Referrer-Policy: no-referrer');
	header("Permissions-Policy: accelerometer=(), autoplay=(), camera=(), cross-origin-isolated=(), display-capture=(), encrypted-media=(), fullscreen=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), midi=(), payment=(), picture-in-picture=(), publickey-credentials-get=(), screen-wake-lock=(), sync-xhr=(), usb=(), xr-spatial-tracking=(), clipboard-read=(), clipboard-write=(), gamepad=(), hid=(), idle-detection=(), serial=(), interest-cohort=(), otp-credentials=()");
	if(!get_setting('imgembed') || !($U['embed'] ?? false)){
		header("Cross-Origin-Embedder-Policy: require-corp");
	}
	header("Cross-Origin-Opener-Policy: same-origin");
	header("Cross-Origin-Resource-Policy: same-origin");
	$style_hashes = '';
	foreach($styles as $style) {
		$style_hashes .= " 'sha256-".base64_encode(hash('sha256', $style, true))."'";
	}
	$script_hashes = '';
	foreach($scripts as $script) {
		$script_hashes .= " 'sha256-".base64_encode(hash('sha256', $script, true))."'";
	}
	header("Content-Security-Policy: base-uri 'self'; default-src 'none'; font-src 'self'; form-action 'self'; frame-ancestors 'self'; frame-src 'self'; img-src * data:; media-src * data:; style-src 'self' 'unsafe-inline';" . (empty($script_hashes) ? '' : " script-src $script_hashes;")); // $style_hashes"); //we can add computed hashes as soon as all inline css is moved to default css
	header('X-Content-Type-Options: nosniff');
	header('X-Frame-Options: sameorigin');
	header('X-XSS-Protection: 1; mode=block');
	if($_SERVER['REQUEST_METHOD'] === 'HEAD'){
		exit; // headers sent, no further processing needed
	}
}

function save_setup(array $C): void
{
	global $db;
	//sanity checks and escaping
	foreach($C['msg_settings'] as $setting => $title){
		$_POST[$setting]=htmlspecialchars($_POST[$setting]);
	}
	foreach($C['number_settings'] as $setting => $title){
		settype($_POST[$setting], 'int');
	}
	foreach($C['colour_settings'] as $setting => $title){
		if(preg_match('/^#([a-f0-9]{6})$/i', $_POST[$setting], $match)){
			$_POST[$setting]=$match[1];
		}else{
			unset($_POST[$setting]);
		}
	}
	settype($_POST['guestaccess'], 'int');
	if(!preg_match('/^[01234]$/', $_POST['guestaccess'])){
		unset($_POST['guestaccess']);
	}else{
		change_guest_access(intval($_POST['guestaccess']));
	}
	settype($_POST['englobalpass'], 'int');
	settype($_POST['captcha'], 'int');
	settype($_POST['dismemcaptcha'], 'int');
	settype($_POST['guestreg'], 'int');
	if(isset($_POST['defaulttz'])){
		$tzs=timezone_identifiers_list();
		if(!in_array($_POST['defaulttz'], $tzs)){
			unset($_POST['defualttz']);
		}
	}
	$_POST['rulestxt']=preg_replace("/(\r?\n|\r\n?)/u", '<br>', $_POST['rulestxt']);
	$_POST['chatname']=htmlspecialchars($_POST['chatname']);
	$_POST['redirect']=htmlspecialchars($_POST['redirect']);
	if($_POST['memberexpire']<5){
		$_POST['memberexpire']=5;
	}
	if($_POST['captchatime']<30){
		$_POST['memberexpire']=30;
	}
	$max_refresh_rate = (int) get_setting('max_refresh_rate');
	$min_refresh_rate = (int) get_setting('min_refresh_rate');
	if($_POST['defaultrefresh']<$min_refresh_rate){
		$_POST['defaultrefresh']=$min_refresh_rate;
	}elseif($_POST['defaultrefresh']>$max_refresh_rate){
		$_POST['defaultrefresh']=$max_refresh_rate;
	}
	if($_POST['maxname']<1){
		$_POST['maxname']=1;
	}elseif($_POST['maxname']>50){
		$_POST['maxname']=50;
	}
	if($_POST['maxmessage']<1){
		$_POST['maxmessage']=1;
	}elseif($_POST['maxmessage']>16000){
		$_POST['maxmessage']=16000;
	}
		if($_POST['numnotes']<1){
		$_POST['numnotes']=1;
	}
	if(!valid_regex($_POST['nickregex'])){
		unset($_POST['nickregex']);
	}
	if(!valid_regex($_POST['passregex'])){
		unset($_POST['passregex']);
	}
	//save values
	foreach($C['settings'] as $setting){
		if(isset($_POST[$setting])){
			update_setting($setting, $_POST[$setting]);
		}
	}
}

function change_guest_access(int $guest_access) : void {
	global $db;
	if($guest_access === 4){
		$db->exec('DELETE FROM ' . PREFIX . 'sessions WHERE status<7;');
	}elseif($guest_access === 0){
		$db->exec('DELETE FROM ' . PREFIX . 'sessions WHERE status<3;');
	}
}

function set_default_tz(): void
{
	global $U;
	if(isset($U['tz'])){
		date_default_timezone_set($U['tz']);
	}else{
		date_default_timezone_set(get_setting('defaulttz'));
	}
}

function valid_admin() : bool {
	global $U;
	parse_sessions();
	if(!isset($U['session']) && isset($_POST['nick']) && isset($_POST['pass'])){
		create_session(true, $_POST['nick'], $_POST['pass']);
	}
	if(isset($U['status'])){
		if($U['status']>=7){
			return true;
		}
		send_access_denied();
	}
	return false;
}

function valid_nick(string $nick) : bool{
	$len=mb_strlen($nick);
	if($len<1 || $len>get_setting('maxname')){
		return false;
	}
	return preg_match('/'.get_setting('nickregex').'/u', $nick);
}

function valid_pass(string $pass) : bool {
	if(mb_strlen($pass)<get_setting('minpass')){
		return false;
	}
	return preg_match('/'.get_setting('passregex').'/u', $pass);
}

function valid_regex(string &$regex) : bool {
	$regex=preg_replace('~(^|[^\\\\])/~', "$1\/u", $regex); // Escape "/" if not yet escaped
	return (@preg_match("/$_POST[match]/u", '') !== false);
}

function get_timeout(int $lastpost, int $expire): void
{
	$s=($lastpost+60*$expire)-time();
	$m=floor($s/60);
	$s%=60;
	if($s<10){
		$s="0$s";
	}
	if($m>60){
		$h=floor($m/60);
		$m%=60;
		if($m<10){
			$m="0$m";
		}
		echo "$h:$m:$s";
	}else{
		echo "$m:$s";
	}
}

function print_colours(): void
{
	// Prints a short list with selected named HTML colours and filters out illegible text colours for the given background.
	// It's a simple comparison of weighted grey values. This is not very accurate but gets the job done well enough.
	// name=>[colour, greyval(colour), translated name]
	$colours=[
		'Beige'=>['F5F5DC', 242.25, _('Beige')],
		'Black'=>['000000', 0, _('Black')],
		'Blue'=>['0000FF', 28.05, _('Blue')],
		'BlueViolet'=>['8A2BE2', 91.63, _('Blue violet')],
		'Brown'=>['A52A2A', 78.9, _('Brown')],
		'Cyan'=>['00FFFF', 178.5, _('Cyan')],
		'DarkBlue'=>['00008B', 15.29, _('Dark blue')],
		'DarkGreen'=>['006400', 59, _('Dark green')],
		'DarkRed'=>['8B0000', 41.7, _('Dark red')],
		'DarkViolet'=>['9400D3', 67.61, _('Dark violet')],
		'DeepSkyBlue'=>['00BFFF', 140.74, _('Sky blue')],
		'Gold'=>['FFD700', 203.35, _('Gold')],
		'Grey'=>['808080', 128, _('Grey')],
		'Green'=>['008000', 75.52, _('Green')],
		'HotPink'=>['FF69B4', 158.25, _('Hot pink')],
		'Indigo'=>['4B0082', 36.8, _('Indigo')],
		'LightBlue'=>['ADD8E6', 204.64, _('Light blue')],
		'LightGreen'=>['90EE90', 199.46, _('Light green')],
		'LimeGreen'=>['32CD32', 141.45, _('Lime green')],
		'Magenta'=>['FF00FF', 104.55, _('Magenta')],
		'Olive'=>['808000', 113.92, _('Olive')],
		'Orange'=>['FFA500', 173.85, _('Orange')],
		'OrangeRed'=>['FF4500', 117.21, _('Orange red')],
		'Purple'=>['800080', 52.48, _('Purple')],
		'Red'=>['FF0000', 76.5, _('Red')],
		'RoyalBlue'=>['4169E1', 106.2, _('Royal blue')],
		'SeaGreen'=>['2E8B57', 105.38, _('Sea green')],
		'Sienna'=>['A0522D', 101.33, _('Sienna')],
		'Silver'=>['C0C0C0', 192, _('Silver')],
		'Tan'=>['D2B48C', 184.6, _('Tan')],
		'Teal'=>['008080', 89.6, _('Teal')],
		'Violet'=>['EE82EE', 174.28, _('Violet')],
		'White'=>['FFFFFF', 255, _('White')],
		'Yellow'=>['FFFF00', 226.95, _('Yellow')],
		'YellowGreen'=>['9ACD32', 172.65, _('Yellow green')],
	];
	$greybg=greyval(get_setting('colbg'));
	foreach($colours as $name=>$colour){
		if(abs($greybg-$colour[1])>75){
			echo "<option value=\"$colour[0]\" style=\"color:#$colour[0];\">$colour[2]</option>";
		}
	}
}

function greyval(string $colour) : string {
	return hexdec(substr($colour, 0, 2))*.3+hexdec(substr($colour, 2, 2))*.59+hexdec(substr($colour, 4, 2))*.11;
}

function style_this(string $text, string $styleinfo) : string {
	return "<span style=\"$styleinfo\">$text</span>";
}

function check_init() : bool {
	global $db;
	try {
		$db->query( 'SELECT null FROM ' . PREFIX . 'settings LIMIT 1;' );
	} catch (Exception $e){
		return false;
	}
	return true;
}

// run every minute doing various database cleanup task
function cron(): void
{
	global $db;
	$time=time();
	if(get_setting('nextcron')>$time){
		return;
	}
	update_setting('nextcron', $time+10);
	// delete old sessions
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'sessions WHERE (status<=2 AND lastpost<(?-60*(SELECT value FROM ' . PREFIX . "settings WHERE setting='guestexpire'))) OR (status>2 AND lastpost<(?-60*(SELECT value FROM " . PREFIX . "settings WHERE setting='memberexpire'))) OR (status<3 AND exiting>0 AND lastpost<(?-(SELECT value FROM " . PREFIX . "settings WHERE setting='exitwait')));");
	$stmt->execute([$time, $time, $time]);
	// delete old messages
	$limit=get_setting('messagelimit');
	$stmt=$db->query('SELECT id FROM ' . PREFIX . "messages WHERE poststatus=1 OR poststatus=4 ORDER BY id DESC LIMIT 1 OFFSET $limit;");
	if($id=$stmt->fetch(PDO::FETCH_NUM)){
		$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id<=?;');
		$stmt->execute($id);
	}
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'messages WHERE id IN (SELECT * FROM (SELECT id FROM ' . PREFIX . 'messages WHERE postdate<(?-60*(SELECT value FROM ' . PREFIX . "settings WHERE setting='messageexpire'))) AS t);");
	$stmt->execute([$time]);
	// delete expired ignored people
	$result=$db->query('SELECT id FROM ' . PREFIX . 'ignored WHERE ign NOT IN (SELECT nickname FROM ' . PREFIX . 'sessions UNION SELECT nickname FROM ' . PREFIX . 'members UNION SELECT poster FROM ' . PREFIX . 'messages) OR ignby NOT IN (SELECT nickname FROM ' . PREFIX . 'sessions UNION SELECT nickname FROM ' . PREFIX . 'members UNION SELECT poster FROM ' . PREFIX . 'messages);');
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'ignored WHERE id=?;');
	while($tmp=$result->fetch(PDO::FETCH_NUM)){
		$stmt->execute($tmp);
	}
	// delete files that do not belong to any message
	$result=$db->query('SELECT id FROM ' . PREFIX . 'files WHERE postid NOT IN (SELECT id FROM ' . PREFIX . 'messages UNION SELECT postid FROM ' . PREFIX . 'inbox);');
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'files WHERE id=?;');
	while($tmp=$result->fetch(PDO::FETCH_NUM)){
		$stmt->execute($tmp);
	}
	// delete old notes
	$limit=get_setting('numnotes');
	$to_keep = [];
	$stmt = $db->query('SELECT id FROM ' . PREFIX . "notes WHERE type=0 ORDER BY id DESC LIMIT $limit;");
	while($tmp = $stmt->fetch(PDO::FETCH_ASSOC)){
		$to_keep []= $tmp['id'];
	}
	$stmt = $db->query('SELECT id FROM ' . PREFIX . "notes WHERE type=1 ORDER BY id DESC LIMIT $limit;");
	while($tmp = $stmt->fetch(PDO::FETCH_ASSOC)){
		$to_keep []= $tmp['id'];
	}
	$query = 'DELETE FROM ' . PREFIX . 'notes WHERE type!=2 AND type!=3';
	if(!empty($to_keep)){
		$query .= ' AND id NOT IN (';
		for($i = count($to_keep); $i > 1; --$i){
			$query .= '?, ';
		}
		$query .= '?)';
	}
	$stmt = $db->prepare($query);
	$stmt->execute($to_keep);
	$result=$db->query('SELECT editedby, COUNT(*) AS cnt FROM ' . PREFIX . "notes WHERE type=2 GROUP BY editedby HAVING cnt>$limit;");
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'notes WHERE (type=2 OR type=3) AND editedby=? AND id NOT IN (SELECT * FROM (SELECT id FROM ' . PREFIX . "notes WHERE (type=2 OR type=3) AND editedby=? ORDER BY id DESC LIMIT $limit) AS t);");
	while($tmp=$result->fetch(PDO::FETCH_NUM)){
		$stmt->execute([$tmp[0], $tmp[0]]);
	}
	// delete old captchas
	$stmt=$db->prepare('DELETE FROM ' . PREFIX . 'captcha WHERE time<(?-(SELECT value FROM ' . PREFIX . "settings WHERE setting='captchatime'));");
	$stmt->execute([$time]);
	// delete member associated data of deleted accounts
	$db->query('DELETE FROM ' . PREFIX . 'inbox WHERE recipient NOT IN (SELECT nickname FROM ' . PREFIX . 'members);');
	$db->query('DELETE FROM ' . PREFIX . 'notes WHERE (type=2 OR type=3) AND editedby NOT IN (SELECT nickname FROM ' . PREFIX . 'members);');
}

function destroy_chat(array $C): void
{
	global $db, $memcached, $session;
	setcookie(COOKIENAME, false);
	$session = '';
	print_start('destroy');
	$db->exec('DROP TABLE ' . PREFIX . 'captcha;');
	$db->exec('DROP TABLE ' . PREFIX . 'files;');
	$db->exec('DROP TABLE ' . PREFIX . 'filter;');
	$db->exec('DROP TABLE ' . PREFIX . 'cyber_links;'); // Add this line
	$db->exec('DROP TABLE ' . PREFIX . 'ignored;');
	$db->exec('DROP TABLE ' . PREFIX . 'inbox;');
	$db->exec('DROP TABLE ' . PREFIX . 'linkfilter;');
	$db->exec('DROP TABLE ' . PREFIX . 'members;');
	$db->exec('DROP TABLE ' . PREFIX . 'messages;');
	$db->exec('DROP TABLE ' . PREFIX . 'notes;');
	$db->exec('DROP TABLE ' . PREFIX . 'sessions;');
	$db->exec('DROP TABLE ' . PREFIX . 'settings;');
	if(MEMCACHED){
		$memcached->delete(DBNAME . '-' . PREFIX . 'filter');
		$memcached->delete(DBNAME . '-' . PREFIX . 'linkfilter');
		foreach($C['settings'] as $setting){
			$memcached->delete(DBNAME . '-' . PREFIX . "settings-$setting");
		}
		$memcached->delete(DBNAME . '-' . PREFIX . 'settings-dbversion');
		$memcached->delete(DBNAME . '-' . PREFIX . 'settings-msgencrypted');
		$memcached->delete(DBNAME . '-' . PREFIX . 'settings-nextcron');
	}
	echo '<h2>'._('Successfully destroyed chat').'</h2><br><br><br>';
	echo form('setup').submit(_('Initial Setup')).'</form>'.credit();
	print_end();
}

function init_chat(): void
{
	global $db;
	if(check_init()){
		$suwrite=_('Database tables already exist! To continue, you have to delete these tables manually first.');
		$result=$db->query('SELECT null FROM ' . PREFIX . 'members WHERE status=8;');
		if($result->fetch(PDO::FETCH_NUM)){
			$suwrite=_('A Superadmin already exists!');
		}
	}elseif(!preg_match('/^[a-z0-9]{1,20}$/i', $_POST['sunick'])){
		$suwrite=sprintf(_('Invalid nickname (%1$d characters maximum and has to match the regular expression "%2$s")'), 20, '^[A-Za-z1-9]*$');
	}elseif(mb_strlen($_POST['supass'])<5){
		$suwrite=sprintf(_('Invalid password (At least %1$d characters and has to match the regular expression "%2$s")'), 5, '.*');
	}elseif($_POST['supass']!==$_POST['supassc']){
		$suwrite=_('Password confirmation does not match!');
	}else{
		ignore_user_abort(true);
		set_time_limit(0);
		if(DBDRIVER===0){//MySQL
			$memengine=' ENGINE=InnoDB';
			$diskengine=' ENGINE=InnoDB';
			$charset=' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
			$primary='integer PRIMARY KEY AUTO_INCREMENT';
			$longtext='longtext';
		}elseif(DBDRIVER===1){//PostgreSQL
			$memengine='';
			$diskengine='';
			$charset='';
			$primary='serial PRIMARY KEY';
			$longtext='text';
		}else{//SQLite
			$memengine='';
			$diskengine='';
			$charset='';
			$primary='integer PRIMARY KEY';
			$longtext='text';
		}
		$db->exec('CREATE TABLE ' . PREFIX . "captcha (id $primary, time integer NOT NULL, code char(5) NOT NULL)$memengine$charset;");
		$db->exec('CREATE TABLE ' . PREFIX . "files (id $primary, postid integer NOT NULL UNIQUE, filename varchar(255) NOT NULL, hash char(40) NOT NULL, type varchar(255) NOT NULL, data $longtext NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'files_hash ON ' . PREFIX . 'files(hash);');
		$db->exec('CREATE TABLE ' . PREFIX . "filter (id $primary, filtermatch varchar(255) NOT NULL, filterreplace text NOT NULL, allowinpm smallint NOT NULL, regex smallint NOT NULL, kick smallint NOT NULL, cs smallint NOT NULL)$diskengine$charset;");
		$db->exec('CREATE TABLE ' . PREFIX . "ignored (id $primary, ign varchar(50) NOT NULL, ignby varchar(50) NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'ign ON ' . PREFIX . 'ignored(ign);');
		$db->exec('CREATE INDEX ' . PREFIX . 'ignby ON ' . PREFIX . 'ignored(ignby);');
		$db->exec('CREATE TABLE ' . PREFIX . "members  (id $primary, nickname varchar(50) NOT NULL UNIQUE, passhash varchar(255) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, loginfails integer unsigned NOT NULL DEFAULT 0, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) NOT NULL, nocache smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL, nocache_old smallint NOT NULL)$diskengine$charset;");
		$db->exec('CREATE TABLE ' . PREFIX . "inbox (id $primary, postdate integer NOT NULL, postid integer NOT NULL UNIQUE, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL, FOREIGN KEY (recipient) REFERENCES " . PREFIX . "members(nickname) ON DELETE CASCADE ON UPDATE CASCADE)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
		$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id $primary, filtermatch varchar(255) NOT NULL, filterreplace varchar(255) NOT NULL, regex smallint NOT NULL)$diskengine$charset;");
		$db->exec('CREATE TABLE ' . PREFIX . "messages (id $primary, postdate integer NOT NULL, poststatus smallint NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL, delstatus smallint NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'poster ON ' . PREFIX . 'messages (poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'recipient ON ' . PREFIX . 'messages(recipient);');
		$db->exec('CREATE INDEX ' . PREFIX . 'postdate ON ' . PREFIX . 'messages(postdate);');
		$db->exec('CREATE INDEX ' . PREFIX . 'poststatus ON ' . PREFIX . 'messages(poststatus);');
		$db->exec('CREATE TABLE ' . PREFIX . "notes (id $primary, type smallint NOT NULL, lastedited integer NOT NULL, editedby varchar(50) NOT NULL, text text NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_type ON ' . PREFIX . 'notes(type);');
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_editedby ON ' . PREFIX . 'notes(editedby);');
		$db->exec('CREATE TABLE ' . PREFIX . "sessions (id $primary, session char(32) NOT NULL UNIQUE, nickname varchar(50) NOT NULL UNIQUE, status smallint NOT NULL, refresh smallint NOT NULL, style varchar(255) NOT NULL, lastpost integer NOT NULL, passhash varchar(255) NOT NULL, postid char(6) NOT NULL DEFAULT '000000', useragent varchar(255) NOT NULL, kickmessage varchar(255) DEFAULT '', bgcolour char(6) NOT NULL, entry integer NOT NULL, exiting smallint NOT NULL, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, ip varchar(45) NOT NULL, nocache smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL, nocache_old smallint NOT NULL)$memengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'status ON ' . PREFIX . 'sessions(status);');
		$db->exec('CREATE INDEX ' . PREFIX . 'lastpost ON ' . PREFIX . 'sessions(lastpost);');
		$db->exec('CREATE INDEX ' . PREFIX . 'incognito ON ' . PREFIX . 'sessions(incognito);');
		$db->exec('CREATE TABLE ' . PREFIX . "settings (setting varchar(50) NOT NULL PRIMARY KEY, value text NOT NULL)$diskengine$charset;");
		$db->exec('CREATE TABLE ' . PREFIX . "bad_words (id INT AUTO_INCREMENT PRIMARY KEY, word VARCHAR(255) NOT NULL UNIQUE)$diskengine$charset;");
		$db->exec('CREATE TABLE ' . PREFIX . "cyber_links (id $primary, title VARCHAR(255) NOT NULL, url VARCHAR(255) NOT NULL, description TEXT, section VARCHAR(50) DEFAULT 'General', status TINYINT(1) DEFAULT 1)$diskengine$charset;");
		$settings=[
			['guestaccess', '0'],
			['globalpass', ''],
			['englobalpass', '0'],
			['captcha', '0'],
			['dateformat', 'm-d H:i:s'],
			['rulestxt', ''],
			['msgencrypted', '0'],
			['dbversion', DBVERSION],
			['css', ''],
			['memberexpire', '60'],
			['guestexpire', '15'],
			['kickpenalty', '10'],
			['entrywait', '120'],
			['exitwait', '180'],
			['messageexpire', '14400'],
			['messagelimit', '150'],
			['maxmessage', 2000],
			['captchatime', '600'],
			['colbg', '000000'],
			['coltxt', 'FFFFFF'],
			['maxname', '20'],
			['minpass', '5'],
			['defaultrefresh', '20'],
			['dismemcaptcha', '0'],
			['suguests', '0'],
			['imgembed', '1'],
			['timestamps', '1'],
			['trackip', '0'],
			['captchachars', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'],
			['memkick', '1'],
			['memkickalways', '0'],
			['namedoers', '1'],
			['forceredirect', '0'],
			['redirect', ''],
			['incognito', '1'],
			['chatname', 'My Chat'],
			['topic', ''],
			['msgsendall', _('%s - ')],
			['msgsendmem', _('[M] %s - ')],
			['msgsendmod', _('[Staff] %s - ')],
			['msgsendadm', _('[Admin] %s - ')],
			['msgsendprv', _('[%1$s to %2$s] - ')],
			['msgenter', _('%s entered the chat.')],
			['msgexit', _('%s left the chat.')],
			['msgmemreg', _('%s is now a registered member.')],
			['msgsureg', _('%s is now a registered applicant.')],
			['msgkick', _('%s has been kicked.')],
			['msgmultikick', _('%s have been kicked.')],
			['msgallkick', _('All guests have been kicked.')],
			['msgclean', _('%s has been cleaned.')],
			['numnotes', '3'],
			['mailsender', 'www-data <www-data@localhost>'],
			['mailreceiver', 'Webmaster <webmaster@localhost>'],
			['sendmail', '0'],
			['modfallback', '1'],
			['guestreg', '0'],
			['disablepm', '0'],
			['disabletext', '<h1>'._('Temporarily disabled').'</h1>'],
			['defaulttz', 'UTC'],
			['eninbox', '0'],
			['passregex', '.*'],
			['nickregex', '^[A-Za-z0-9]*$'],
			['externalcss', ''],
			['enablegreeting', '0'],
			['sortupdown', '0'],
			['hidechatters', '0'],
			['enfileupload', '0'],
			['msgattache', '%2$s [%1$s]'],
			['maxuploadsize', '1024'],
			['nextcron', '0'],
			['personalnotes', '1'],
			['publicnotes', '1'],
			['filtermodkick', '0'],
			['metadescription', _('A chat community')],
			['exitingtxt', '&#128682;'], // door emoji
			['sysmessagetxt', 'ℹ️ &nbsp;'],
			['hide_reload_post_box', '0'],
			['hide_reload_messages', '0'],
			['hide_profile', '0'],
			['hide_admin', '0'],
			['hide_notes', '0'],
			['hide_clone', '0'],
			['hide_rearrange', '0'],
			['hide_help', '0'],
			['max_refresh_rate', '150'],
			['min_refresh_rate', '5'],
			['postbox_delete_globally', '0'],
			['allow_js', '0'],
			
		];
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'settings (setting, value) VALUES (?, ?);');
		foreach($settings as $pair){
			$stmt->execute($pair);
		}
		$reg=[
			'nickname'	=>$_POST['sunick'],
			'passhash'	=>password_hash($_POST['supass'], PASSWORD_DEFAULT),
			'status'	=>8,
			'refresh'	=>20,
			'bgcolour'	=>'000000',
			'timestamps'	=>1,
			'style'		=>'color:#FFFFFF;',
			'embed'		=>1,
			'incognito'	=>0,
			'nocache'	=>0,
			'nocache_old'	=>1,
			'tz'		=>'UTC',
			'eninbox'	=>0,
			'sortupdown'	=>0,
			'hidechatters'	=>0,
		];
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, timestamps, style, embed, incognito, nocache, tz, eninbox, sortupdown, hidechatters, nocache_old) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		$stmt->execute([$reg['nickname'], $reg['passhash'], $reg['status'], $reg['refresh'], $reg['bgcolour'], $reg['timestamps'], $reg['style'], $reg['embed'], $reg['incognito'], $reg['nocache'], $reg['tz'], $reg['eninbox'], $reg['sortupdown'], $reg['hidechatters'], $reg['nocache_old']]);
		$suwrite=_('Successfully registered!');
	}
	print_start('init');
	echo '<h2>'._('Initial Setup').'</h2><br><h3>'._('Superadmin Login')."</h3>$suwrite<br><br><br>";
	echo form('setup').submit(_('Go to the Setup-Page')).'</form>'.credit();
	print_end();
}

function update_db(): void
{
	global $db, $memcached;
	$dbversion=(int) get_setting('dbversion');
	$msgencrypted=(bool) get_setting('msgencrypted');
	if($dbversion>=DBVERSION && $msgencrypted===MSGENCRYPTED){
		return;
	}
	ignore_user_abort(true);
	set_time_limit(0);
	if(DBDRIVER===0){//MySQL
		$memengine=' ENGINE=InnoDB';
		$diskengine=' ENGINE=InnoDB';
		$charset=' DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin';
		$primary='integer PRIMARY KEY AUTO_INCREMENT';
		$longtext='longtext';
	}elseif(DBDRIVER===1){//PostgreSQL
		$memengine='';
		$diskengine='';
		$charset='';
		$primary='serial PRIMARY KEY';
		$longtext='text';
	}else{//SQLite
		$memengine='';
		$diskengine='';
		$charset='';
		$primary='integer PRIMARY KEY';
		$longtext='text';
	}
	$msg='';
	if($dbversion<2){
		$db->exec('CREATE TABLE IF NOT EXISTS ' . PREFIX . "ignored (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, ignored varchar(50) NOT NULL, `by` varchar(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8;");
	}
	if($dbversion<3){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('rulestxt', '');");
	}
	if($dbversion<4){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD incognito smallint NOT NULL;');
	}
	if($dbversion<5){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('globalpass', '');");
	}
	if($dbversion<6){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('dateformat', 'm-d H:i:s');");
	}
	if($dbversion<7){
		$db->exec('ALTER TABLE ' . PREFIX . 'captcha ADD code char(5) CHARACTER SET utf8 COLLATE utf8_general_ci NOT NULL;');
	}
	if($dbversion<8){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('captcha', '0'), ('englobalpass', '0');");
		$ga=(int) get_setting('guestaccess');
		if($ga===-1){
			update_setting('guestaccess', 0);
			update_setting('englobalpass', 1);
		}elseif($ga===4){
			update_setting('guestaccess', 1);
			update_setting('englobalpass', 2);
		}
	}
	if($dbversion<9){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting,value) VALUES ('msgencrypted', '0');");
		$db->exec('ALTER TABLE ' . PREFIX . 'settings MODIFY value varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages DROP postid;');
	}
	if($dbversion<10){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('css', ''), ('memberexpire', '60'), ('guestexpire', '15'), ('kickpenalty', '10'), ('entrywait', '120'), ('messageexpire', '14400'), ('messagelimit', '150'), ('maxmessage', 2000), ('captchatime', '600');");
	}
	if($dbversion<11){
		$db->exec('ALTER TABLE ' , PREFIX . 'captcha CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'filter CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'ignored CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'notes CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('ALTER TABLE ' . PREFIX . 'settings CHARACTER SET utf8 COLLATE utf8_bin;');
		$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, `match` varchar(255) NOT NULL, `replace` varchar(255) NOT NULL, regex smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE utf8_bin;");
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD style varchar(255) NOT NULL;');
		$result=$db->query('SELECT * FROM ' . PREFIX . 'members;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'members SET style=? WHERE id=?;');
		$F=load_fonts();
		while($temp=$result->fetch(PDO::FETCH_ASSOC)){
			$style="color:#$temp[colour];";
			if(isset($F[$temp['fontface']])){
				$style.=$F[$temp['fontface']];
			}
			if(strpos($temp['fonttags'], 'i')!==false){
				$style.='font-style:italic;';
			}
			if(strpos($temp['fonttags'], 'b')!==false){
				$style.='font-weight:bold;';
			}
			$stmt->execute([$style, $temp['id']]);
		}
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('colbg', '000000'), ('coltxt', 'FFFFFF'), ('maxname', '20'), ('minpass', '5'), ('defaultrefresh', '20'), ('dismemcaptcha', '0'), ('suguests', '0'), ('imgembed', '1'), ('timestamps', '1'), ('trackip', '0'), ('captchachars', '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ'), ('memkick', '1'), ('forceredirect', '0'), ('redirect', ''), ('incognito', '1');");
	}
	if($dbversion<12){
		$db->exec('ALTER TABLE ' . PREFIX . 'captcha MODIFY code char(5) NOT NULL, DROP INDEX id, ADD PRIMARY KEY (id) USING BTREE;');
		$db->exec('ALTER TABLE ' . PREFIX . 'captcha ENGINE=MEMORY;');
		$db->exec('ALTER TABLE ' . PREFIX . 'filter MODIFY id integer unsigned NOT NULL AUTO_INCREMENT, MODIFY `match` varchar(255) NOT NULL, MODIFY replace varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'ignored MODIFY ignored varchar(50) NOT NULL, MODIFY `by` varchar(50) NOT NULL, ADD INDEX(ignored), ADD INDEX(`by`);');
		$db->exec('ALTER TABLE ' . PREFIX . 'linkfilter MODIFY match varchar(255) NOT NULL, MODIFY replace varchar(255) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'messages MODIFY poster varchar(50) NOT NULL, MODIFY recipient varchar(50) NOT NULL, MODIFY text varchar(20000) NOT NULL, ADD INDEX(poster), ADD INDEX(recipient), ADD INDEX(postdate), ADD INDEX(poststatus);');
		$db->exec('ALTER TABLE ' . PREFIX . 'notes MODIFY type char(5) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY editedby varchar(50) NOT NULL, MODIFY text varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'settings MODIFY id integer unsigned NOT NULL, MODIFY setting varchar(50) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL, MODIFY value varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'settings DROP PRIMARY KEY, DROP id, ADD PRIMARY KEY(setting);');
		$stmt = $db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('chatname', 'My Chat'), ('topic', ''), ('msgsendall', ?), ('msgsendmem', ?), ('msgsendmod', ?), ('msgsendadm', ?), ('msgsendprv', ?), ('numnotes', '3');");
		$stmt->execute([_('%s - '), _('[M] %s - '), _('[Staff] %s - '), _('[Admin] %s - '), _('[%1$s to %2$s] - ')]);
	}
	if($dbversion<13){
		$db->exec('ALTER TABLE ' . PREFIX . 'filter CHANGE `match` filtermatch varchar(255) NOT NULL, CHANGE `replace` filterreplace varchar(20000) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'ignored CHANGE ignored ign varchar(50) NOT NULL, CHANGE `by` ignby varchar(50) NOT NULL;');
		$db->exec('ALTER TABLE ' . PREFIX . 'linkfilter CHANGE `match` filtermatch varchar(255) NOT NULL, CHANGE `replace` filterreplace varchar(255) NOT NULL;');
	}
	if($dbversion<14){
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . 'members');
			$memcached->delete(DBNAME . '-' . PREFIX . 'ignored');
		}
		if(DBDRIVER===0){//MySQL - previously had a wrong SQL syntax and the captcha table was not created.
			$db->exec('CREATE TABLE IF NOT EXISTS ' . PREFIX . 'captcha (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, time integer unsigned NOT NULL, code char(5) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;');
		}
	}
	if($dbversion<15){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('mailsender', 'www-data <www-data@localhost>'), ('mailreceiver', 'Webmaster <webmaster@localhost>'), ('sendmail', '0'), ('modfallback', '1'), ('guestreg', '0');");
	}
	if($dbversion<17){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN nocache smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<18){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('disablepm', '0');");
	}
	if($dbversion<19){
		$stmt = $db->prepare('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('disabletext', ?);");
		$stmt->execute(['<h1>'._('Temporarily disabled').'</h1>']);
	}
	if($dbversion<20){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN tz smallint NOT NULL DEFAULT 0;');
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('defaulttz', 'UTC');");
	}
	if($dbversion<21){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN eninbox smallint NOT NULL DEFAULT 0;');
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('eninbox', '0');");
		if(DBDRIVER===0){
			$db->exec('CREATE TABLE ' . PREFIX . "inbox (id integer unsigned NOT NULL PRIMARY KEY AUTO_INCREMENT, postid integer unsigned NOT NULL, postdate integer unsigned NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL, INDEX(postid), INDEX(poster), INDEX(recipient)) ENGINE=InnoDB DEFAULT CHARSET=utf8 COLLATE=utf8_bin;");
		}else{
			$db->exec('CREATE TABLE ' . PREFIX . "inbox (id $primary, postdate integer NOT NULL, postid integer NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text varchar(20000) NOT NULL);");
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_postid ON ' . PREFIX . 'inbox(postid);');
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
		}
	}
	if($dbversion<23){
		$db->exec('DELETE FROM ' . PREFIX . "settings WHERE setting='enablejs';");
	}
	if($dbversion<25){
		$db->exec('DELETE FROM ' . PREFIX . "settings WHERE setting='keeplimit';");
	}
	if($dbversion<26){
		$db->exec('INSERT INTO ' . PREFIX . 'settings (setting, value) VALUES (\'passregex\', \'.*\'), (\'nickregex\', \'^[A-Za-z0-9]*$\');');
	}
	if($dbversion<27){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('externalcss', '');");
	}
	if($dbversion<28){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('enablegreeting', '0');");
	}
	if($dbversion<29){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('sortupdown', '0');");
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN sortupdown smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<30){
		$db->exec('ALTER TABLE ' . PREFIX . 'filter ADD COLUMN cs smallint NOT NULL DEFAULT 0;');
		if(MEMCACHED){
			$memcached->delete(DBNAME . '-' . PREFIX . "filter");
		}
	}
	if($dbversion<31){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('hidechatters', '0');");
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN hidechatters smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<32 && DBDRIVER===0){
		//recreate db in utf8mb4
		try{
			$olddb=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_WARNING, PDO::ATTR_PERSISTENT=>PERSISTENT]);
			$db->exec('DROP TABLE ' . PREFIX . 'captcha;');
			$db->exec('CREATE TABLE ' . PREFIX . "captcha (id integer PRIMARY KEY AUTO_INCREMENT, time integer NOT NULL, code char(5) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
			$result=$olddb->query('SELECT filtermatch, filterreplace, allowinpm, regex, kick, cs FROM ' . PREFIX . 'filter;');
			$data=$result->fetchAll(PDO::FETCH_NUM);
			$db->exec('DROP TABLE ' . PREFIX . 'filter;');
			$db->exec('CREATE TABLE ' . PREFIX . "filter (id integer PRIMARY KEY AUTO_INCREMENT, filtermatch varchar(255) NOT NULL, filterreplace text NOT NULL, allowinpm smallint NOT NULL, regex smallint NOT NULL, kick smallint NOT NULL, cs smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'filter (filtermatch, filterreplace, allowinpm, regex, kick, cs) VALUES(?, ?, ?, ?, ?, ?);');
			foreach($data as $tmp){
				$stmt->execute($tmp);
			}
			$result=$olddb->query('SELECT ign, ignby FROM ' . PREFIX . 'ignored;');
			$data=$result->fetchAll(PDO::FETCH_NUM);
			$db->exec('DROP TABLE ' . PREFIX . 'ignored;');
			$db->exec('CREATE TABLE ' . PREFIX . "ignored (id integer PRIMARY KEY AUTO_INCREMENT, ign varchar(50) NOT NULL, ignby varchar(50) NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'ignored (ign, ignby) VALUES(?, ?);');
			foreach($data as $tmp){
				$stmt->execute($tmp);
			}
			$db->exec('CREATE INDEX ' . PREFIX . 'ign ON ' . PREFIX . 'ignored(ign);');
			$db->exec('CREATE INDEX ' . PREFIX . 'ignby ON ' . PREFIX . 'ignored(ignby);');
			$result=$olddb->query('SELECT postdate, postid, poster, recipient, text FROM ' . PREFIX . 'inbox;');
			$data=$result->fetchAll(PDO::FETCH_NUM);
			$db->exec('DROP TABLE ' . PREFIX . 'inbox;');
			$db->exec('CREATE TABLE ' . PREFIX . "inbox (id integer PRIMARY KEY AUTO_INCREMENT, postdate integer NOT NULL, postid integer NOT NULL UNIQUE, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'inbox (postdate, postid, poster, recipient, text) VALUES(?, ?, ?, ?, ?);');
			foreach($data as $tmp){
				$stmt->execute($tmp);
			}
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
			$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
			$result=$olddb->query('SELECT filtermatch, filterreplace, regex FROM ' . PREFIX . 'linkfilter;');
			$data=$result->fetchAll(PDO::FETCH_NUM);
			$db->exec('DROP TABLE ' . PREFIX . 'linkfilter;');
			$db->exec('CREATE TABLE ' . PREFIX . "linkfilter (id integer PRIMARY KEY AUTO_INCREMENT, filtermatch varchar(255) NOT NULL, filterreplace varchar(255) NOT NULL, regex smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'linkfilter (filtermatch, filterreplace, regex) VALUES(?, ?, ?);');
			foreach($data as $tmp){
				$stmt->execute($tmp);
			}
			$result=$olddb->query('SELECT nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, tz, eninbox, sortupdown, hidechatters FROM ' . PREFIX . 'members;');
			$data=$result->fetchAll(PDO::FETCH_NUM);
			$db->exec('DROP TABLE ' . PREFIX . 'members;');
			$db->exec('CREATE TABLE ' . PREFIX . "members (id integer PRIMARY KEY AUTO_INCREMENT, nickname varchar(50) NOT NULL UNIQUE, passhash char(32) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) NOT NULL, nocache smallint NOT NULL, tz smallint NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, tz, eninbox, sortupdown, hidechatters) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
			foreach($data as $tmp){
				$stmt->execute($tmp);
			}
			$result=$olddb->query('SELECT postdate, poststatus, poster, recipient, text, delstatus FROM ' . PREFIX . 'messages;');
			$data=$result->fetchAll(PDO::FETCH_NUM);
			$db->exec('DROP TABLE ' . PREFIX . 'messages;');
			$db->exec('CREATE TABLE ' . PREFIX . "messages (id integer PRIMARY KEY AUTO_INCREMENT, postdate integer NOT NULL, poststatus smallint NOT NULL, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL, delstatus smallint NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'messages (postdate, poststatus, poster, recipient, text, delstatus) VALUES(?, ?, ?, ?, ?, ?);');
			foreach($data as $tmp){
				$stmt->execute($tmp);
			}
			$db->exec('CREATE INDEX ' . PREFIX . 'poster ON ' . PREFIX . 'messages (poster);');
			$db->exec('CREATE INDEX ' . PREFIX . 'recipient ON ' . PREFIX . 'messages(recipient);');
			$db->exec('CREATE INDEX ' . PREFIX . 'postdate ON ' . PREFIX . 'messages(postdate);');
			$db->exec('CREATE INDEX ' . PREFIX . 'poststatus ON ' . PREFIX . 'messages(poststatus);');
			$result=$olddb->query('SELECT type, lastedited, editedby, text FROM ' . PREFIX . 'notes;');
			$data=$result->fetchAll(PDO::FETCH_NUM);
			$db->exec('DROP TABLE ' . PREFIX . 'notes;');
			$db->exec('CREATE TABLE ' . PREFIX . "notes (id integer PRIMARY KEY AUTO_INCREMENT, type char(5) NOT NULL, lastedited integer NOT NULL, editedby varchar(50) NOT NULL, text text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'notes (type, lastedited, editedby, text) VALUES(?, ?, ?, ?);');
			foreach($data as $tmp){
				$stmt->execute($tmp);
			}
			$result=$olddb->query('SELECT setting, value FROM ' . PREFIX . 'settings;');
			$data=$result->fetchAll(PDO::FETCH_NUM);
			$db->exec('DROP TABLE ' . PREFIX . 'settings;');
			$db->exec('CREATE TABLE ' . PREFIX . "settings (setting varchar(50) NOT NULL PRIMARY KEY, value text NOT NULL) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_bin;");
			$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'settings (setting, value) VALUES(?, ?);');
			foreach($data as $tmp){
				$stmt->execute($tmp);
			}
		}catch(PDOException $e){
			send_fatal_error(_('No connection to database!'));
		}
	}
	if($dbversion<33){
		$db->exec('CREATE TABLE ' . PREFIX . "files (id $primary, postid integer NOT NULL UNIQUE, filename varchar(255) NOT NULL, hash char(40) NOT NULL, type varchar(255) NOT NULL, data $longtext NOT NULL)$diskengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'files_hash ON ' . PREFIX . 'files(hash);');
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('enfileupload', '0'), ('msgattache', '%2\$s [%1\$s]'), ('maxuploadsize', '1024');");
	}
	if($dbversion<34){
		$msg.='<br>'._('Note: Default CSS is now hardcoded and can be removed from the CSS setting');
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN nocache_old smallint NOT NULL DEFAULT 0;');
	}
	if($dbversion<37){
		$db->exec('ALTER TABLE ' . PREFIX . 'members MODIFY tz varchar(255) NOT NULL;');
		$db->exec('UPDATE ' . PREFIX . "members SET tz='UTC';");
		$db->exec('UPDATE ' . PREFIX . "settings SET value='UTC' WHERE setting='defaulttz';");
	}
	if($dbversion<38){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('nextcron', '0');");
		$db->exec('DELETE FROM ' . PREFIX . 'inbox WHERE recipient NOT IN (SELECT nickname FROM ' . PREFIX . 'members);'); // delete inbox of members who deleted themselves
	}
	if($dbversion<39){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('personalnotes', '1');");
		$result=$db->query('SELECT type, id FROM ' . PREFIX . 'notes;');
		$data = [];
		while($tmp=$result->fetch(PDO::FETCH_NUM)){
			if($tmp[0]==='admin'){
				$tmp[0]=0;
			}else{
				$tmp[0]=1;
			}
			$data[]=$tmp;
		}
		$db->exec('ALTER TABLE ' . PREFIX . 'notes MODIFY type smallint NOT NULL;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'notes SET type=? WHERE id=?;');
		foreach($data as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_type ON ' . PREFIX . 'notes(type);');
		$db->exec('CREATE INDEX ' . PREFIX . 'notes_editedby ON ' . PREFIX . 'notes(editedby);');
	}
	if($dbversion<41){
		$db->exec('DROP TABLE ' . PREFIX . 'sessions;');
		$db->exec('CREATE TABLE ' . PREFIX . "sessions (id $primary, session char(32) NOT NULL UNIQUE, nickname varchar(50) NOT NULL UNIQUE, status smallint NOT NULL, refresh smallint NOT NULL, style varchar(255) NOT NULL, lastpost integer NOT NULL, passhash varchar(255) NOT NULL, postid char(6) NOT NULL DEFAULT '000000', useragent varchar(255) NOT NULL, kickmessage varchar(255) DEFAULT '', bgcolour char(6) NOT NULL, entry integer NOT NULL, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, ip varchar(45) NOT NULL, nocache smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL, nocache_old smallint NOT NULL)$memengine$charset;");
		$db->exec('CREATE INDEX ' . PREFIX . 'status ON ' . PREFIX . 'sessions(status);');
		$db->exec('CREATE INDEX ' . PREFIX . 'lastpost ON ' . PREFIX . 'sessions(lastpost);');
		$db->exec('CREATE INDEX ' . PREFIX . 'incognito ON ' . PREFIX . 'sessions(incognito);');
		$result=$db->query('SELECT nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, nocache_old, tz, eninbox, sortupdown, hidechatters FROM ' . PREFIX . 'members;');
		$members=$result->fetchAll(PDO::FETCH_NUM);
		$result=$db->query('SELECT postdate, postid, poster, recipient, text FROM ' . PREFIX . 'inbox;');
		$inbox=$result->fetchAll(PDO::FETCH_NUM);
		$db->exec('DROP TABLE ' . PREFIX . 'inbox;');
		$db->exec('DROP TABLE ' . PREFIX . 'members;');
		$db->exec('CREATE TABLE ' . PREFIX . "members (id $primary, nickname varchar(50) NOT NULL UNIQUE, passhash varchar(255) NOT NULL, status smallint NOT NULL, refresh smallint NOT NULL, bgcolour char(6) NOT NULL, regedby varchar(50) DEFAULT '', lastlogin integer DEFAULT 0, timestamps smallint NOT NULL, embed smallint NOT NULL, incognito smallint NOT NULL, style varchar(255) NOT NULL, nocache smallint NOT NULL, nocache_old smallint NOT NULL, tz varchar(255) NOT NULL, eninbox smallint NOT NULL, sortupdown smallint NOT NULL, hidechatters smallint NOT NULL)$diskengine$charset");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'members (nickname, passhash, status, refresh, bgcolour, regedby, lastlogin, timestamps, embed, incognito, style, nocache, nocache_old, tz, eninbox, sortupdown, hidechatters) VALUES(?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?);');
		foreach($members as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE TABLE ' . PREFIX . "inbox (id $primary, postdate integer NOT NULL, postid integer NOT NULL UNIQUE, poster varchar(50) NOT NULL, recipient varchar(50) NOT NULL, text text NOT NULL)$diskengine$charset;");
		$stmt=$db->prepare('INSERT INTO ' . PREFIX . 'inbox (postdate, postid, poster, recipient, text) VALUES(?, ?, ?, ?, ?);');
		foreach($inbox as $tmp){
			$stmt->execute($tmp);
		}
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_poster ON ' . PREFIX . 'inbox(poster);');
		$db->exec('CREATE INDEX ' . PREFIX . 'inbox_recipient ON ' . PREFIX . 'inbox(recipient);');
		$db->exec('ALTER TABLE ' . PREFIX . 'inbox ADD FOREIGN KEY (recipient) REFERENCES ' . PREFIX . 'members(nickname) ON DELETE CASCADE ON UPDATE CASCADE;');
	}
	if($dbversion<42){
		$db->exec('INSERT IGNORE INTO ' . PREFIX . "settings (setting, value) VALUES ('filtermodkick', '1');");
	}
	if($dbversion<43){
		$stmt = $db->prepare('INSERT IGNORE INTO ' . PREFIX . "settings (setting, value) VALUES ('metadescription', ?);");
		$stmt->execute([_('A chat community')]);
	}
	if($dbversion<44){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting,value) VALUES ('publicnotes', '0');");
	}
	if($dbversion<45){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting,value) VALUES ('memkickalways', '0'), ('sysmessagetxt', 'ℹ️ &nbsp;'),('namedoers', '1');");
	}
	if($dbversion<46){
		$db->exec('ALTER TABLE ' . PREFIX . 'members ADD COLUMN loginfails integer unsigned NOT NULL DEFAULT 0;');
	}
	if($dbversion<47){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting,value) VALUES ('hide_reload_post_box', '0'), ('hide_reload_messages', '0'),('hide_profile', '0'),('hide_admin', '0'),('hide_notes', '0'),('hide_clone', '0'),('hide_rearrange', '0'),('hide_help', '0'),('max_refresh_rate', '150'),('min_refresh_rate', '5'),('postbox_delete_globally', '0'),('allow_js', '1');");
	}
	if($dbversion<48){
		$db->exec('INSERT INTO ' . PREFIX . "settings (setting, value) VALUES ('exitwait', '180'), ('exitingtxt', ' &#128682;"); // door emoji
		$db->exec('ALTER TABLE ' . PREFIX . 'sessions ADD COLUMN exiting smallint NOT NULL DEFAULT 0;');
	}
	update_setting('dbversion', DBVERSION);
	if($msgencrypted!==MSGENCRYPTED){
		if(!extension_loaded('sodium')){
			send_fatal_error(sprintf(_('The %s extension of PHP is required for the encryption feature. Please install it first or set the encrypted setting back to false.'), 'sodium'));
		}
		$result=$db->query('SELECT id, text FROM ' . PREFIX . 'messages;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'messages SET text=? WHERE id=?;');
		while($message=$result->fetch(PDO::FETCH_ASSOC)){
			try {
				if(MSGENCRYPTED){
					$message['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($message['text'], '', AES_IV, ENCRYPTKEY));
				}else{
					$message['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($message['text']), null, AES_IV, ENCRYPTKEY);
				}
			} catch (SodiumException $e){
				send_error($e->getMessage());
			}
			$stmt->execute([$message['text'], $message['id']]);
		}
		$result=$db->query('SELECT id, text FROM ' . PREFIX . 'notes;');
		$stmt=$db->prepare('UPDATE ' . PREFIX . 'notes SET text=? WHERE id=?;');
		while($message=$result->fetch(PDO::FETCH_ASSOC)){
			try {
				if(MSGENCRYPTED){
					$message['text']=base64_encode(sodium_crypto_aead_aes256gcm_encrypt($message['text'], '', AES_IV, ENCRYPTKEY));
				}else{
					$message['text']=sodium_crypto_aead_aes256gcm_decrypt(base64_decode($message['text']), null, AES_IV, ENCRYPTKEY);
				}
			} catch (SodiumException $e){
				send_error($e->getMessage());
			}
			$stmt->execute([$message['text'], $message['id']]);
		}
		update_setting('msgencrypted', (int) MSGENCRYPTED);
	}
	send_update($msg);
}

function get_setting(string $setting) : string {
	global $db, $memcached;
	$value = '';
	if($db instanceof PDO && ( !MEMCACHED || ! ($value = $memcached->get(DBNAME . '-' . PREFIX . "settings-$setting") ) ) ){
		try {
			$stmt = $db->prepare( 'SELECT value FROM ' . PREFIX . 'settings WHERE setting=?;' );
			$stmt->execute( [ $setting ] );
			$stmt->bindColumn( 1, $value );
			$stmt->fetch( PDO::FETCH_BOUND );
			if ( MEMCACHED ) {
				$memcached->set( DBNAME . '-' . PREFIX . "settings-$setting", $value );
			}
		} catch (Exception $e){
			return '';
		}
	}
	return $value;
}

function update_setting(string $setting, $value): void
{
	global $db, $memcached;
	$stmt=$db->prepare('UPDATE ' . PREFIX . 'settings SET value=? WHERE setting=?;');
	$stmt->execute([$value, $setting]);
	if(MEMCACHED){
		$memcached->set(DBNAME . '-' . PREFIX . "settings-$setting", $value);
	}
}

// configuration, defaults and internals

function check_db(): void
{
	global $db, $memcached;
	$options=[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_PERSISTENT=>PERSISTENT];
	try{
		if(DBDRIVER===0){
			if(!extension_loaded('pdo_mysql')){
				send_fatal_error(sprintf(_('The %s extension of PHP is required for the selected database driver. Please install it first.'), 'pdo_mysql'));
			}
			$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, $options);
		}elseif(DBDRIVER===1){
			if(!extension_loaded('pdo_pgsql')){
				send_fatal_error(sprintf(_('The %s extension of PHP is required for the selected database driver. Please install it first.'), 'pdo_pgsql'));
			}
			$db=new PDO('pgsql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, $options);
		}else{
			if(!extension_loaded('pdo_sqlite')){
				send_fatal_error(sprintf(_('The %s extension of PHP is required for the selected database driver. Please install it first.'), 'pdo_sqlite'));
			}
			$db=new PDO('sqlite:' . SQLITEDBFILE, NULL, NULL, $options);
			$db->exec('PRAGMA foreign_keys = ON;');
		}
	}catch(PDOException $e){
		try{
			//Attempt to create database
			if(DBDRIVER===0){
				$db=new PDO('mysql:host=' . DBHOST, DBUSER, DBPASS, $options);
				if(false!==$db->exec('CREATE DATABASE ' . DBNAME)){
					$db=new PDO('mysql:host=' . DBHOST . ';dbname=' . DBNAME . ';charset=utf8mb4', DBUSER, DBPASS, $options);
				}else{
					send_fatal_error(_('No connection to database, please create a database and edit the script to use the correct database with given username and password!'));
				}

			}elseif(DBDRIVER===1){
				$db=new PDO('pgsql:host=' . DBHOST, DBUSER, DBPASS, $options);
				if(false!==$db->exec('CREATE DATABASE ' . DBNAME)){
					$db=new PDO('pgsql:host=' . DBHOST . ';dbname=' . DBNAME, DBUSER, DBPASS, $options);
				}else{
					send_fatal_error(_('No connection to database, please create a database and edit the script to use the correct database with given username and password!'));
				}
			}else{
				if(isset($_REQUEST['action']) && $_REQUEST['action']==='setup'){
					send_fatal_error(_('No connection to database, please create a database and edit the script to use the correct database with given username and password!'));
				}else{
					send_fatal_error(_('No connection to database!'));
				}
			}
		}catch(PDOException $e){
			if(isset($_REQUEST['action']) && $_REQUEST['action']==='setup'){
				send_fatal_error(_('No connection to database, please create a database and edit the script to use the correct database with given username and password!'));
			}else{
				send_fatal_error(_('No connection to database!'));
			}
		}
	}
	if(MEMCACHED){
		if(!extension_loaded('memcached')){
			send_fatal_error(_('The memcached extension of PHP is required for the caching feature. Please install it first or set the memcached setting back to false.'));
		}
		$memcached=new Memcached();
		$memcached->addServer(MEMCACHEDHOST, MEMCACHEDPORT);
	}
	if(!isset($_REQUEST['action']) || $_REQUEST['action']==='setup'){
		if(!check_init()){
			send_init();
		}
		update_db();
	}elseif($_REQUEST['action']==='init'){
		init_chat();
	}
}

function load_fonts() : array {
	return [
		'Arial'			=>"font-family:Arial,Helvetica,sans-serif;",
		'Book Antiqua'	=>"font-family:'Book Antiqua','MS Gothic',serif;",
		'Comic'			=>"font-family:'Comic Sans MS',Papyrus,sans-serif;",
		'Courier'		=>"font-family:'Courier New',Courier,monospace;",
		'Cursive'		=>"font-family:Cursive,Papyrus,sans-serif;",
		'Fantasy'		=>"font-family:Fantasy,Futura,Papyrus,sans;",
		'Garamond'		=>"font-family:Garamond,Palatino,serif;",
		'Georgia'		=>"font-family:Georgia,'Times New Roman',Times,serif;",
		'Serif'			=>"font-family:'MS Serif','New York',serif;",
		'System'		=>"font-family:System,Chicago,sans-serif;",
		'Times New Roman'	=>"font-family:'Times New Roman',Times,serif;",
		'Verdana'		=>"font-family:Verdana,Geneva,Arial,Helvetica,sans-serif;",
		'Roboto'		=>"font-family:'Roboto',sans-serif;",
		'Open Sans'		=>"font-family:'Open Sans',sans-serif;",
		'Lato'			=>"font-family:'Lato',sans-serif;",
		'Montserrat'	=>"font-family:'Montserrat',sans-serif;",
		'Poppins'		=>"font-family:'Poppins',sans-serif;",
	];
}
function load_lang(): void
{
	global $language, $locale, $dir;
	if(isset($_REQUEST['lang']) && isset(LANGUAGES[$_REQUEST['lang']])){
		$locale = LANGUAGES[$_REQUEST['lang']]['locale'];
		$language = $_REQUEST['lang'];
		$dir = LANGUAGES[$_REQUEST['lang']]['dir'];
		set_secure_cookie('language', $language);
	}elseif(isset($_COOKIE['language']) && isset(LANGUAGES[$_COOKIE['language']])){
		$locale = LANGUAGES[$_COOKIE['language']]['locale'];
		$language = $_COOKIE['language'];
		$dir = LANGUAGES[$_COOKIE['language']]['dir'];
	}elseif(!empty($_SERVER['HTTP_ACCEPT_LANGUAGE'])){
		$prefLocales = array_reduce(
			explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']),
			function (array $res, string $el) {
				list($l, $q) = array_merge(explode(';q=', $el), [1]);
				$res[$l] = (float) $q;
				return $res;
			}, []);
		arsort($prefLocales);
		foreach($prefLocales as $l => $q){
			$lang = locale_lookup(array_keys(LANGUAGES), $l);
			if(!empty($lang)){
				$locale = LANGUAGES[$lang]['locale'];
				$language = $lang;
				$dir = LANGUAGES[$lang]['dir'];
				set_secure_cookie('language', $language);
				break;
			}
		}
	}
	// Menggunakan $_ENV untuk mengatur locale karena putenv() tidak tersedia
	$_ENV['LC_ALL'] = $locale;
	setlocale(LC_ALL, $locale);
	bindtextdomain('le-chat-php', __DIR__.'/locale');
	bind_textdomain_codeset('le-chat-php', 'UTF-8');
	textdomain('le-chat-php');
}


function load_config(): void
{
	mb_internal_encoding('UTF-8');
	define('VERSION', '1.24.1'); // Script version
	define('DBVERSION', 48); // Database layout version
	define('MSGENCRYPTED', true); // Store messages encrypted in the database to prevent other database users from reading them - true/false - visit the setup page after editing!
	define('ENCRYPTKEY_PASS', '5PcpFOZ+SfuAIU/32XqK/26ZXKsI198qC7DR1HTdjVY='); // Recommended length: 32. Encryption key for messages
	define('AES_IV_PASS', 'ba94e56f3888507402d5e08484e92cd1'); // Recommended length: 12. AES Encryption IV
	
	// define('DBHOST', 'localhost'); // Database host
	// define('DBUSER', '8XEdt92Z4NAIIu9CNXCxR58Xet0Ev3C0'); // Database user
	// define('DBPASS', '180406'); // Database password
	// define('DBNAME', '7dt78qxuzbTTlqSOLYdfbJOMLqh1bJBs'); // Database
	// Load database configuration from external file

	if (!file_exists('confix.php')) {
		die('Error: confix.php file not found');
	}
	require_once ('confix.php');
	
	define('DBHOST', $DBHOST); // Database host
	define('DBUSER', $DBUSER); // Database user 
	define('DBPASS', $DBPASS); // Database password
	define('DBNAME', $DBNAME); // Database name

	define('PERSISTENT', true); // Use persistent database conection true/false
	define('PREFIX', ''); // Prefix - Set this to a unique value for every chat, if you have more than 1 chats on the same database or domain - use only alpha-numeric values (A-Z, a-z, 0-9, or _) other symbols might break the queries
	define('MEMCACHED', false); // Enable/disable memcached caching true/false - needs memcached extension and a memcached server.
		if(defined('MEMCACHED') && MEMCACHED){
			define('MEMCACHEDHOST', 'localhost'); // Memcached host
			define('MEMCACHEDPORT', '11211'); // Memcached port
		}
	define('DBDRIVER', 0); // Selects the database driver to use - 0=MySQL, 1=PostgreSQL, 2=sqlite
	if(DBDRIVER===2){
		define('SQLITEDBFILE', 'public_chat.sqlite'); // Filepath of the sqlite database, if sqlite is used - make sure it is writable for the webserver user
	}
	define('COOKIENAME', PREFIX . 'chat_session'); // Cookie name storing the session information
	define('LANG', 'en'); // Default language
	if (MSGENCRYPTED){
		if (version_compare(PHP_VERSION, '7.2.0') < 0) {
			die("You need at least PHP >= 7.2.x");
		}
		//Do not touch: Compute real keys needed by encryption functions
		if (strlen(ENCRYPTKEY_PASS) !== SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES){
			define('ENCRYPTKEY', substr(hash("sha512/256",ENCRYPTKEY_PASS),0, SODIUM_CRYPTO_AEAD_AES256GCM_KEYBYTES));
		}else{
			define('ENCRYPTKEY', ENCRYPTKEY_PASS);
		}
		if (strlen(AES_IV_PASS) !== SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES){
			define('AES_IV', substr(hash("sha512/256",AES_IV_PASS), 0, SODIUM_CRYPTO_AEAD_AES256GCM_NPUBBYTES));
		}else{
			define('AES_IV', AES_IV_PASS);
		}
	}
	define('RESET_SUPERADMIN_PASSWORD', ''); //Use this to reset your superadmin password in case you forgot it
}


