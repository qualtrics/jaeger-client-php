<?php

namespace Jaeger;

use Jaeger\Span;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Reporter\NullReporter;
use OpenTracing\Scope;
use OpenTracing\ScopeManager;
use OpenTracing\NoopScope;
use OpenTracing\NoopScopeManager;
use OpenTracing\Span as OTSpan;
use OpenTracing\SpanContext;
use OpenTracing\Tracer as OTTracer;
use OpenTracing\Formats;

final class Tracer implements OTTracer
{
    const SAMPLER = "Sampler";
    const REPORTER = "Reporter";
    const TAGS = "Tags";

    private $serviceName = null;

    private $sampler = null;
    private $reporter = null;

    // Tracer-level tags
    private $tags = [];

    public static function create($serviceName, $options)
    {
        $tracer = new self();

        // configure with defaults
        $tracer->serviceName = $serviceName;
        $tracer->sampler = new ConstSampler(false);
        $tracer->reporter = new NullReporter();

        // add standard tracer-level tags
        $tracer->tags["hostname"] = gethostname();
        $tracer->tags["php.version"] = PHP_VERSION;
        $tracer->tags["php.pid"] = getmypid();
        $tracer->tags["jaeger.version"] = "jaeger-php-qualtrics";

        // add the server's IP if we're a web server
        if (php_sapi_name() == "apache2handler") {
            $tracer->tags["ip"] = $_SERVER['SERVER_ADDR'] . ":" . $_SERVER['SERVER_PORT'];
        }

        foreach ($options as $key => $value) {
            switch ($key) {
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

    public function getScopeManager(): ScopeManager
    {
        return new NoopScopeManager();
    }

    public function getActiveSpan(): ?OTSpan
    {
        return null;
    }

    public function startActiveSpan(string $operationName, $options = []): Scope
    {
        return new NoopScope();
    }

    public function startSpan(string $operationName, $options = []): OTSpan
    {
        $span = Span::create($this, $operationName, $options);

        // configure a context for the new span
        $ctx = $span->getContext();
        $ctx->setSampled($this->sampler->IsSampled($ctx->getTraceID(), $span->getOperationName()));
        $span->setContext($ctx);

        return $span;
    }

    public function inject(SpanContext $spanContext, string $format, &$carrier): void
    {
        switch ($format) {
            case Formats\HTTP_HEADERS:
                $carrier["Uber-Trace-ID"] = $spanContext->encode();
                break;

            default:
                // TODO(tylerc): Implement this.
        }
    }

    public function extract(string $format, $carrier): ?SpanContext
    {
        // TODO(tylerc): Implement this.
        return SpanContext::createAsDefault();
    }

    public function flush(): void
    {
        $this->reporter->close();
    }

    // ---

    public function reportSpan($span)
    {
        if ($span->getContext()->isSampled()) {
            $this->reporter->reportSpan($span);
        }
    }

    public function getServiceName()
    {
        return $this->serviceName;
    }

    public function getTags()
    {
        return $this->tags;
    }
}
