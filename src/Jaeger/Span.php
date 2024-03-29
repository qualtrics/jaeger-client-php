<?php

namespace Jaeger;

use Jaeger\SpanContext;
use Jaeger\Thrift;
use Jaeger\Thrift\Zipkin;
use OpenTracing\Reference;
use OpenTracing\Span as OTSpan;
use OpenTracing\SpanContext as OTSpanContext;
use OpenTracing\StartSpanOptions;

final class Span implements OTSpan
{
    private $tracer;
    private $context;
    private $operationName;
    private $startTime = 0;
    private $duration = 0;
    private $tags = [];
    private $logs = [];
    private $references = [];

    public static function create($tracer, $operationName, StartSpanOptions $options)
    {
        return new self($tracer, $operationName, $options);
    }

    private function __construct($tracer, $operationName, StartSpanOptions $options = null)
    {
        $this->tracer = $tracer;
        $this->operationName = $operationName;

        if ($options != null) {
            $this->startTime = $options->getStartTime();
            $this->references = $options->getReferences();
            $this->tags = $options->getTags();
        }

        if (empty($this->startTime)) {
            $this->startTime = microtime(true);
        }

        // if we have a parent, be its child
        $traceId = null;
        $parentId = null;
        foreach ($this->references as $ref) {
            if ($ref->isType(Reference::CHILD_OF)) {
                if ($parentId != null) {
                    throw new \Exception("can't be a child of two things");
                }
                $traceId = $ref->getSpanContext()->getTraceID();
                $parentId = $ref->getSpanContext()->getSpanID();
            }
        }
        $this->context = SpanContext::create($traceId, $parentId);
    }

    /**
     * {@inheritdoc}
     */
    public function getOperationName(): string
    {
        return $this->operationName;
    }

    public function setContext($ctx)
    {
        $this->context = $ctx;
    }

    /**
     * {@inheritdoc}
     */
    public function getContext(): OTSpanContext
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function finish($finishTime = null): void
    {
        // mark the duration
        $this->duration = microtime(true) - $this->startTime;
        if ($this->duration < 0) {
            // Negative duration is not possible, and causes overflow making the span look like it lasted 584,512 years.
            $this->duration = 0;
        }

        // report ourselves to the Tracer
        $this->tracer->reportSpan($this);
    }

    public function overwriteOperationName(string $newOperationName): void
    {
        $this->operation = $newOperationName;
    }

    /**
     * {@inheritdoc}
     */
    public function setTag(string $key, $value): void
    {
        if (!(is_string($value) || is_bool($value) || is_numeric($value) || is_null($value))) {
            error_log("Type of " . $key . " is " . gettype($value));
            throw new \Exception("invalid tag value type");
        }

        $this->tags[$key] = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function log(array $fields = [], $timestamp = null): void
    {
        if ($timestamp == null) {
            $timestamp = microtime(true) * 1000000;
        }

        foreach ($fields as $key => $value) {
            if (!is_string($key)) {
                throw new \Exception("log field not a string");
            }

            if (!(is_string($value) || is_bool($value) || is_numeric($value))) {
                throw new \Exception("invalid log value type");
            }
        }

        $this->logs[] = array(
            "timestamp" => $timestamp,
            "fields" => $fields,
        );
    }

    /**
     * {@inheritdoc}
     */
    public function addBaggageItem(string $key, string $value): void
    {
        throw new \Exception("not implemented");
    }

    /**
     * {@inheritdoc}
     */
    public function getBaggageItem(string $key): ?string
    {
        return null;
    }

    public function getTracer()
    {
        return $this->tracer;
    }

    public function getStartTime()
    {
        return $this->startTime;
    }

    public function getDuration()
    {
        return $this->duration;
    }

    public function getLogs()
    {
        return $this->logs;
    }

    public function getTags()
    {
        return $this->tags;
    }

    public function getReferences()
    {
        return $this->references;
    }
}
