<?php

namespace Datagram;

use React\Promise\When;
use \Exception;
use \InvalidArgumentException;

class FactoryUnix
{
    const DUPLICATE_FAIL = 1;
    const DUPLICATE_REBIND = 2;

    protected $loop;

    public function __construct(LoopInterface $loop)
    {
        if (array_search('udg', stream_get_transports()) === false) {
            throw new Exception('No support for UDG transport in your installation');
        }

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

    public function createServer($path, $chmod = null, $duplicateMode = self::DUPLICATE_FAIL)
    {
        /** @url http://stackoverflow.com/questions/7405932/how-to-know-whether-any-process-is-bound-to-a-unix-domain-socket */
        if (file_exists($path)) {
            if ($duplicateMode === self::DUPLICATE_FAIL) {
                return When::reject(new Exception('Unable to create server socket: socket path already exists'));
            } else if($duplicateMode === self::DUPLICATE_REBIND) {
                if (unlink($path) === false) {
                    return When::reject(new Exception('Unable to remove existing socket path'));
                }
            } else {
                return When::reject(new InvalidArgumentException('Invalid duplicate mode given'));
            }
        }

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

        return When::resolve(new UnixServer($this->loop, $socket, $address));
    }

    private function createAdress($path)
    {
        return 'udg://' . $path;
    }
}
