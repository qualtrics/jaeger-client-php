<?php

namespace Jaeger\Reporter;

use Jaeger\Span;

interface Reporter
{
    /**
    * Submits a new span to collectors, possibly delayed and/or with buffering.
    *
    * @param Span $span
    */
    public function reportSpan(Span $span);

    /**
    * Does a clean shutdown of the reporter, flushing any traces that may be
    * buffered in memory.
    */
    public function close();
}
