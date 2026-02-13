<?php
session_start();

$adminToken = $_SESSION['token'] ?? uniqid();
$_SESSION['token'] = $adminToken;

include('config.php');

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
                <p>Welcome to the Front-End Testing Page. Please select the credential that you want to verify by clicking the relevant button below.</p>
                <?php foreach ($groups as $gkey => $group) : ?>
                <div class="group">
                    <h1 class="grouptitle"><span class='group expand'>(+)</span><?= $group['name'] ?></h1>
                    <div class="issuers">
                        <?php foreach ($group['issuers'] as $ikey => $issuer):?>
                            <div class="issuer">
                                <h3 class="issuertitle"><span class='issuer expand'>(+)</span><?= $issuer["name"] ?></h3>
                                <div class='credentials-wrapper'><div class="credentials">
                                    <?php foreach ($issuer["credentials"] as $credential):?>
                                        <div class="credential">
                                            <button data-cred='<?php echo json_encode(["group" => $gkey, "issuer" => $ikey, "credential" => $credential['short']]) ?>'>
                                                <?=  $credential['name'] ?>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                </div></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <div id="stage2">
                <p>Please start the flow by scanning the QR code with your wallet app</p>
                <div id="qrcode"></div>
                <button id="refresh">Refresh the code</button>
            </div>
            <div id="stage3">
                <H1>Requesting Response</H1>
                <p>Please proceed on your device to select the correct requested credential.</p>
            </div>
            <div id="stage4">
                <H1>Flow Completed</H1>
                <button id="return">Return to the front page</button>
            </div>
        </div>

        <script type="text/javascript">

var code = new QRCode(document.getElementById("qrcode"));
var response = null;
var backendurl = 'backendcall.php';
var selectedcredential = '';
var selectedgroup = '';
var selectedissuer = '';
var state = 'none';
var currentStage = 'stage1';
var isWaiting = false;

function createNewQR() {
    $.ajax({
        url: backendurl,
        method:'POST',
        data: {
            token: <?php echo json_encode($adminToken); ?>,
            state: state,
            action: "create",
            credential: selectedcredential,
            group: selectedgroup,
            issuer: selectedissuer
        },
        dataType: 'json'
    })
    .then((json) => {
        code.makeCode(json.requestUri);
        state = json.state;
    });
}

$('#stage1 button').on('click', function() {
    console.log("clicked on button on stage1");
    var data = JSON.parse($(this).attr('data-cred'));
    console.log('button data is ', data);
    selectedcredential = data.credential;
    selectedgroup = data.group;
    selectedissuer = data.issuer;
    createNewQR();
    currentStage = 'stage2';
    console.log('switching to stage 2');
    flipStage();
});
$('#refresh').on('click', () => createNewQR());
$('#return').on('click', () => {
    currentStage = 'stage1';
    flipStage();
});

function flipStage() {
    $('#stage4').hide();
    $('#stage3').hide();
    $('#stage2').hide();
    $('#stage1').hide();
    if (currentStage == 'stage4') {
        $('#stage4').show();
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
            if (json.status == 'processing') {
                currentStage = 'stage3';
                flipStage();
            }
            else if(json.status == 'finished') {
                currentStage = 'stage4';
                response = json.result;
                flipStage();
            }
            else if(json.status == 'error') {
                alert('An error was detected in the backend. Please check the output logs and investigate further');
                currentStage = 'stage1';
                flipStage();
            }
        })
        .catch(() => {
            isWaiting = false;
        });
    }
}, 10000);

$('.expand').on('click', function() {
    var expanded = $(this).data('expanded');
    if (!expanded) {
        expanded = {
            isExpanded: false
        };
    }
    if (expanded.isExpanded === false) {
        expanded.isExpanded = true;
        $(this).html("(-)");
        if ($(this).hasClass('group')) {
            $('.issuers', $(this).parent().parent()).show();
        }
        else {
            $('.credentials-wrapper', $(this).parent().parent()).show();
        }
    }
    else {
        expanded.isExpanded = false;
        $(this).html("(+)");
        if ($(this).hasClass('group')) {
            $('.issuers', $(this).parent().parent()).hide();
        }
        else {
            $('.credentials-wrapper', $(this).parent().parent()).hide();
        }
    }
    $(this).data('expanded', expanded);
});
            </script>
        </div>
    </body>
</html>