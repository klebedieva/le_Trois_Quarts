<?php

use App\Kernel;
use Symfony\Component\HttpFoundation\Request;

require_once dirname(__DIR__).'/vendor/autoload_runtime.php';

return function (array $context) {
    $request = Request::createFromGlobals();
    Request::setTrustedProxies(
        ['127.0.0.1', $request->server->get('REMOTE_ADDR')],
        Request::HEADER_X_FORWARDED_ALL
    );

    return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
};
