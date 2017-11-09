<?php

namespace Jaeger\Transport;

use Jaeger\Span;
use Jaeger\Thrift\AgentClient;
use Jaeger\Thrift\Zipkin;
use Jaeger\Transport\TUDPTransport;
use Thrift\Protocol\TCompactProtocol;

final class ZipkinTransport implements Transport
{
    private $transport;
    private $client;

    private $spans = [];

    public function __construct($address = "127.0.0.1", $port = 5775)
    {
        $this->transport = new TUDPTransport($address, $port);
        $p = new TCompactProtocol($this->transport);
        $this->client = new AgentClient($p, $p);
    }

    /**
    * Submits a new span to collectors, possibly delayed and/or with buffering.
    *
    * @param Span $span
    */
    public function append(Span $span)
    {
        // identify ourself
        $endpoint = new Zipkin\Endpoint(array(
            "ipv4" => 167918715,
            "port" => 0,
            "service_name" => "monolith",
            "ipv6" => "",
        ));

        $this->spans[] = $this->encode($span, $endpoint);

        // TODO(tylerc): Buffer spans and send them in as few UDP packets as possible.
        return $this->flush();
    }

    /**
    * Flush submits the internal buffer to the remote server. It returns the
    * number of spans flushed.
    */
    public function flush()
    {
        $spans = count($this->buffer);

        // emit a batch
        $this->client->emitZipkinBatch($this->spans);

        // flush the UDP data
        $this->transport->flush();

        // reset the internal buffer
        $this->buffer = [];

        return $spans;
    }

    /**
    * Does a clean shutdown of the reporter, flushing any traces that may be
    * buffered in memory.
    */
    public function close()
    {
        $this->transport->close();
    }

    public function binaryEncode($type, $value)
    {
        switch ($type) {
            case Zipkin\AnnotationType::BOOL:
                return ($value === true) ? chr(0x01) : chr(0x00);

            case Zipkin\AnnotationType::I16:
                return pack('n', $value);

            case Zipkin\AnnotationType::I32:
                return pack('N', $value);

            case Zipkin\AnnotationType::I64:
                return pack('J', $value);

            case Zipkin\AnnotationType::DOUBLE:
                if (version_compare(PHP_VERSION, "7.0.15") >= 0) {
                    return pack('E', $value);
                } else {
                    // encode in native endianness
                    $enc = pack('d', $value);

                    // if we have a little-endian system, reverse the bytes
                    if (pack('S', 1) == "\x01\x00") {
                        $enc = strrev($enc);
                    }

                    return $enc;
                }
        }
    }

    private function encode(Span $span, $endpoint)
    {
        $startTime = (int) ($span->getStartTime() * 1000000); // microseconds
        $duration = (int) ($span->getDuration() * 1000000); // microseconds

        $annotations = array(
            new Zipkin\Annotation(array(
                "timestamp" => $startTime,
                "value" => "cs",
                "host" => $endpoint,
            )),
            new Zipkin\Annotation(array(
                "timestamp" => $startTime + $duration,
                "value" => "cr",
                "host" => $endpoint,
            )),
        );

        // add each log to the annotations
        if (is_array($span->getLogs())) {
            foreach ($span->getLogs() as $key => $value) {
                $annotations[] = new Zipkin\Annotation(array(
                    "timestamp" => $value["timestamp"],
                    "value" => json_encode($value["fields"]),
                    "host" => $endpoint,
                ));
            }
        }

        // collect binary annotations
        $binary_annotations = [];
        foreach ($span->getTags() as $key => $value) {
            if (is_bool($value)) {
                $binary_annotations[] = new Zipkin\BinaryAnnotation(array(
                    "key" => $key,
                    "value" => $this->binaryEncode(Zipkin\AnnotationType::BOOL, $value),
                    "annotation_type" => Zipkin\AnnotationType::BOOL,
                    "host" => $endpoint,
                ));
            } elseif (is_integer($value)) {
                $binary_annotations[] = new Zipkin\BinaryAnnotation(array(
                    "key" => $key,
                    "value" => $this->binaryEncode(Zipkin\AnnotationType::I64, $value),
                    "annotation_type" => Zipkin\AnnotationType::I64,
                    "host" => $endpoint,
                ));
            } elseif (is_numeric($value)) {
                $binary_annotations[] = new Zipkin\BinaryAnnotation(array(
                    "key" => $key,
                    "value" => $this->binaryEncode(Zipkin\AnnotationType::DOUBLE, $value),
                    "annotation_type" => Zipkin\AnnotationType::DOUBLE,
                    "host" => $endpoint,
                ));
            } elseif (is_string($value)) {
                $binary_annotations[] = new Zipkin\BinaryAnnotation(array(
                    "key" => $key,
                    "value" => $value,
                    "annotation_type" => Zipkin\AnnotationType::STRING,
                    "host" => $endpoint,
                ));
            }
        }

        $traceId = $span->getContext()->getTraceID();

        return new Zipkin\Span(array(
            "trace_id" => (is_array($traceId) ? $traceId["low"] : $traceId),
            "name" => $span->getOperationName(),
            "id" => $span->getContext()->getSpanID(),
            "parent_id" => $span->getContext()->getParentID(),
            "annotations" => $annotations,
            "binary_annotations" => $binary_annotations,
            "debug" => false,
            "timestamp" => $startTime,
            "duration" => $duration,
            "trace_id_high" => (is_array($traceId) ? $traceId["high"] : 0),
        ));
    }
}
