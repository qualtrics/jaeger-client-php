<?php

namespace Shared\Libraries\Jaeger;

use Shared\Libraries\Jaeger\Sampler;

class AlwaysSampler implements Sampler
{
    public function IsSampled($traceId, $operation)
    {
        return true;
    }
}
