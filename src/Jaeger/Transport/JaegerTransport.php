<?php

namespace Jaeger\Transport;

use Jaeger\Span;
use Jaeger\Thrift\AgentClient;
use Jaeger\Thrift\Batch;
use Jaeger\Thrift\Log;
use Jaeger\Thrift\Process;
use Jaeger\Thrift\Span as JTSpan;
use Jaeger\Thrift\SpanRef;
use Jaeger\Thrift\SpanRefType;
use Jaeger\Thrift\Tag;
use Jaeger\Thrift\TagType;
use OpenTracing\Reference;
use Thrift\Protocol\TBinaryProtocolAccelerated;
use Thrift\Protocol\TCompactProtocol;
use Thrift\Exception\TTransportException;

final class JaegerTransport implements Transport
{
    // DEFAULT_BUFFER_SIZE indicates the default maximum buffer size, or the size threshold
    // at which the buffer will be flushed to the agent.
    const DEFAULT_BUFFER_SIZE = 100;

    private $transport;
    private $client;

    private $buffer = [];
    private $process = null;
    private $maxBufferSize = 0;

    public function __construct($address = "127.0.0.1", $port = 5775, $maxBufferSize = 0, bool $binaryProtocol = false)
    {
        $this->transport = new TUDPTransport($address, $port);
        $p = $binaryProtocol ? new TBinaryProtocolAccelerated($this->transport) : new TCompactProtocol($this->transport);
        $this->client = new AgentClient($p, $p);

        $this->maxBufferSize = ($maxBufferSize > 0 ? $maxBufferSize : self::DEFAULT_BUFFER_SIZE);
    }

    /**
    * Submits a new span to collectors, possibly delayed and/or with buffering.
    *
    * @param Span $span
    */
    public function append(Span $span)
    {
        // Grab a copy of the process data, if we didn't already.
        if ($this->process == null) {
            $this->process = new Process([
                "serviceName" => $span->getTracer()->getServiceName(),
                "tags" => $this->buildTags($span->getTracer()->getTags()),
            ]);
        }

        $this->buffer[] = $this->encode($span);

        // TODO(tylerc): Buffer spans and send them in as few UDP packets as possible.
        return $this->flush();
    }

    /**
    * Flush submits the internal buffer to the remote server. It returns the
    * number of spans flushed.
    *
    * @param $force bool - force a flush, even on a partial buffer
    */
    public function flush($force = false)
    {
        $spans = count($this->buffer);

        // buffer not full yet
        if (!$force && $spans < $this->maxBufferSize) {
            return 0;
        }

        // no spans to flush
        if ($spans <= 0) {
            return 0;
        }

        try {
            // emit a batch
            $this->client->emitBatch(new Batch([
                "process" => $this->process,
                "spans" => $this->buffer,
            ]));

            // flush the UDP data
            $this->transport->flush();

            // reset the internal buffer
            $this->buffer = [];
        } catch (TTransportException $e) {
            error_log("jaeger: transport failure: " . $e->getMessage());
            return 0;
        }

        return $spans;
    }

    /**
    * Does a clean shutdown of the reporter, flushing any traces that may be
    * buffered in memory.
    */
    public function close()
    {
        $this->flush(true); // flush all remaining data

        $this->transport->close();
    }

    private function encode(Span $span)
    {
        $startTime = (int) ($span->getStartTime() * 1000000); // microseconds
        $duration = (int) ($span->getDuration() * 1000000); // microseconds

        $references = array_map(function (Reference $reference) {

            $type = null;
            if ($reference->isType(Reference::CHILD_OF)) {
                $type = SpanRefType::CHILD_OF;
            } elseif ($reference->isType(Reference::FOLLOWS_FROM)) {
                $type = SpanRefType::FOLLOWS_FROM;
            }

            $traceId = $reference->getSpanContext()->getTraceID();

            return new SpanRef([
                "refType" => $type,
                "traceIdLow" => (is_array($traceId) ? $traceId["low"] : $traceId),
                "traceIdHigh" => (is_array($traceId) ? $traceId["high"] : 0),
                "spanId" => $reference->getSpanContext()->getSpanID(),
            ]);
        }, $span->getReferences());

        $tags = $this->buildTags($span->getTags());

        $logs = array_map(function ($log) {
            return new Log([
                "timestamp" => $log["timestamp"],
                "fields" => $this->buildTags($log["fields"]),
            ]);
        }, $span->getLogs());

        $traceId = $span->getContext()->getTraceID();
        $parentSpanId = $span->getContext()->getParentID();

        return new JTSpan([
            "traceIdLow" => (is_array($traceId) ? $traceId["low"] : $traceId),
            "traceIdHigh" => (is_array($traceId) ? $traceId["high"] : 0),
            "spanId" => $span->getContext()->getSpanID(),
            "parentSpanId" => (is_numeric($parentSpanId) ? $parentSpanId : 0),
            "operationName" => $span->getOperationName(),
            "references" => $references,
            "flags" => $span->getContext()->getFlags(),
            "startTime" => $startTime,
            "duration" => $duration,
            "tags" => $tags,
            "logs" => $logs,
        ]);
    }

    private function buildTags($tagPairs)
    {
        $tags = [];
        foreach ($tagPairs as $key => $value) {
            $tags[] = $this->buildTag($key, $value);
        }
        return $tags;
    }

    private function buildTag($key, $value)
    {
        if (is_bool($value)) {
            return new Tag([
                "key" => $key,
                "vType" => TagType::BOOL,
                "vBool" => $value,
            ]);
        } elseif (is_string($value)) {
            return new Tag([
                "key" => $key,
                "vType" => TagType::STRING,
                "vStr" => $value,
            ]);
        } elseif (is_null($value)) {
            return new Tag([
                "key" => $key,
                "vType" => TagType::STRING,
                "vStr" => "",
            ]);
        } elseif (is_integer($value)) {
            return new Tag([
                "key" => $key,
                "vType" => TagType::LONG,
                "vLong" => $value,
            ]);
        } elseif (is_numeric($value)) {
            return new Tag([
                "key" => $key,
                "vType" => TagType::DOUBLE,
                "vDouble" => $value,
            ]);
        }

        error_log("Cannot build tag for " . $key . " of type " . gettype($value));
        throw new \Exception("unsupported tag type");
    }
}
