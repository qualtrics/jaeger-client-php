<?php

namespace Shared\Libraries\Jaeger;

use OpenTracing\Reference;
use OpenTracing\Span;
use OpenTracing\SpanOptions;
use Shared\Libraries\Jaeger\JSpanContext;
use Shared\Libraries\Jaeger\Thrift;
use Shared\Libraries\Jaeger\Thrift\Zipkin;
use Shared\Libraries\Log;

final class JSpan implements Span
{
    private $tracer;
    private $context;
    private $operationName;
    private $startTime = 0;
    private $duration = 0;
    private $tags = null;
    private $logs = null;
    private $references = [];

    public static function create($tracer, $operationName, $options)
    {
        return new self($tracer, $operationName, $options);
    }

    private function __construct($tracer, $operationName, SpanOptions $options = null)
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
        if ($timestamp == null)
        {
            $timestamp = microtime(true) * 1000000;
        }

        foreach ($fields as $key => $value)
        {
            if (!is_string($key))
            {
                throw new \Exception("log field not a string");
            }

            if (!(is_string($value) || is_bool($value) || is_numeric($value)))
            {
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

    private function binaryEncode($type, $value)
    {
        switch ($type)
        {
            case Zipkin\AnnotationType::BOOL:
                return ($value === true) ? chr(0x01) : chr(0x00);

            case Zipkin\AnnotationType::I16:
                return pack('n', $value);

            case Zipkin\AnnotationType::I32:
                return pack('N', $value);

            case Zipkin\AnnotationType::I64:
                return pack('J', $value);

            case Zipkin\AnnotationType::DOUBLE:

                if (version_compare(PHP_VERSION, "7.0.15") >= 0)
                {
                    return pack('E', $value);
                }
                else
                {
                    // encode in native endianness
                    $enc = pack('d', $value);

                    // determine our own endianness
                    $littleEndian = (base64_encode(pack('d', 3.1415)) == "bxKDwMohCUA=");
                    if ($littleEndian)
                    {
                        // reverse the bits
                        $enc = strrev($enc);
                    }

                    return $enc;

                }
        }
    }

    public function zipkinify($endpoint)
    {

        $startTime = (int) ($this->startTime * 1000000); // microseconds
        $duration = (int) ($this->duration * 1000000); // microseconds

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
        if (is_array($this->logs))
        {
            foreach ($this->logs as $key => $value)
            {
                // TODO(tylerc)
                $annotations[] = new Zipkin\Annotation(array(
                    "timestamp" => $value["timestamp"],
                    "value" => json_encode($value["fields"]),
                    "host" => $endpoint,
                ));
            }
        }

        // collect binary annotations
        $binary_annotations = array(
            // new Zipkin\BinaryAnnotation(array(
            //     "key" => "is_test",
            //     "value" => true,
            //     "annotation_type" => Zipkin\AnnotationType::BOOL,
            //     "host" => $endpoint,
            // )),
            // new Zipkin\BinaryAnnotation(array(
            //     "key" => "random_bytes",
            //     "value" => "some bytes go here",
            //     "annotation_type" => Zipkin\AnnotationType::BYTES,
            //     "host" => $endpoint,
            // )),
            // new Zipkin\BinaryAnnotation(array(
            //     "key" => "some_int16",
            //     "value" => $this->binaryEncode(Zipkin\AnnotationType::I16, 42),
            //     "annotation_type" => Zipkin\AnnotationType::I16,
            //     "host" => $endpoint,
            // )),
            // new Zipkin\BinaryAnnotation(array(
            //     "key" => "some_int32",
            //     "value" => $this->binaryEncode(Zipkin\AnnotationType::I32, 42),
            //     "annotation_type" => Zipkin\AnnotationType::I32,
            //     "host" => $endpoint,
            // )),
            // new Zipkin\BinaryAnnotation(array(
            //     "key" => "some_int64",
            //     "value" => $this->binaryEncode(Zipkin\AnnotationType::I64, 42),
            //     "annotation_type" => Zipkin\AnnotationType::I64,
            //     "host" => $endpoint,
            // )),
            // new Zipkin\BinaryAnnotation(array(
            //     "key" => "a_double",
            //     "value" => $this->binaryEncode(Zipkin\AnnotationType::DOUBLE, 3.14159265),
            //     "annotation_type" => Zipkin\AnnotationType::DOUBLE,
            //     "host" => $endpoint,
            // )),
            new Zipkin\BinaryAnnotation(array(
                "key" => "php.version",
                "value" => phpversion(),
                "annotation_type" => Zipkin\AnnotationType::STRING,
                "host" => $endpoint,
            )),
            new Zipkin\BinaryAnnotation(array(
                "key" => "php.pid",
                "value" => $this->binaryEncode(Zipkin\AnnotationType::I16, getmypid()),
                "annotation_type" => Zipkin\AnnotationType::I16,
                "host" => $endpoint,
            )),
            new Zipkin\BinaryAnnotation(array(
                "key" => "jaeger.hostname",
                "value" => gethostname(),
                "annotation_type" => Zipkin\AnnotationType::STRING,
                "host" => $endpoint,
            )),
            new Zipkin\BinaryAnnotation(array(
                "key" => "jaeger.version",
                "value" => "jaeger-php-qualtrics",
                "annotation_type" => Zipkin\AnnotationType::STRING,
                "host" => $endpoint,
            )),
        );


        foreach ($this->tags as $key => $value)
        {
            if (is_bool($value))
            {
                $binary_annotations[] = new Zipkin\BinaryAnnotation(array(
                    "key" => $key,
                    "value" => $this->binaryEncode(Zipkin\AnnotationType::BOOL, $value),
                    "annotation_type" => Zipkin\AnnotationType::BOOL,
                    "host" => $endpoint,
                ));
            }
            else if (is_integer($value))
            {
                $binary_annotations[] = new Zipkin\BinaryAnnotation(array(
                    "key" => $key,
                    "value" => $this->binaryEncode(Zipkin\AnnotationType::I64, $value),
                    "annotation_type" => Zipkin\AnnotationType::I64,
                    "host" => $endpoint,
                ));  
            }
            else if (is_numeric($value))
            {
                $binary_annotations[] = new Zipkin\BinaryAnnotation(array(
                    "key" => $key,
                    "value" => $this->binaryEncode(Zipkin\AnnotationType::DOUBLE, $value),
                    "annotation_type" => Zipkin\AnnotationType::DOUBLE,
                    "host" => $endpoint,
                ));
            }
            else if (is_string($value))
            {
                $binary_annotations[] = new Zipkin\BinaryAnnotation(array(
                    "key" => $key,
                    "value" => $value,
                    "annotation_type" => Zipkin\AnnotationType::STRING,
                    "host" => $endpoint,
                ));
            }
        }

        $traceId = $this->getContext()->getTraceID();

        return new Zipkin\Span(array(
            "trace_id" => (is_array($traceId) ? $traceId["low"] : $traceId),
            "name" => $this->operationName,
            "id" => $this->getContext()->getSpanID(),
            "parent_id" => $this->getContext()->getParentID(),
            "annotations" => $annotations,
            "binary_annotations" => $binary_annotations,
            "debug" => false,
            "timestamp" => $startTime,
            "duration" => $duration,
            "trace_id_high" => (is_array($traceId) ? $traceId["high"] : 0),
        ));

    }
}
