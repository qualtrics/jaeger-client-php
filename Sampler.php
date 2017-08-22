<?php

namespace Shared\Libraries\Jaeger;

interface Sampler
{
    public function IsSampled($traceId, $operation);
}
