<?php

namespace JaegerTests\Unit\Sampler;

use Jaeger\Sampler\ConstSampler;
use PHPUnit_Framework_TestCase;

class ConstSamplerTest extends PHPUnit_Framework_TestCase
{
    /**
     * @author tylerc
     */
    public function testConstSampling()
    {
        $cases = [
            [
                "decision" => true,
                "trace_id" => 0,
                "sampled" => true,
            ],
            [
                "decision" => false,
                "trace_id" => 0,
                "sampled" => false,
            ],
        ];

        foreach ($cases as $case) {
            $sampler = new ConstSampler($case["decision"]);
            $this->assertEquals(
                $case["sampled"],
                $sampler->isSampled($case["trace_id"], "test_operation")
            );
        }
    }
}
