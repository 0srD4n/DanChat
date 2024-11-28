<?php
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DanChat Secure Donation Portal</title>
    <style>
        body {
            color: #0f0;
            font-family: 'Courier New', monospace;
            margin: 0;
            padding: 20px;
            background-color: black;
            }

        .container {
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            border: 1px solid #0f0;
            box-shadow: 0 0 20px rgba(0,255,0,0.2);
        }

        h1 {
            text-align: center;
            color: #0f0;
            text-shadow: 0 0 10px #0f0;
            font-size: 2em;
            margin-bottom: 30px;
        }

        .warning {
            color: #ff0000;
            text-align: center;
            margin: 20px 0;
            padding: 10px;
            border: 1px solid #ff0000;
            text-transform: uppercase;
        }

        .payment-options {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }

        .payment-option {
            border: 1px solid purple;
            padding: 15px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }

        .payment-option:hover {
            background: rgba(0,255,0,0.1);
            transform: scale(1.05);
        }

        .crypto-address {
            font-family: monospace;
            background: rgba(0,255,0,0.1);
            padding: 10px;
            margin: 10px 0;
            word-break: break-all;
        }

        .disclaimer {
            color: red;
            font-size: 0.8em;
            text-align: center;
            margin-top: 30px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>SECURE DONATION PORTAL FOR DANCHAT</h1>
        
        <div class="warning">
            ⚠ ENCRYPTED CONNECTION REQUIRED ⚠
        </div>

        <p>Support the chat Room danchat. All transactions are encrypted and routed through secure nodes.</p>

        <div class="payment-options">
            <div class="payment-option">
                <h3>BTC</h3>
                <div class="crypto-address">18cq7C3Gegea7XVnEHbXDJ9EAJ5SWFfxrZ</div>
                <img src="./btc.png" width="150"height="150" alt="Bitcoin QR Code">
            </div>
            
            <div class="payment-option">
                <h3>ETH</h3>
                <div class="crypto-address">0xb23D38832c86d3A56389473D2a8cE10B684bC902</div>
                <img src="./eth.png" width="150" height="150" alt="Ethereum QR Code">
            </div>
            
            <div class="payment-option">
                <h3>XMR</h3>
                <div class="crypto-address">865UDtygoM7Woh1dvcLD1yLFLx4zbCLTsQd1zfnh4jPtBJ6Q53RDpEpDCM79o7Q6degCV5TJbRqTTYtDEPs4UhGxCTdu4YD</div>
                <img src="./xmr.png" width="150" height="150" alt="Monero QR Code">
            </div>
        </div>

        <div class="disclaimer">
            By proceeding with donation, you acknowledge that all transactions are final and irreversible. 
            We take no responsibility for funds sent to incorrect addresses.
            For maximum security, verify addresses through multiple secure channels.
            Thanks For donation
        </div>
    </div>
</body>
</html>