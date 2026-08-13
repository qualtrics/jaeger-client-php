<?php

namespace Jaeger\Transport;

use Thrift\Transport\TTransport;
use Thrift\Exception\TTransportException;

class TUDPTransport extends TTransport
{
    const MAX_UDP_PACKET = 65000;

    protected $server;
    protected $port;

    protected $socket = null;
    protected $buffer = "";

    // this implements a TTransport over UDP
    public function __construct($server, $port)
    {
        $this->server = $server;
        $this->port = $port;

        // open a UDP socket to somewhere
        if (!($this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
             
            error_log("jaeger: transport: Couldn't create socket: [$errorcode] $errormsg");

            throw new TTransportException("unable to open UDP socket", TTransportException::UNKNOWN);
        }
    }

    public function isOpen(): bool
    {
        return $this->socket != null;
    }

    // Open does nothing as connection is opened on creation
    // Required to maintain thrift.TTransport interface
    public function open(): void
    {
        return;
    }

    public function close(): void
    {
        if ($this->socket === null) {
            return;
        }

        socket_close($this->socket);
        $this->socket = null;
    }

    public function read($len): string
    {
        throw new TTransportException("UDP transport is write-only", TTransportException::UNKNOWN);
    }

    public function write($buf): void
    {
        // ensure that the data will still fit in a UDP packeg
        if (strlen($this->buffer) + strlen($buf) > self::MAX_UDP_PACKET) {
            throw new TTransportException("Data does not fit within one UDP packet", TTransportException::UNKNOWN);
        }

        // buffer up some data
        $this->buffer .= $buf;
    }

    public function flush(): void
    {
        // no data to send; don't send a packet
        if (strlen($this->buffer) == 0) {
            return;
        }

        if ($this->socket === null) {
            throw new TTransportException("cannot flush a closed UDP transport", TTransportException::NOT_OPEN);
        }

        // TODO(tylerc): This assumes that the whole buffer successfully sent... I believe
        // that this should always be the case for UDP packets, but I could be wrong.

        // flush the buffer to the socket
        if (!socket_sendto($this->socket, $this->buffer, strlen($this->buffer), 0, $this->server, $this->port)) {
            $errorcode = socket_last_error();
            $errormsg = socket_strerror($errorcode);
            error_log("jaeger: transport: Could not flush data: [$errorcode] $errormsg");
        }

        $this->buffer = ""; // empty the buffer
    }
}
