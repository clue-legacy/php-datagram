<?php

require_once __DIR__.'/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$factory = new Datagram\FactoryUnix($loop);

$factory->createClient('/var/tmp/unix.socket')->then(function (Datagram\Client $client) use ($loop) {
    $client->send('first');

    $client->on('message', function($message, $server) {
        //$remote->send() is same as $client->send()

        echo 'received "' . $message . '" from ' . $server->getAddress() . PHP_EOL;
    });

    $client->on('error', function($error, $server) {
        echo 'error from ' . $server . PHP_EOL;
    });

    $n = 0;
    $loop->addPeriodicTimer(2.0, function() use ($client, &$n) {
        $client->send('tick' . ++$n);
    });

    // read input from STDIN and forward everything to server
    $loop->addReadStream(STDIN, function () use ($client) {
        $client->send(trim(fgets(STDIN, 2000)));
    });
}, function($error) {
    echo 'ERROR: ' . $error->getMessage() . PHP_EOL;
});

$loop->run();
