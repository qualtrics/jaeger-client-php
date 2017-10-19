<?php

namespace Jaeger;

class AlwaysSampler implements Sampler
{
    public function isSampled($traceId, $operation)
    {
        return true;
    }
}
