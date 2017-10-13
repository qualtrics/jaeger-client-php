<?php

namespace Shared\Libraries\Jaeger;

use OpenTracing\Reference;
use Shared\Libraries\Jaeger\Thrift\AgentClient;
use Shared\Libraries\Jaeger\Thrift\Batch;
use Shared\Libraries\Jaeger\Thrift\Log;
use Shared\Libraries\Jaeger\Thrift\Process;
use Shared\Libraries\Jaeger\Thrift\Span as JTSpan;
use Shared\Libraries\Jaeger\Thrift\SpanRef;
use Shared\Libraries\Jaeger\Thrift\SpanRefType;
use Shared\Libraries\Jaeger\Thrift\Tag;
use Shared\Libraries\Jaeger\Thrift\TagType;
use Thrift\Protocol\TCompactProtocol;

final class JaegerReporter implements Reporter
{
	private $transport;
	private $client;

	function __construct($address = "127.0.0.1", $port = 5775)
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
    public function reportSpan(Span $span)
    {
		// TODO(tylerc): Buffer spans and send them as they accumulate; send the remainder in flush().

        // emit a batch
        $this->client->emitBatch(new Batch([
            "process" => new Process([
                "serviceName" => "monolith",
                "tags" => [
                    $this->buildTag("jaeger.version", "jaeger-php-qualtrics"),
                    $this->buildTag("hostname", gethostname()),
                    $this->buildTag("ip", "1.2.3.4"),
                    $this->buildTag("rich", true),
                    $this->buildTag("riches", 99),
                ],
            ]),
            "spans" => [
                $this->encode($span),
            ],
        ]));
    }

    /**
    * Does a clean shutdown of the reporter, flushing any traces that may be
    * buffered in memory.
    */
    public function close()
    {
    	$this->transport->close();
    }

    public function encode(Span $span)
    {
        $startTime = (int) ($span->getStartTime() * 1000000); // microseconds
        $duration = (int) ($span->getDuration() * 1000000); // microseconds

        $references = array_map(function(Reference $reference) {

            $type = null;
            if ($reference->isType(Reference::CHILD_OF)) {
                $type = SpanRefType::CHILD_OF;
            } else if ($reference->isType(Reference::FOLLOWS_FROM)) {
                $type = SpanRefType::FOLLOWS_FROM;
            }

            $traceId = $reference->getContext()->getTraceID();

            return new SpanRef([
                "refType" => $type,
                "traceIdLow" => (is_array($traceId) ? $traceId["low"] : $traceId),
                "traceIdHigh" => (is_array($traceId) ? $traceId["high"] : 0),
                "spanId" => $reference->getContext()->getSpanID(),
            ]);

        }, $span->getReferences());

        $tags = $this->buildTags($span->getTags());

        $logs = array_map(function($log) {
            return new Log([
                "timestamp" => $log["timetamp"],
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
        foreach ($tagPairs as $key => $value)
        {
            $tags[] = $this->buildTag($key, $value);
        }
        return $tags;
    }

    private function buildTag($key, $value)
    {
        if (is_bool($value))
        {
            return new Tag([
                "key" => $key,
                "vType" => TagType::BOOL,
                "vBool" => $value,
            ]);
        }
        else if (is_integer($value))
        {
            return new Tag([
                "key" => $key,
                "vType" => TagType::LONG,
                "vLong" => $value,
            ]);
        }
        else if (is_numeric($value))
        {
            return new Tag([
                "key" => $key,
                "vType" => TagType::DOUBLE,
                "vDouble" => $value,
            ]);
        }
        else if (is_string($value))
        {
            return new Tag([
                "key" => $key,
                "vType" => TagType::STRING,
                "vStr" => $value,
            ]);
        }

        throw new \Exception("unsupported tag type");
    }

}