<?php

namespace Shared\Libraries\Jaeger;

use OpenTracing\Propagators\Reader;
use OpenTracing\Propagators\Writer;
use OpenTracing\SpanContext;
use OpenTracing\SpanReference;
use OpenTracing\Tracer;
use Shared\Libraries\Jaeger\AlwaysSampler;
use Shared\Libraries\Jaeger\Sampler;
use Shared\Libraries\Jaeger\JSpanContext;
use Shared\Libraries\Jaeger\JSpan;
use Shared\Libraries\Log;

use Shared\Libraries\Jaeger\Thrift\AgentClient;
use Shared\Libraries\Jaeger\Thrift\Batch;
use Shared\Libraries\Jaeger\Thrift\Process;
use Shared\Libraries\Jaeger\Thrift\Span;
use Shared\Libraries\Jaeger\Thrift\Tag;
use Shared\Libraries\Jaeger\Thrift\TagType;
use Shared\Libraries\Jaeger\Thrift\Zipkin;

use Thrift\Protocol\TCompactProtocol;
use Thrift\Serializer\TBinarySerializer;
use Thrift\Transport\TMemoryBuffer;

final class JTracer implements Tracer
{
    private $sampler;

    private $spans;

    public static function create($sampler)
    {
        $tracer = new self();
        $tracer->sampler = $sampler;
        $tracer->spans = [];
        return $tracer;
    }

    public function startSpan($operationName, $options) {
        $span = JSpan::create($this, $operationName, $options);

        // configure a context for the new span
        $ctx = $span->getContext();
        $ctx->setSampled($this->sampler->IsSampled($ctx->getTraceID(), $span->getOperationName()));
        $span->setContext($ctx);

        return $span;
    }

    public function inject(SpanContext $spanContext, $format, Writer $carrier)
    {
        // TODO(tylerc): Implement this.
    }

    public function extract($format, Reader $carrier)
    {
        // TODO(tylerc): Implement this.
        return SpanContext::createAsDefault();
    }

    public function flush()
    {
        // do nothing
    }

    // ---

    public function reportSpan($span)
    {
        // TODO(tylerc): Buffer spans and send them as they accumulate; send the remainder in flush().

        $this->sendZipkinSpan($span);
    }

    private function sendZipkinSpan($span)
    {
        $p = new TCompactProtocol(new TUDPTransport("10.2.60.55", "5775"));
        $client = new AgentClient($p, $p);

        // identify ourself
        $endpoint = new Zipkin\Endpoint(array(
            "ipv4" => 167918715,
            "port" => 0,
            "service_name" => "monolith",
            "ipv6" => "",
        ));

        // emit a batch
        $client->emitZipkinBatch([
            $span->zipkinify($endpoint),
        ]);
    }

}