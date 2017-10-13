<?php

namespace Shared\Libraries\Jaeger;

use OpenTracing\Propagators\Reader;
use OpenTracing\Propagators\Writer;
use OpenTracing\SpanContext;
use OpenTracing\SpanReference;
use OpenTracing\Tracer as OTTracer;
use Shared\Libraries\Jaeger\Span;
use Shared\Libraries\Log;

final class Tracer implements OTTracer
{
    const SAMPLER = "Sampler";
    const REPORTER = "Reporter";

    private $sampler;
    private $reporter;

    private $spans;

    public static function create($options)
    {
        $tracer = new self();
        $tracer->spans = [];

        foreach ($options as $key => $value)
        {
            switch ($key)
            {
                case Tracer::SAMPLER:
                    $tracer->sampler = $value;
                    break;

                case Tracer::REPORTER:
                    $tracer->reporter = $value;
                    break;

                default:
                    throw new \Exception("unknown option");
            }
        }
        return $tracer;
    }

    public function startSpan($operationName, $options) {
        $span = Span::create($this, $operationName, $options);

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
        $this->reporter->reportSpan($span);
    }

}