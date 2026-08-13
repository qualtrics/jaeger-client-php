<?php

namespace JaegerTests\Unit\Transport;

use Jaeger\Transport\TUDPTransport;
use PHPUnit\Framework\TestCase;
use Thrift\Exception\TTransportException;
use Thrift\Transport\TTransport;

class TUDPTransportTest extends TestCase
{
    public function testIsLoadableAgainstInstalledThriftVersion()
    {
        $transport = new TUDPTransport("127.0.0.1", 6832);

        $this->assertInstanceOf(TTransport::class, $transport);
        $this->assertTrue($transport->isOpen());

        $transport->close();
        $this->assertFalse($transport->isOpen());
    }

    public function testSignaturesRemainCompatibleWithTTransport()
    {
        $expectedReturnTypes = [
            "isOpen" => "bool",
            "open" => "void",
            "close" => "void",
            "read" => "string",
            "write" => "void",
            "flush" => "void",
        ];

        $parent = new \ReflectionClass(TTransport::class);
        $child = new \ReflectionClass(TUDPTransport::class);

        foreach ($expectedReturnTypes as $name => $expectedReturnType) {
            $childMethod = $child->getMethod($name);
            $this->assertSame(
                TUDPTransport::class,
                $childMethod->getDeclaringClass()->getName(),
                "TUDPTransport::{$name}() is expected to be overridden here"
            );
            $this->assertSame(
                $expectedReturnType,
                (string) $childMethod->getReturnType(),
                "TUDPTransport::{$name}() must return {$expectedReturnType} to match apache/thrift >= 0.24"
            );

            foreach ($childMethod->getParameters() as $childParam) {
                $this->assertFalse(
                    $childParam->hasType(),
                    "TUDPTransport::{$name}() must not type parameter \${$childParam->getName()}; "
                        . "it breaks loading under apache/thrift < 0.24"
                );
            }

            $parentMethod = $parent->getMethod($name);
            if ($parentMethod->hasReturnType()) {
                $this->assertSame(
                    (string) $parentMethod->getReturnType(),
                    (string) $childMethod->getReturnType(),
                    "TUDPTransport::{$name}() return type must match the installed TTransport"
                );
            }
        }
    }

    public function testWriteBuffersUntilFlush()
    {
        $transport = new TUDPTransport("127.0.0.1", 6832);
        $transport->write("hello ");
        $transport->write("world");

        $this->assertSame("hello world", $this->bufferOf($transport));
    }

    public function testFlushOnEmptyBufferSendsNothing()
    {
        $transport = new TUDPTransport("127.0.0.1", 6832);
        $transport->flush();

        $this->assertSame("", $this->bufferOf($transport));
    }

    public function testCloseIsIdempotent()
    {
        $transport = new TUDPTransport("127.0.0.1", 6832);
        $transport->close();
        $transport->close();

        $this->assertFalse($transport->isOpen());
    }

    public function testFlushOnClosedTransportThrowsInsteadOfFatalling()
    {
        $transport = new TUDPTransport("127.0.0.1", 6832);
        $transport->write("undeliverable");
        $transport->close();

        $this->expectException(TTransportException::class);
        $transport->flush();
    }

    public function testWriteRejectsDataLargerThanOneUdpPacket()
    {
        $transport = new TUDPTransport("127.0.0.1", 6832);

        $this->expectException(TTransportException::class);
        $transport->write(str_repeat("x", TUDPTransport::MAX_UDP_PACKET + 1));
    }

    public function testReadIsUnsupported()
    {
        $transport = new TUDPTransport("127.0.0.1", 6832);

        $this->expectException(TTransportException::class);
        $transport->read(8);
    }

    private function bufferOf(TUDPTransport $transport)
    {
        $property = new \ReflectionProperty(TUDPTransport::class, "buffer");

        return $property->getValue($transport);
    }
}
