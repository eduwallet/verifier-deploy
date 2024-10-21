<?php
session_start();
include_once(__DIR__ . '/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$adminToken = $_SESSION['token'] ?? uniqid();
$_SESSION['token'] = $adminToken;

?>
<!doctype html>
<html>
    <head>
        <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
        <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
        <link rel='stylesheet' href="index.css"></link>
    </head>
    <body>
        <div class='wrapper'>
            <div id="stage1">
                <h1>Welcome</h1>
                <p>Welcome to the Front-End Verifier Demo Page. Please select the credential that you want to verify by clicking the relevant button below.</p>
                <div class='buttons'>
                    <button id='abc'>Academic Base Credential</button>
                    <button id='pid'>Personal ID</button>
                </div>
            </div>
            <div id="stage2">
                <p>Please start the verification flow by scanning the QR code with your wallet app</p>
                <div id="qrcode"></div>
                <button id="verifier">Refresh the code</button>
            </div>
            <div id="stage3">
                <H1>Requesting Credential</H1>
                <p>Please proceed on your device to select the correct requested credential.</p>
            </div>
            <div id="stage4">
                <H1>Credential Received</H1>
                <h4>Metadata</h4>
                <div class='metadatablock'>
                    <div class='row'>
                        <div class='label'>State identifier</div>
                        <div class="metadata"><span id='stateid'></span></div>
                    </div>
                    <div class='row'>
                        <div class='label'>Nonce</div>
                        <div class="metadata"><span id='nonce'></span></div>
                    </div>
                    <div class='row'>
                        <div class='label'>Wallet key</div>
                        <div class="metadata"><span id='issuer'></span></div>
                    </div>
                    <div class='row'>
                        <div class='label'>VC Holder key</div>
                        <div class="metadata"><span id='holder'></span></div>
                    </div>
                    <div class='row'>
                        <div class='label'>VC Issuer ID</div>
                        <div class="metadata"><span id='originalissuer'></span></div>
                    </div>
                    <div class='row'>
                        <div class='label'>VC Issuer key</div>
                        <div class="metadata"><span id='issuerkey'></span></div>
                    </div>
                </div>

                <h4>Credential data</h4>
                <div id='credentials'></div>

                <h4>Validation Messages</h4>
                <div id='messages'></div>
            </div>
        </div>

        <script type="text/javascript">

var code = new QRCode(document.getElementById("qrcode"));
var response = null;
var backendurl = 'backendcall.php';
var selectedcredential = 'PID';
var state = 'none';
var currentStage = 'stage1';
var isWaiting = false;

function createNewQR() {
    $.ajax({
        url: backendurl,
        method:'POST',
        data: {
            token: <?php echo json_encode($adminToken); ?>,
            action: "create",
            credential: selectedcredential
        },
        dataType: 'json'
    })
    .then((json) => {
        code.makeCode(json.requestUri);
        state = json.state;
    });
}

$('#abc').on('click', function() {
    selectedcredential = 'ABC';
    currentStage = 'stage2';
    flipStage();
});
$('#pid').on('click', function() {
    selectedcredential = 'PID';
    currentStage = 'stage2';
    flipStage();
});
$('#verifier').on('click', () => createNewQR());

function flipStage() {
    $('#stage4').hide();
    $('#stage3').hide();
    $('#stage2').hide();
    $('#stage1').hide();
    if (currentStage == 'stage4') {
        $('#stage4').show();

        // metadata
        $('#issuer').html(response.issuer);
        $('#stateid').html(response.state);
        $('#nonce').html(response.nonce);
        $('#holder').html(response.credentials[0].holder);
        $('#originalissuer').html(response.credentials[0].issuer);
        $('#issuerkey').html(response.credentials[0].issuerKey);

        var txt='';
        for(var i of Object.keys(response.credentials[0].claims)) {
            var key = i;
            var claim = response.credentials[0].claims[key];
            if (typeof(claim) == 'string' && claim.startsWith('data:image/')) {
                txt += '<div class="row"><div class="label claimlabel">' + key + '</div><div class="claim portraitclaim"><img src="' + claim + '" class="portrait"></div></div>';
            }
            else {
                txt += '<div class="row"><div class="label claimlabel">' + key + '</div><div class="claim">'+ claim + '</div></div>';
            }            
        }
        $('#credentials').html('<div class="credentials">' + txt + '</div>');

        var msg='';
        if (response.messages.length > 0) {
            for (var message of response.messages) {
                msg += '<div class="row"><div class="label messagelabel">' + message.code + '</div><div class="message">'+ message.message + '</div></div>';
            }
        }
        else {
            msg += '<div class="row"><div class="label messagelabel">OKAY</div><div class="message">No problems found while validating</div></div>';
        }
        $('#messages').html('<div class="messages">' + msg + '</div>');
    }
    else if (currentStage == 'stage3') {
        $('#stage3').show();
    }
    else if (currentStage == 'stage2') {
        createNewQR();
        $('#stage2').show();
    }
    else {
        $('#stage1').show();
    }
}

flipStage();
window.setInterval(function() {
    if (['stage2', 'stage3'].includes(currentStage)) {
        isWaiting = true;
        $.ajax({
            url: backendurl,
            method: 'POST',
            data: {
                token: <?php echo json_encode($adminToken); ?>,
                action: "state",
                state: state
            },
            dataType: 'json'
        })
        .then((json) => {
            isWaiting = false;
            if (json.status == 'AUTHORIZATION_REQUEST_RETRIEVED' || json.status == 'RESPONSE_PROCESSING') {
                currentStage = 'stage3';
                flipStage();
            }
            else if(json.status == 'RESPONSE_RECEIVED') {
                currentStage = 'stage4';
                response = json.result;
                flipStage();
            }
        })
        .catch(() => {
            isWaiting = false;
        });
    }
}, 1000);
            </script>
        </div>
    </body>
</html>