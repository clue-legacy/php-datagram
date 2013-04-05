<?php

require_once __DIR__.'/../vendor/autoload.php';

$loop = React\EventLoop\Factory::create();

$factory = new Datagram\FactoryUnix($loop);

$factory->createServer('/var/tmp/unix.socket')->then(function (Datagram\Server $server) {
    $server->on('message', function($message, $client) {
        $client->send('hello '.$client->getAddress().'! echo: '.$message);

        echo 'client ' . $client->getAddress() . ': ' . $message . PHP_EOL;
    });
}, function($exception) {
    echo 'ERROR: ' . $exception->getMessage() . PHP_EOL;
});

$loop->run();
