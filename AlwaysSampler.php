<?php

namespace Shared\Libraries\Jaeger;

class AlwaysSampler implements Sampler
{
    public function IsSampled($traceId, $operation)
    {
        return true;
    }
}
