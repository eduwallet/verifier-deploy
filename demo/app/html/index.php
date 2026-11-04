<?php
// Front controller of the API. Everything under /api is routed here; the vendor, src and private
// directories live outside the docroot.
require __DIR__ . '/../vendor/autoload.php';

use Demo\App;
use Demo\Config;
use Demo\Http\CurlTransport;
use Demo\Http\Request;
use Demo\Http\Response;
use Demo\Session\NativeSession;

try {
    $config = Config::load(__DIR__ . '/../private');
    $app = new App($config, new NativeSession($config->bool('DEMO_COOKIE_SECURE', true)), new CurlTransport());
    $app->handle(Request::fromGlobals())->send();
} catch (\Throwable $e) {
    error_log('startup failure: ' . $e->getMessage());
    Response::json(500, ['error' => ['code' => 'internal_error', 'message' => 'Internal server error']])->send();
}
