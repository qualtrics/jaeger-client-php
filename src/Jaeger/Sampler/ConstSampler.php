<?php

namespace Jaeger\Sampler;

class ConstSampler implements Sampler
{
    private $decision;

    public function __construct($decision)
    {
        $this->decision = $decision;
    }

    public function isSampled($traceId, $operation)
    {
        return $this->decision;
    }
}
