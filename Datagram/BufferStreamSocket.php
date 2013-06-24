<?php

namespace Datagram;

use Evenement\EventEmitter;
use Exception;

class BufferStreamSocket extends BufferAbstract
{
    private $listening = false;

    protected function pause()
    {
        if ($this->listening) {
            $this->loop->removeWriteStream($this->socket);
            $this->listening = false;
        }
    }

    protected function resume()
    {
        if (!$this->listening) {
            $this->loop->addWriteStream($this->socket, array($this, 'onWritable'));
            $this->listening = true;
        }
    }

    protected function handleWrite($data, $remoteAddress)
    {
        if ($remoteAddress === null) {
            // do not use fwrite() as it obeys the stream buffer size and
            // packets are not to be split at 8kb
            $ret = @stream_socket_sendto($this->socket, $data);
        } else {
            $ret = @stream_socket_sendto($this->socket, $data, 0, $remoteAddress);
        }

        if ($ret < 0) {
            $error = error_get_last();
            throw new Exception('Unable to send packet: ' . trim($error['message']));
        }
    }
}
