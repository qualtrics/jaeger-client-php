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
    const TAGS = "Tags";

    private $sampler = null;
    private $reporter = null;

    // Tracer-level tags
    private $tags = [];

    public static function create($options)
    {
        $tracer = new self();

        // configure with defaults
        $tracer->sampler = new AlwaysSampler();
        $tracer->reporter = new NullReporter();

        // add standard tracer-level tags
        $tracer->tags["php.version"] = PHP_VERSION;
        $tracer->tags["php.pid"] = getmypid();
        $tracer->tags["jaeger.version"] = "jaeger-php-qualtrics";
        $tracer->tags["jaeger.hostname"] = gethostname();

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

                case Tracer::TAGS:
                    $tracer->tags = array_merge($tracer->tags, $value);
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
        // append the tracer-level tags UNDERNEATH the existing ones
        $spanTags = $span->getTags();
        $span->setTags($this->tags);
        $span->setTags($spanTags); // re-write the span tags to override any tracer-level tags

        $this->reporter->reportSpan($span);
    }

}