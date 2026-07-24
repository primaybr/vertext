<?php

define('DS', DIRECTORY_SEPARATOR);
define('ROOT', dirname(dirname(__FILE__)).DS);

require_once '../Core/Boot.php';

// Behind a reverse proxy/Ingress (Kubernetes) - trust its forwarded IP headers
// only from the proxy itself, never blindly, so Core\Http\Client::getIpAddress()
// (rate limiting, the /api/v1/home allowlist, admin IP logging) sees the real
// visitor IP instead of the proxy's own.
$trustedProxies = getenv('TRUSTED_PROXIES');
if ($trustedProxies !== false && $trustedProxies !== '') {
    \Core\Http\Client::setTrustedProxies(array_filter(array_map('trim', explode(',', $trustedProxies))));
}

$app = new Core\Base();

$app->run();
