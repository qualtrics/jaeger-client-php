<?php

namespace JaegerTests\Unit;

use Jaeger\ZipkinReporter;
use Jaeger\Thrift\Zipkin;
use PHPUnit_Framework_TestCase;

class ZipkinReporterTest extends PHPUnit_Framework_TestCase
{
    /**
     * @author tylerc
     */
    public function testBinaryEncode()
    {
        $cases = [
            [
                "type" => Zipkin\AnnotationType::BOOL,
                "input" => false,
                "output" => "\x00",
            ],
            [
                "type" => Zipkin\AnnotationType::BOOL,
                "input" => true,
                "output" => "\x01",
            ],
            [
                "type" => Zipkin\AnnotationType::I16,
                "input" => 23209, // largest Mersenne Prime that fits in an int16
                "output" => "\x5a\xa9",
            ],
            [
                "type" => Zipkin\AnnotationType::I16,
                "input" => 44497, // largest Mersenne Prime that fits in a uint16
                "output" => "\xad\xd1",
            ],
            [
                "type" => Zipkin\AnnotationType::I32,
                "input" => 44497, // largest Mersenne Prime that fits in a uint16
                "output" => "\x00\x00\xad\xd1",
            ],
            [
                "type" => Zipkin\AnnotationType::I32,
                "input" => 74207281, // largest known Mersenne Prime
                "output" => "\x04\x6c\x50\x31",
            ],
            [
                "type" => Zipkin\AnnotationType::I64,
                "input" => 9814072356, // the largest perfect power that contains no repeated digits in base ten
                "output" => "\x00\x00\x00\x02\x48\xf6\xdc\x24",
            ],
            [
                "type" => Zipkin\AnnotationType::DOUBLE,
                "input" => 3.14159265,
                "output" => "\x40\x09\x21\xfb\x53\xc8\xd4\xf1",
            ],
        ];

        $reporter = new ZipkinReporter();

        foreach ($cases as $case) {
            // It's not strictly necessary to base64_encode each of these, but it sure
            // makes the test output more legible when a case fails!
            $this->assertEquals(
                base64_encode($case["output"]),
                base64_encode($reporter->binaryEncode($case["type"], $case["input"]))
            );
        }
    }

    protected function getClassName()
    {
        return 'Shared\Libraries\Jaeger\JSpan';
    }
}
