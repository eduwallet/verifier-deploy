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
                        <?php foreach ($group['issuers'] as $ikey => $issuer) :?>
                            <div class="issuer">
                                <h3 class="issuertitle"><span class='issuer expand'>(+)</span><?= $issuer["name"] ?></h3>
                                <div class='credentials-wrapper'>
                                    <div class='qrcodeui'>
                                        <div class="stage2">
                                            <h1 class="credential"></h1>
                                            <p>Please start the flow by scanning the QR code with your wallet app</p>
                                            <div class="qrcode"></div>
                                            <button class="refresh">Refresh</button>                                        
                                            <button class="close right">Close</button>  
                                        </div>
                                        <div class="stage3">
                                            <H1>Requesting Response</H1>
                                            <p>Please proceed on your device to select the correct requested credential.</p>
                                            <button class="close right">Close</button>
                                        </div>
                                        <div class="stage4">
                                            <H1>Flow Completed</H1>
                                            <button class="close right">Close</button>
                                        </div>
                                    </div>
                                    <div class="credentials">
                                    <?php foreach ($issuer["credentials"] as $credential) :?>
                                        <div class="credential">
                                            <button class="cred" data-cred='<?php echo json_encode(["group" => $gkey, "issuer" => $ikey, "credential" => $credential['short']]) ?>'>
                                                <?=  $credential['name'] ?>
                                            </button>
                                        </div>
                                    <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <script type="text/javascript">

var code;
var response = null;
var backendurl = 'backendcall.php';
var selectedcredential = '';
var selectedgroup = '';
var selectedissuer = '';
var state = 'none';
var currentStage = 'stage1';
var isWaiting = false;

function createNewQR(el) {
    $(el).html('');
    code = new QRCode(el);
    return $.ajax({
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
        try {
            // there is an obscure bug that triggers if you give the library
            // a text of length between 192 and 220 characters
            // https://stackoverflow.com/questions/30796584/qrcode-js-error-code-length-overflow-17161056
            // The fix is to pad the uri with spaces until it is 220 characters long
            console.log('making QR code with ', json.requestUri);
            code.makeCode(json.requestUri.padEnd(220));
        }
        catch (e) {
            console.log(e);
            alert("There was an error creating a QR code" + e);
        }
        state = json.state;
        return true;
    })
    .catch((e) => {
        console.log(e);
        if (e.status == 403) {
            alert("Access was forbidden");
        }
        else if(e.status == 404) {
            alert("Credential was not found");
        }
        else {
            alert("There was an error creating a url " + e.statusText);
        }
        return false;
    });
}

$('button.cred').on('click', function() {
    var data = JSON.parse($(this).attr('data-cred'));
    var qrelement = $('.qrcodeui .qrcode', $(this).parents('.credentials-wrapper'))[0];
    selectedcredential = data.credential;
    selectedgroup = data.group;
    selectedissuer = data.issuer;
    createNewQR(qrelement).then((e) => {
        if (e) {
            currentStage = 'stage2';
            console.log('switching to stage 2');
            flipStage();
        }
    });
});
$('button.refresh').on('click', function() {
    var qrelement = $('.qrcode', $(this).parent())[0];
    createNewQR(qrelement).then((e) => {
        if (e) {
            currentStage = 'stage2';
            console.log('switching to stage 2');
            flipStage();
        }
    });
});
$('button.close').on('click', () => {
    currentStage = 'stage1';
    flipStage();
});

function flipStage() {
    $('.stage4').hide();
    $('.stage3').hide();
    $('.stage2').hide();
    $('.stage1').hide();
    if (currentStage == 'stage4') {
        $('.stage4').show();
    }
    else if (currentStage == 'stage3') {
        $('.stage3').show();
    }
    else if (currentStage == 'stage2') {
        $('.stage2').show();
    }
    else {
        $('.stage1').show();
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
}, 1000);

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