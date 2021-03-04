<?php

namespace JaegerTests\Unit\Transport;

use Jaeger\Reporter\RemoteReporter;
use Jaeger\Sampler\ConstSampler;
use Jaeger\Span;
use Jaeger\Thrift\Zipkin;
use Jaeger\Tracer;
use Jaeger\Transport\Transport;
use Jaeger\Transport\DummyTransport;
use OpenTracing\StartSpanOptions;
use PHPUnit_Framework_TestCase;

class RemoteReporterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @author tylerc
     */
    public function testRemoteReporter()
    {
        $transport = new DummyTransport();

        // sample tracer
        $tracer = Tracer::create("test", [
            Tracer::SAMPLER => new ConstSampler(true),
            Tracer::REPORTER => new RemoteReporter($transport),
        ]);

        // sample span
        $span = $tracer->startSpan("test-operation", StartSpanOptions::create([
            "tags" => [
                "some-key" => "some-value",
            ],
        ]));

        // finish the span
        $span->finish();

        // must have recorded the proper span
        $this->assertEquals($transport->spans, [$span]);

        // must have done a flush
        $this->assertEquals(count($transport->spans), 1);
    }
}
