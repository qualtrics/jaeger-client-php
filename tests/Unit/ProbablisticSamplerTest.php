<?php

namespace JaegerTests\Unit;

use Jaeger\ProbabilisticSampler;
use PHPUnit_Framework_TestCase;

class ProbabilisticSamplerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @author tylerc
     */
    public function testSampling()
    {
        $cases = [
            [
                "rate" => 0,
                "trace_id" => 0,
                "sampled" => false,
            ],
            [
                "rate" => 0,
                "trace_id" => [
                    "low" => PHP_INT_MAX,
                    "high" => 0xfeedface
                ],
                "sampled" => false,
            ],
            [
                "rate" => 1,
                "trace_id" => 0,
                "sampled" => true,
            ],
            [
                "rate" => 1,
                "trace_id" => [
                    "low" => PHP_INT_MAX,
                    "high" => 0xfeedface
                ],
                "sampled" => true,
            ],
            [
                "rate" => 0.999,
                "trace_id" => [
                    "low" => PHP_INT_MAX,
                    "high" => 0xfeedface
                ],
                "sampled" => false,
            ],
            [
                "rate" => 0.5,
                "trace_id" => [
                    "low" => PHP_INT_MAX >> 1,
                    "high" => 0xfeedface
                ],
                "sampled" => true,
            ],
            [
                "rate" => 0.5,
                "trace_id" => [
                    "low" => PHP_INT_MAX >> 1 - 1,
                    "high" => 0xfeedface
                ],
                "sampled" => false,
            ],
        ];

        foreach ($cases as $case) {
            $sampler = new ProbabilisticSampler($case["rate"]);
            $this->assertEquals(
                $case["sampled"],
                $sampler->isSampled($case["trace_id"], "test_operation")
            );
        }
    }
}
