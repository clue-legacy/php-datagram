<?php

namespace Datagram;

use Datagram\SocketInterface;
use Datagram\BufferStreamSocket;
use React\EventLoop\LoopInterface;
use Evenement\EventEmitter;
use Exception;

class Socket extends EventEmitter implements SocketInterface
{
    protected $loop;
    protected $socket;

    protected $buffer;

    public $bufferSize = 65536;

    public function __construct(LoopInterface $loop, $socket)
    {
        $this->loop = $loop;
        $this->socket = $socket;

        $this->buffer = $this->createBuffer();
        $that = $this;
        $this->buffer->on('error', function ($error) use ($that) {
            $that->emit('error', array($error, $that));
        });
        $this->buffer->on('close', array($this, 'close'));

        $this->resume();
    }

    public function getAddress()
    {
        if ($this->socket !== false) {
            return stream_socket_get_name($this->socket, false);
        }
    }

    public function getPort()
    {
        $address = $this->getAddress();
        return (int)substr($address, strrpos($address, ':') + 1);
    }

    public function getHost()
    {
        $address = $this->getAddress();
        return trim(substr($address, 0, strrpos($address, ':')), '[]');
    }

    public function send($data, $remoteAddress = null)
    {
        $this->buffer->send($data, $remoteAddress);
    }


    public function pause()
    {
        $this->loop->removeReadStream($this->socket);
    }

    public function resume()
    {
        if ($this->socket !== false) {
            $this->loop->addReadStream($this->socket, array($this, 'onReceive'));
        }
    }

    public function onReceive()
    {
        try {
            $data = $this->handleReceive($peer);
        }
        catch (Exception $e) {
            // emit error message and local socket
            $this->emit('error', array($e, $this));
            return;
        }

        $this->emit('message', array($data, $peer, $this));
    }

    public function close()
    {
        if ($this->socket === false)  {
            return;
        }

        $this->emit('close', array($this));
        $this->pause();

        $this->handleClose();
        $this->socket = false;
        $this->buffer->close();

        $this->removeAllListeners();
    }

    public function end()
    {
        $this->buffer->end();
    }

    private function sanitizeAddress($address)
    {
        // doc comment suggests IPv6 address is not enclosed in square brackets?

        $pos = strrpos(':', $address);
        // this is an IPv6 address which includes colons but no square brackets
        if ($pos !== false && substr($address, 0, 1) !== '[') {
            if (strpos(':', $address) < $pos) {
                $port = substr($address, $pos + 1);
                $address = '[' . substr($address, 0, $pos) . ']:' . $port;
            }

        }
        return $address;
    }

    public function __toString()
    {
        return $this->getAddress() . ' (' . $this->socket . ')';
    }

    protected function createBuffer()
    {
        return new BufferStreamSocket($this->loop, $this->socket);
    }

    protected function handleReceive(&$peerAddress)
    {
        $data = stream_socket_recvfrom($this->socket, $this->bufferSize, 0, $peerAddress);

        if ($data === false) {
            // receiving data failed => remote side rejected one of our packets
            // due to the nature of UDP, there's no way to tell which one exactly
            // $peer is not filled either

            throw new Exception('Invalid message');
        }

        $peerAddress = $this->sanitizeAddress($peerAddress);

        return $data;
    }

    protected function handleClose()
    {
        fclose($this->socket);
    }
}
