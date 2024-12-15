<?php
// Directory where music files are stored
$music_dir = '../music/';

// Handle file upload
if (isset($_FILES['music_file'])) {
    $target_file = $music_dir . basename($_FILES["music_file"]["name"]);
    $uploadOk = 1;
    $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));

    // Check if file is an MP3
    if($imageFileType != "mp3") {
        die("Sorry, only MP3 files are allowed.");
    }

    // Try to upload file
    if (move_uploaded_file($_FILES["music_file"]["tmp_name"], $target_file)) {
        $upload_message = "The file ". basename( $_FILES["music_file"]["name"]). " has been uploaded.";
    } else {
        die("Sorry, there was an error uploading your file.");
    }
}

// Get all mp3 files from directory
$music_files = glob($music_dir . "*.mp3");

// Handle empty music directory case
if (empty($music_files)) {
    die("No music files found in {$music_dir}. Please add some .mp3 files.");
}

// Get random music file
$random_music = $music_files[array_rand($music_files)];
$session = $_POST['session'];
// Get current song from URL parameter or use random
$current_song = isset($_GET['song']) ? $_GET['song'] : $random_music;

// Validate that file exists and is in music directory
if (!file_exists($current_song) || strpos($current_song, $music_dir) !== 0) {
    $current_song = $random_music;
}

// Get list of all songs
$all_songs = array();
foreach($music_files as $file) {
    $all_songs[] = basename($file);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Music Player</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            min-height: 100vh;
            color: white;
            background:black;
            padding: 20px;
        }
        .player-card {
            background:green ;
            backdrop-filter: blur(10px);
            border-radius: 15px;
            padding: 20px;
            margin: 20px auto;
            max-width: 600px;
        }
        .controls {
            display: flex;
            justify-content: center;
            gap: 15px;
            margin: 20px 0;
        }
        .controls a {
            color: white;
            text-decoration: none;
            background: rgba(0,0,0,0.3);
            padding: 10px 20px;
            border-radius: 25px;
            transition: all 0.3s;
        }
        .controls a:hover {
            background: rgba(0,0,0,0.5);
        }
        .now-playing {
            text-align: center;
            margin: 20px 0;
            font-size: 1.2rem;
        }
        .playlist {
            background: rgba(0,0,0,0.2);
            border-radius: 10px;
            padding: 15px;
            margin-top: 20px;
        }
        .playlist a {
            color: white;
            text-decoration: none;
            display: block;
            padding: 8px;
            border-radius: 5px;
            transition: background 0.3s;
        }
        .playlist a:hover {
            background: rgba(255,255,255,0.1);
        }
        .upload-form {
            background: rgba(0,0,0,0.2);
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="player-card">
            <h2 class="text-center mb-4">Music Player</h2>

            <!-- Upload Form -->
            <div class="upload-form">
                <h4>Upload Music</h4>
                <form action="" method="post" enctype="multipart/form-data">
                    <div class="mb-3">
                        <input type="file" class="form-control" name="music_file" accept=".mp3">
                    </div>
                    <button type="submit" class="btn btn-primary">Upload MP3</button>
                </form>
                <?php if(isset($upload_message)): ?>
                    <div class="alert alert-success mt-2"><?php echo $upload_message; ?></div>
                <?php endif; ?>
            </div>

<?php
// Get session from POST data
$session = isset($_POST['session']) ? $_POST['session'] : '';

// Validate session
if (empty($session)) {
    header('Location:/chat/index.php?');
    exit;
}

// Sanitize session ID before displaying
$sanitized_session = htmlspecialchars($session, ENT_QUOTES, 'UTF-8');
echo "<p>Session ID: $sanitized_session</p>";
?>
            <div class="now-playing">
                Now Playing: <?php echo basename($current_song); ?>
            </div>

            <audio controls autoplay style="width: 100%">
                <source src="<?php echo htmlspecialchars($current_song); ?>" type="audio/mpeg">
                Your browser does not support the audio element.
            </audio>

            <div class="controls">
                <a href="?song=<?php echo urlencode($music_files[array_rand($music_files)]); ?>">
                    <i class="fas fa-random"></i> Random
                </a>
                <?php 
                $current_index = array_search($current_song, $music_files);
                $prev_song = ($current_index > 0) ? $music_files[$current_index - 1] : end($music_files);
                $next_song = ($current_index < count($music_files) - 1) ? $music_files[$current_index + 1] : reset($music_files);
                ?>
                <a href="?song=<?php echo urlencode($prev_song); ?>">
                    <i class="fas fa-step-backward"></i> Previous
                </a>
                <a href="?song=<?php echo urlencode($next_song); ?>">
                    <i class="fas fa-step-forward"></i> Next
                </a>
            </div>

            <div class="playlist">
                <h4 class="mb-3">Playlist</h4>
                <?php foreach($all_songs as $song): ?>
                    <a href="?song=<?php echo urlencode($music_dir . $song); ?>" 
                       class="<?php echo ($current_song == $music_dir . $song) ? 'active' : ''; ?>">
                        <?php echo htmlspecialchars($song); ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</body>
</html>