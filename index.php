<?php
function navbar() {
    echo "<style> nav {
    text-align: center;
    height: 40px;
    display: flex;
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 1rem;
    background: rgba(0,20,20,0.8);
    border-bottom: 1px solid var(--neon-border-color);
}

nav a {
    display: inline-block;
    text-decoration: none;
    color: var(--neon-text-color);
    padding: 0.5rem 1rem;
    margin: 0 0.5rem;
    transition: all 0.3s ease;
}</style>";
    echo "<nav>
        <span class='menu'>
        <a href=''>Home</a>
        <a href=''>About</a>
        <a href=''>Contact</a>
        </span>
    </nav>";
}
header('Content-Type: text/html; charset=UTF-8');

echo '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>XplDan Gate</title>
    <link rel="icon" type="image/x-icon" href="danchat.svg">
    <style>
        :root {
            --neon-text-color: white;
            --neon-border-color: #0f0;
        }
*{
    box-sizing:border-box;
}
        body {
            margin: 0;
            padding: 0;
            background-color: #000;
            color: var(--neon-text-color);
            font-family: \'Courier New\', monospace;
            min-height: 100vh;
            background-image: url(\'danchat.jpg\');
            background-size: cover;
            background-position: center;
            overflow-x: hidden;
        }

        .header {
        margin-top:20px;
            text-align: center;
            padding: 2rem;
            backdrop-filter: blur(10px);
            background: rgba(0,20,20,0.8);
            border-bottom: 1px solid var(--neon-border-color);
        }

        .header h1 {
            font-size: 3em;
            margin: 0;
            text-transform: uppercase;
            letter-spacing: 0.15em;
        }

        .main-content {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 2rem;
            padding: 2rem;
            max-width: 1200px;
            margin: 0 auto;
        }

        .card {
            backdrop-filter: blur(10px);
            background: rgba(0,20,20,0.8);
            border: 1px solid var(--neon-border-color);
            padding: 1.5rem;
            border-radius: 10px;
            transition: transform 0.3s;
        }

     
        .card h2 {
            color: var(--neon-text-color);
            margin-top: 0;
            border-bottom: 1px solid var(--neon-border-color);
            padding-bottom: 0.5rem;
        }

        .access-links {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            margin-top: 2rem;
        }

        .neon-link {
            color: var(--neon-text-color);
            text-decoration: none;
            border: 1px solid var(--neon-text-color);
            padding: 1rem;
            text-align: center;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            border-radius: 5px;
            transition: all 0.3s;
        }


  

        .stat-item {
            text-align: center;
            padding: 1rem;
            border: 1px solid var(--neon-border-color);
            border-radius: 5px;
        }

        .footer {
            text-align: center;
            padding: 2rem;
            backdrop-filter: blur(10px);
            background: rgba(0,20,20,0.8);
            border-top: 1px solid var(--neon-border-color);
            margin-top: 2rem;
        }

        @media (max-width: 768px) {
            .header h1 {
                font-size: 2em;
            }
            
            .main-content {
                grid-template-columns: 1fr;
                padding: 1rem;
            }
        }
.section{
    display:inline;
    width: 100%;
    text-align:center;
    height:20px;
}

    .warn { 
    color:red;
    }
.text-section{
    border-bottom:1px solid green;
padding:20px;
align-items:center;
font-size:50px;
}
.project p {
width:100%;
}

a {
    color: inherit;
    text-decoration: underline;
}
.kontainer {
     position: relative;
      width: 100%;
      height: 100%;
      overflow: hidden;
}
    .slide {
      position: absolute;
      width: 100%;
      height: 100%;
      object-fit: cover;
      animation: slideshow 15s infinite;
      opacity: 0;
    }
    .slide:nth-child(1) {
      animation-delay: 0s;
    }
    .slide:nth-child(2) {
      animation-delay: 5s; 
    }
    .slide:nth-child(3) {
      animation-delay: 10s;
    }
    @keyframes slideshow {
      0% { opacity: 0; }
      5% { opacity: 1; }
      33% { opacity: 1; }
      38% { opacity: 0; }
      100% { opacity: 0; }
    }
.header-preview {
    width: 100%;
    height: 900px;
    border-radius: 10px;
    overflow: hidden;
    position: relative;
            border-bottom: 1px solid var(--neon-border-color);
}


.header-preview img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

    </style>
</head>
<body>';
navbar();
echo '
    <header class="header">
        <h1>XplDan Project</h1>
        <p>This My Project</p>
    </header>
    <div class="header-preview">
<div class="kontainer">
  <img src="preview/prev_danchat.png" class="slide">
  <img src="preview/prev2_danchat.png" class="slide">

</div>
</div>
    <div class="section">
<h2 class="text-section"> Chat Room </h2></div>
    <main class="main-content">
        <section class="card">
            <h2>About</h2>
            
              <div class="project">
                <p>Hello everyone, this is a chat room that I created using <a target="_blank" href="https://github.com/DanWin/le-chat-php">le-chat-php</a> by Danwin as the framework. I modified it and added several features that can be useful for admins and members.</p>
            </div>
        </section>

        <section class="card">
            <h2>Security Features</h2>
            <ul>
                <li>End-to-End Encryption</li>
                <li>Tor Network Support</li>
                <li>Zero Logs Policy</li>
                <li>Secure File Sharing</li>
                <li>Anonymous Chat Rooms</li>
                <li>One Gate Password</li>
            </ul>
        </section>

        <section class="card">
            <h2>Access Points</h2>
            <div class="access-links">
                <a href="' . htmlspecialchars('/chat/index.php') . '" class="neon-link">
                    Enter DanChat
                </a>
                <a href="' . htmlspecialchars('http://7ezcvo2wrozkrakhitpnloz2m3l6uqa33st6lyyylpe7ptzdghpsc4yd.onion/chat/index.php') . '" class="neon-link">
                    Tor Network Access
                </a>
                <a href="' . htmlspecialchars('http://danchat.run.place/chat/index.php') . '" class="neon-link">
                    Clearnet Access
                </a>
            </div>
        </section>
    </main>

    <footer class="footer">
        <p class="warn">⚠ WARNING: All activities are monitored and logged</p>
        <p>Protected by Tor Network | Secure Connection</p>
        <p class="disclaimer">By entering, you acknowledge all risks and responsibilities.</p>
    </footer>
</body>
</html>';
?>
