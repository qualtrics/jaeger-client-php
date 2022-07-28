<?php

namespace JaegerTests\Unit\Sampler;

use Jaeger\Sampler\ProbabilisticSampler;
use PHPUnit\Framework\TestCase;

class ProbabilisticSamplerTest extends TestCase
{
    /**
     * @author tylerc
     */
    public function testProbabilisticSampling()
    {
        $sampleMax = (int) (mt_getrandmax() << 31 | mt_getrandmax());

        $cases = [
            [
                "rate" => 0,
                "trace_id" => 0,
                "sampled" => false,
            ],
            [
                "rate" => 0,
                "trace_id" => [
                    "low" => $sampleMax,
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
                    "low" => $sampleMax,
                    "high" => 0xfeedface
                ],
                "sampled" => true,
            ],
            [
                "rate" => 0.999,
                "trace_id" => [
                    "low" => $sampleMax,
                    "high" => 0xfeedface
                ],
                "sampled" => false,
            ],
            [
                "rate" => 0.5,
                "trace_id" => [
                    "low" => $sampleMax >> 1,
                    "high" => 0xfeedface
                ],
                "sampled" => true,
            ],
            [
                "rate" => 0.5,
                "trace_id" => [
                    "low" => $sampleMax >> 1 - 1,
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
