<?php

namespace Shared\Libraries\Jaeger;

use OpenTracing\Span;
use OpenTracing\SpanOptions;

use OpenTracing\Reference;
use Shared\Libraries\Jaeger\JSpanContext;
use Shared\Libraries\Log;

use Shared\Libraries\Jaeger\Thrift;

final class JSpan implements Span
{
    private $tracer;
    private $context;
    private $operationName;
    private $startTime = 0;
    private $duration = 0;
    private $tags = null;
    private $references = [];

    public static function create($tracer, $operationName, $options)
    {
        error_log("@GREEN Created a new span");
        return new self($tracer, $operationName, $options);
    }

    private function __construct($tracer, $operationName = "untitled_span", SpanOptions $options = null)
    {
        $this->tracer = $tracer;
        $this->operationName = $operationName;

        if ($options != null)
        {
            $this->startTime = $options->getStartTime();
            $this->references = $options->getReferences();
            $this->tags = $options->getTags();
        }

        if (empty($this->startTime))
        {
            $this->startTime = microtime(true);
        }

        // if we have a parent, be its child
        $traceId = null;
        $parentId = null;
        foreach ($this->references as $ref)
        {
            if ($ref->isType(Reference::CHILD_OF))
            {
                if ($parentId != null)
                {
                    throw new \Exception("can't be a child of two things");
                }
                $traceId = $ref->getContext()->getTraceID();
                $parentId = $ref->getContext()->getSpanID();
            }
        }
        $this->context = JSpanContext::create($traceId, $parentId);
    }

    /**
     * {@inheritdoc}
     */
    public function getOperationName()
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
    public function getContext()
    {
        return $this->context;
    }

    /**
     * {@inheritdoc}
     */
    public function finish($finishTime = null, array $logRecords = [])
    {
        // mark the duration
        $this->duration = microtime(true) - $this->startTime;

        // report ourselves to the Tracer
        $this->tracer->reportSpan($this);
    }

    public function overwriteOperationName($newOperationName)
    {
        error_log("@GREEN Naming span: {$newOperationName}");
        $this->operation = $newOperationName;
    }

    /**
     * {@inheritdoc}
     */
    public function setTags(array $tags)
    {
        foreach ($tags as $key => $value)
        {
            if (!is_string($key))
            {
                throw new \Exception("tag key not a string");
            }

            if (!(is_string($value) || is_bool($value) || is_numeric($value)))
            {
                throw new \Exception("invalid tag value type");
            }

            $this->tags[$key] = $value;
        }
    }

    /**
     * {@inheritdoc}
     */
    public function log(array $fields = [], $timestamp = null)
    {
        throw new \Exception("not implemented");
    }

    /**
     * {@inheritdoc}
     */
    public function addBaggageItem($key, $value)
    {
        throw new \Exception("not implemented");
    }

    /**
     * {@inheritdoc}
     */
    public function getBaggageItem($key)
    {
        return null;
    }

    public function thriftify()
    {
        $traceId = $this->getContext()->getTraceID();
        return new Thrift\Span(array(
            "traceIdLow" => $traceId,
            "traceIdHigh" => 0,
            "spanId" => $this->getContext()->getSpanID(),
            "parentSpanId" => $this->getContext()->getParentID(),
            "operationName" => $this->operationName,
            "flags" => $this->getContext()->getFlags(),
            "startTime" => $this->startTime * 1000000, // microseconds
            "duration" => $this->duration * 1000, // ms
            "tags" => $this->buildTags(),
        ));
    }

    private function buildTags()
    {
        $tags = array();
        foreach ($this->tags as $key => $value)
        {
            if (is_bool($value))
            {
                $tags[] = new Thrift\Tag(array(
                    "key" => $key,
                    "vType" => Thrift\TagType::BOOL,
                    "vBool" => $value,
                ));
            }
            else if (is_numeric($value))
            {
                $tags[] = new Thrift\Tag(array(
                    "key" => $key,
                    "vType" => Thrift\TagType::DOUBLE,
                    "vDouble" => $value,
                ));   
            }
            else if (is_string($value))
            {
                $tags[] = new Thrift\Tag(array(
                    "key" => $key,
                    "vType" => Thrift\TagType::STRING,
                    "vStr" => $value,
                ));
            }
        }
        return $tags;
    }
}
