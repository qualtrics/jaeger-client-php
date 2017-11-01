<?php

namespace Jaeger;

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

        $this->samplingRate = (double) $samplingRate;
        $this->samplingBoundary = $this->samplingRate * PHP_INT_MAX;
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
