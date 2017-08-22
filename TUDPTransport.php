<?php

namespace Shared\Libraries\Jaeger;

use Thrift\Transport\TTransport;
use Thrift\Exception\TTransportException;

class TUDPTransport extends TTransport
{
	const MAX_UDP_PACKET = 65000;

	protected $server;
	protected $port;

	protected $socket = NULL;
	protected $buffer;

	// this implements a TTransport over UDP
	function __construct($server, $port)
	{
		$this->server = $server;
		$this->port = $port;

		// open a UDP socket to somewhere
		if (!($this->socket = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP)))
		{
		    $errorcode = socket_last_error();
		    $errormsg = socket_strerror($errorcode);
		     
		    echo "Couldn't create socket: [$errorcode] $errormsg \n";

		    throw new TTransportException("unable to open UDP socket", TTransportException::UNKNOWN);
		}
	}

	public function isOpen()
	{
		return $this->socket != NULL;
	}

	// Open does nothing as connection is opened on creation
	// Required to maintain thrift.TTransport interface
	public function open()
	{
		return;
	}

	public function close()
	{
		socket_close($this->socket);
		$this->socket = NULL;
	}

	public function read($len)
	{
		// not implemented
		echo "reading from thrift udp socket";
	}

	public function write($buf)
	{
		// ensure that the data will still fit in a UDP packeg
		if (strlen($this->buffer) + strlen($buf) > self::MAX_UDP_PACKET) {
			return new TTransportException("Data does not fit within one UDP packet", TTransportException::UNKNOWN);
		}

		// buffer up some data
		$this->buffer .= $buf;

	}

	public function flush()
	{
		// flush the buffer to the socket
		if (!socket_sendto($this->socket, $this->buffer, strlen($this->buffer), 0, $this->server, $this->port))
		{
			$errorcode = socket_last_error();
			$errormsg = socket_strerror($errorcode);

			die("Could not send data: [$errorcode] $errormsg \n");
			return new TTransportException("Data does not fit within one UDP packet", TTransportException::UNKNOWN);
		}

	}

}