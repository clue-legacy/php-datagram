<?php

namespace Datagram;

class UnixServer extends Server
{
    public function close()
    {
        parent::close();
        unlink($this->address);
    }
}
