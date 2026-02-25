<?php
session_start();

$adminToken = $_SESSION['token'] ?? '';
$stateid = bin2hex(random_bytes(16));
$state = [
    'id' => $stateid
];
$states = $_SESSION['states'] ?? [];

include('config.php');
include('setTokens.php');
$groups = setTokens($groups);

$token = $_POST['token'] ?? ($_GET['token'] ?? '');

if ($token !== $adminToken && $token != 'callback') {
    error_log("token mismatch " . $_POST['token'] . ' vs ' . $adminToken);
    http_response_code(403);
    die(403);
}
$ckey = $_POST['credential'] ?? ($_GET['credential'] ?? '');
$gkey = $_POST['group'] ?? ($_GET['group'] ?? '');
$ikey = $_POST['issuer'] ?? ($_GET['issuer'] ?? '');

if (isset($_POST['state']) && empty($gkey)) {
    $stateid = $_POST['state'];
    if (isset($states[$stateid])) {
        $state = $states[$stateid];
        $gkey = $state['group'];
        $ikey = $state['issuer'];
        $ckey = $state['credential'];
    }
}
error_log("$gkey $ikey $ckey");

if (!isset($groups[$gkey])) {
    error_log("invalid group selected $gkey");
    http_response_code(403);
    die(403);
}
$group = $groups[$gkey];
if (!isset($group["issuers"][$ikey])) {
    error_log("invalid issuer selected $ikey");
    http_response_code(403);
    die(403);
}
$issuer = $group['issuers'][$ikey];
$cred = null;
foreach ($issuer["credentials"] as $c) {
    if ($c['short'] === $ckey) {
        $cred = $c;
        break;
    }
}
if ($cred === null) {
    error_log("invalid credential selected $ckey");
    http_response_code(403);
    die(403);
}

$bearerToken = $issuer['token'] ?? null;
if (empty($bearerToken)) {
    error_log('Issuer not configured, missing token');
    http_response_code(403);
    die(403);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $bearerToken,
    'Content-type: application/json'
]);

$baseurl = $group['url'];
$toolname = $issuer['tool'];
if (($group['tenantDomain'] ?? false) === true) {
    $baseurl = $issuer['short'] . '.' . $toolname . '.' . $baseurl;
}
else {
    $baseurl = $toolname . '.' . $baseurl . '/' . $issuer['short'];
}

switch ($_POST['action']) {
    case 'create':
        curl_setopt($ch, CURLOPT_POST, true);
        if (($issuer['type'] ?? 'issuer') === 'issuer') {
            $data = [
                "credentials" => [$cred['credentialId']]
            ];
            if ($cred['flow'] == 'preauth') {
                $data['grants'] = ['urn:ietf:params:oauth:grant-type:pre-authorized_code' => ['pre-authorized_code' => 'generate']];
            }
            else {
                $data['grants'] = ['authorization_code' => ["issuer_state" => "generate"]];
            }

            if (isset($cred['data'])) {
                $data['credentialDataSupplierInput'] = $cred['data'];
            }
            else if (isset($cred['credential'])) {
                $data['credential'] = $cred['credential'];
            }
            else if(isset($cred['callback'])) {
                $data['credential_callback'] = $cred['callback'] + "&group=$gkey&issuer=$ikey&credential=$ckey&token=callback";
            }
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            error_log("calling $baseurl with " . json_encode($data));
            curl_setopt($ch, CURLOPT_URL, "https://" . $baseurl . '/api/create-offer');
        }
        else {
            error_log("this is a verifier at https://$baseurl/api/create-offer/" . $cred['presentation']);
            curl_setopt($ch, CURLOPT_POSTFIELDS, [
                // empty data set for now
            ]);
            curl_setopt($ch, CURLOPT_URL, "https://" . $baseurl . '/api/create-offer/' . $cred['presentation']);
        }

        try {
            $response = curl_exec($ch);
            error_log($response);
            $response = json_decode($response);
        }
        catch (Exception $e) {
            error_log("caught exception " . $e->getMessage());
        }
        if (!is_object($response)) {
            error_log("invalid response");
            http_response_code(403);
            die(403);
        }

        $state['group'] = $gkey;
        $state['issuer'] = $ikey;
        $state['credential'] = $ckey;
        if (($issuer['type'] ?? 'issuer') === 'issuer') {
            echo json_encode([
                'state' => $stateid,
                'requestUri' => $response->uri,
                'id' => $response->id
            ]);
            $state['uri'] = $response->uri;
            $state['checkUri'] = "https://" . $baseurl . '/api/check-offer';
            $state["postData"] = ["id" => $response->id];
        }
        else {
            echo json_encode([
                'state' => $stateid,
                'requestUri' => $response->requestUri
            ]);
            $state['uri'] = $response->requestUri;
            $state['checkUri'] = $response->checkUri;
        }
        break;

    case 'state':
        error_log("calling status callback");
        if (!isset($states[$stateid])) {
            error_log("state $stateid not found");
            http_response_code(403);
            die(403);
        }
        $state = $states[$stateid];
        error_log("state is "  . json_encode($state));
        $checkUri = $state['checkUri'] ?? null;
        if (!empty($checkUri)) {
            error_log("calling $checkUri");
            curl_setopt($ch, CURLOPT_URL, $checkUri);
            if (isset($state['postData'])) {
                curl_setopt($ch, CURLOPT_POST, true);
                error_log("posting " . json_encode($state["postData"]));
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($state["postData"]));
            }
            else {
                curl_setopt($ch, CURLOPT_POST, false);
            }
            $primaryResponse = curl_exec($ch);
            $response = json_decode($primaryResponse);
            error_log("response is " . $primaryResponse);
        }
        if (!is_object($response)) {
            http_response_code(403);
            die(403);
        }

        if (($issuer['type'] ?? 'issuer') === 'issuer') {
            $status = $response->status ?? null;
            if ($status === null) {
                http_response_code(403);
                die(403);
            }
            error_log("status is $status");
            switch ($status) {
                case 'OFFER_CREATED':
                    $state['status'] = "created";
                    break;
                case 'OFFER_URI_RETRIEVED':
                case 'ACCESS_TOKEN_REQUESTED':
                case 'ACCESS_TOKEN_CREATED':
                case 'CREDENTIAL_REQUEST_RECEIVED':
                    $state['status'] = 'processing';
                    break;
                case 'CREDENTIAL_ISSUED':
                    $state['status'] = 'finished';
                    break;
                default:
                case 'ERROR':
                    $state['status'] = 'error';
                    break;
            }
        }
        else {
            $status = $response->status ?? null;
            if ($status === null) {
                http_response_code(403);
                die(403);
            }
            error_log("status is $status");
            switch ($status) {
                case 'INITIALIZED':
                case 'AUTHORIZATION_REQUEST_CREATED':
                    $state['status'] = "created";
                    break;
                case 'AUTHORIZATION_REQUEST_RETRIEVED':
                case 'RESPONSE_PROCESSING':
                    $state['status'] = 'processing';
                    break;
                case 'RESPONSE_RECEIVED':
                    $state['status'] = 'finished';
                    break;
                default:
                    $state['status'] = 'error';
                    break;
            }
        }
        echo json_encode([
            "status" => $state["status"]
        ]);
        break;
    case 'datacallback':
        if ($token !== 'callback') {
            http_response_code(403);
            die(403);
        }
        // we could check group, issuer and credential, but the data we return is public anyway
        return $obccredentialdata;
        break;
    default:
        error_log("unrecognized action");
        http_response_code(403);
        die(403);
}

$states[$stateid] = $state;
$_SESSION['states'] = $states;
