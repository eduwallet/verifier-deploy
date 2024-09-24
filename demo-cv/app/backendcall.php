<?php
session_start();
include_once(__DIR__ . '/vendor/autoload.php');
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->safeLoad();

$adminToken = $_SESSION['token'];
$bearerToken = $_ENV['BEARER_TOKEN'];
$serviceurl = $_ENV['SERVICE_URL'];
$credential = $_POST['credential'];
$states = $_SESSION['states'] ?? [];
$allowedcredentials = array_map(fn ($x) => trim($x), explode(',', $_ENV["CREDENTIAL_TYPES"] ?? 'ABC,PID'));

error_log("token $adminToken, bearer $bearerToken");
error_log(json_encode($_POST));

if (!isset ($_POST['token']) || $_POST['token'] !== $adminToken) {
    error_log("token mismatch " . $_POST['token'] . ' vs ' . $adminToken);
    http_response_code(403);
    die(403);
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, false);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: Bearer ' . $bearerToken
]);

switch ($_POST['action']) {
    case 'create':
        error_log("creating request object");
        if (!in_array($credential, $allowedcredentials)) {
            error_log('credential not supported "' . $credential . '" vs ' . json_encode($allowedcredentials));
            http_response_code(403);
            die(403);
        }

        curl_setopt($ch, CURLOPT_URL, $serviceurl . $credential);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, [
            // empty data set for now
        ]);
        try {
            error_log("calling curl_exec " . $serviceurl . $credential);
            $response = json_decode(curl_exec($ch));
            error_log("output is " . json_encode($response));
        }
        catch (Exception $e) {
            error_log("caught exception " . $e->getMessage());
        }
        if (!is_object($response)) {
            error_log("invalid response");
            curl_close($ch);
            http_response_code(403);
            die(403);
        }

        $states[$response->state] = $response;
        $_SESSION['states'] = $states;

        error_log("replying with request result");
        echo json_encode([
            'state' => $response->state,
            'requestUri' => $response->requestUri
        ]);
        break;
    case 'state':
        error_log("calling status callback");
        $state = $_POST['state'];
        if (!isset($states[$state])) {
            curl_close($ch);
            http_response_code(403);
            die(403);
        }
        $checkUri = $states[$state]->checkUri;
        curl_setopt($ch, CURLOPT_URL, $checkUri);
        curl_setopt($ch, CURLOPT_GET, true);

        $response = json_decode(curl_exec($ch));
        if (!is_object($response)) {
            curl_close($ch);
            http_response_code(403);
            die(403);
        }

        echo json_encode($response);
        break;
    default:
        error_log("unrecognized action");
        curl_close($ch);
        http_response_code(403);
        die(403);
}

curl_close($ch);
