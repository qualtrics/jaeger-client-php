<?php

namespace Jaeger;

interface Sampler
{
    public function isSampled($traceId, $operation);
}
