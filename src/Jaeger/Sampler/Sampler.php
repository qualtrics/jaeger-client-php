<?php

namespace Jaeger\Sampler;

interface Sampler
{
    public function isSampled($traceId, $operation);
}
