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

            $serializer = new TBinarySerializer();
            $bytes = $serializer->serialize($batch);

            $transport = new TUDPTransport("10.4.4.144", "6831");

            $p = new TCompactProtocol($transport);
            $client = new AgentClient($p, $p);

            // emit a batch
            $client->emitBatch($batch);

    }

    public static function sortthem($a, $b)
    {
        return ($a->startTime < $b->startTime) ? -1 : 1;
    }
}