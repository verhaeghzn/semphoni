<?php

it('uses large default limits for reverb message and request sizes', function () {
    putenv('REVERB_MAX_REQUEST_SIZE');
    putenv('REVERB_APP_MAX_MESSAGE_SIZE');

    unset(
        $_ENV['REVERB_MAX_REQUEST_SIZE'],
        $_SERVER['REVERB_MAX_REQUEST_SIZE'],
        $_ENV['REVERB_APP_MAX_MESSAGE_SIZE'],
        $_SERVER['REVERB_APP_MAX_MESSAGE_SIZE'],
    );

    $projectRoot = dirname(__DIR__, 2);

    /** @var array<string, mixed> $config */
    $config = require $projectRoot.'/config/reverb.php';

    expect($config['servers']['reverb']['max_request_size'])
        ->toBeInt()
        ->toBeGreaterThanOrEqual(25_000_000);

    expect($config['apps']['apps'][0]['max_message_size'])
        ->toBeInt()
        ->toBeGreaterThanOrEqual(25_000_000);
});

