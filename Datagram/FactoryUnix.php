<?php

namespace Datagram;

use React\Promise\When;

class FactoryUnix
{
    protected $loop;

    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    public function createClient($path)
    {
        $address = $this->createAddress($path);

        $socket = stream_socket_client($address, $errno, $errstr);
        if (!$socket) {
            return When::reject(new Exception('Unable to create client socket: ' . $errstr, $errno));
        }

        return When::resolve(new Client($this->loop, $socket, $address));
    }

    public function createServer($path, $chmod = null)
    {
        $address = $this->createAddress($path);

        $socket = stream_socket_server($address, $errno, $errstr, STREAM_SERVER_BIND);
        if (!$socket) {
            return When::reject(new Exception('Unable to create server socket: ' . $errstr, $errno));
        }

        if ($chmod !== null) {
            if (chmod($path, $chmod) === false) {
                // chmod failed => clean up socket
                fclose($socket);
                unlink($path);

                return When::reject(new Exception('Unable to chmod server socket'));
            }
        }

        return When::resolve(new Server($this->loop, $socket, $address));
    }

    private function createAdress($path)
    {
        return 'udg://' . $path;
    }
}
