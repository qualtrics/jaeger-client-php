<?php

namespace Jaeger\Sampler;

use Exception;

class ProbabilisticSampler implements Sampler
{
    private $samplingRate;
    private $samplingBoundary;

    public function __construct($samplingRate)
    {
        if (!is_numeric($samplingRate) || $samplingRate < 0 || $samplingRate > 1) {
            throw new Exception("invalid sampling rate");
        }

        // PHP has a hard time getting a true 64-bit random value, so we'll settle
        // for the maximum value that might be produced by our identifier generator.
        //
        // In practice this turns out to be 2^63 - 1 on a 64-bit system.
        $sampleMax = (int) (mt_getrandmax() << 31 | mt_getrandmax());

        $this->samplingRate = (double) $samplingRate;
        $this->samplingBoundary = $this->samplingRate * $sampleMax;
    }

    public function isSampled($traceId, $operation)
    {
        switch ($this->samplingRate) {
            case 0:
                return false;
            case 1:
                return true;
            default:
                $lowBits = is_array($traceId) ? $traceId['low'] : $traceId;
                return $this->samplingBoundary >= $lowBits;
        }
    }
}
