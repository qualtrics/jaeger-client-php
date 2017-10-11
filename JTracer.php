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
	private static $tracer;

    private $sampler;

    private $spans;

    public static function create($sampler)
    {
    	error_log("@GREEN Instantiated a new basic tracer");
        self::$tracer = new self();
        self::$tracer->sampler = $sampler;
        self::$tracer->spans = array();
        return self::get();
    }

    public static function get()
    {
    	error_log("@GREEN Getting the static basic tracer");
    	return self::$tracer;
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
    }

    public function extract($format, Reader $carrier)
    {
        return SpanContext::createAsDefault();
    }

    public function flush()
    {

    }

    // ---

    public function reportSpan($span)
    {
        $this->reportZipkinSpan($span);
    }

    public function reportZipkinSpan($span)
    {
        $thriftSpan = $span->thriftify();
        $this->spans[] = $thriftSpan;

        Log::silence();
        Log::debug("Reporting finished span", $thriftSpan);
        Log::silence(false);

        $endpoint = new Zipkin\Endpoint(array(
            "ipv4" => 167918715,
            "port" => 0,
            "service_name" => "monolith",
            "ipv6" => "",
        ));

        $annotations = array(
            new Zipkin\Annotation(array(
                "timestamp" => $span->timestamp,
                "value" => "sr",
                "host" => $endpoint,
            )),
            new Zipkin\Annotation(array(
                "timestamp" => $span->timestamp + $span->duration,
                "value" => "sr",
                "host" => $endpoint,
            )),
        );

        // add each log to the annotations
        foreach ($span->logs as $key => $value)
        {
            // TODO(tylerc)
        }

        // collect binary annotations
        $binary_annotations = array(
            new Zipkin\BinaryAnnotation(array(
                "key" => "is_test",
                "value" => true,
                "annotation_type" => Zipkin\AnnotationType::BOOL,
                "host" => $endpoint,
            )),
            new Zipkin\BinaryAnnotation(array(
                "key" => "php.version",
                "value" => phpversion(),
                "annotation_type" => Zipkin\AnnotationType::STRING,
                "host" => $endpoint,
            )),
            new Zipkin\BinaryAnnotation(array(
                "key" => "php.pid",
                "value" => getmypid(),
                "annotation_type" => Zipkin\AnnotationType::I64,
                "host" => $endpoint,
            )),
            new Zipkin\BinaryAnnotation(array(
                "key" => "hostname",
                "value" => hostname(),
                "annotation_type" => Zipkin\AnnotationType::STRING,
                "host" => $endpoint,
            )),
        );
        foreach ($span->tags as $key => $tag)
        {
            switch ($tag->vType)
            {
                case TagType::STRING:
                    $binary_annotations[] = new Zipkin\BinaryAnnotation(array(
                        "key" => $tag->key,
                        "value" => $tag->vStr,
                        "annotation_type" => Zipkin\AnnotationType::STRING,
                        "host" => $endpoint,
                    ));
                    break;

                case TagType::BOOL:
                    $binary_annotations[] = new Zipkin\BinaryAnnotation(array(
                        "key" => $tag->key,
                        "value" => $tag->vBool,
                        "annotation_type" => Zipkin\AnnotationType::BOOL,
                        "host" => $endpoint,
                    ));
                    break;

                case TagType::BINARY:
                    $binary_annotations[] = new Zipkin\BinaryAnnotation(array(
                        "key" => $tag->key,
                        "value" => $tag->vBinary,
                        "annotation_type" => Zipkin\AnnotationType::BYTES,
                        "host" => $endpoint,
                    ));
                    break;

                default:
                    break;
            }
        }

        $batch = array(
            new Zipkin\Span(array(
                "trace_id" => $span->traceIdLow,
                "name" => $span->operationName,
                "id" => $span->spanId,
                "parent_id" => $span->parentSpanId,
                "annotations" => array(),
                "binary_annotations" => array(),
                "debug" => false,
                "timestamp" => $span->startTime,
                "duration" => $span->duration,
                "trace_id_high" => $span->traceIdHigh,
            )),
        );

        $transport = new TUDPTransport("10.4.4.144", "5775");

        $p = new TCompactProtocol($transport);
        $client = new AgentClient($p, $p);

        // emit a batch
        $client->emitZipkinBatch($batch);
    }

    public function reportJaegerSpan($span)
    {
        $thriftSpan = $span->thriftify();
        $this->spans[] = $thriftSpan;


        Log::silence();
        Log::debug("Reporting finished span", $thriftSpan);
        Log::silence(false);


            $batch = new Batch(array(
                "process" => new Process(array(
                    "serviceName" => "monolith",
                    "tags" => array(
                        new Tag(array(
                            "key" => "is_test",
                            "vType" => TagType::BOOL,
                            "vBool" => true,
                        )),
                        new Tag(array(
                            "key" => "php.version",
                            "vType" => TagType::STRING,
                            "vStr" => phpversion(),
                        )),
                        new Tag(array(
                            "key" => "hostname",
                            "vType" => TagType::STRING,
                            "vStr" => gethostname(),
                        )),
                        new Tag(array(
                            "key" => "php.pid",
                            "vType" => TagType::LONG,
                            "vLong" => getmypid(),
                        )),
                    ),
                )),
                "spans" => array(
                    $thriftSpan,
                ),
            ));

            // $serializer = new TBinarySerializer();
            // $bytes = $serializer->serialize($batch);

            $transport = new TUDPTransport("10.4.4.144", "6831");

            $p = new TCompactProtocol($transport);
            $client = new AgentClient($p, $p);

            // emit a batch
            $client->emitBatch($batch);

    }
}