<?php

namespace Datagram;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
use \Exception;

abstract class BufferAbstract extends EventEmitter
{
    protected $loop;
    protected $socket;

    private $outgoing = array();
    private $writable = true;

    public function __construct(LoopInterface $loop, $socket)
    {
        $this->loop = $loop;
        $this->socket = $socket;
    }

    public function send($data, $remoteAddress = null)
    {
        if ($this->writable === false) {
            return;
        }

        $this->outgoing []= array($data, $remoteAddress);

        $this->resume();
    }

    public function onWritable()
    {
        list($data, $remoteAddress) = array_shift($this->outgoing);

        try {
            $this->handleWrite($data, $remoteAddress);
        }
        catch (Exception $e) {
            $this->emit('error', array($e, $this));
        }

        if (!$this->outgoing) {
            $this->pause();

            if (!$this->writable) {
                $this->close();
            }
        }
    }

    public function close()
    {
        if ($this->socket === false) {
            return false;
        }

        $this->emit('close', array($this));

        $this->pause();

        $this->writable = false;
        $this->socket = false;
        $this->outgoing = array();
        $this->removeAllListeners();
    }

    public function end()
    {
        if ($this->writable === false) {
            return;
        }

        $this->writable = false;

        if (!$this->outgoing) {
            $this->close();
        }
    }

    abstract protected function resume();

    abstract protected function pause();

    abstract protected function handleWrite($data, $remoteAddress);
}
