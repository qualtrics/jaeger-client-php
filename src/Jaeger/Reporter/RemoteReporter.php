<?php

namespace Jaeger\Reporter;

use Jaeger\Span;
use Jaeger\Transport\Transport;

class RemoteReporter implements Reporter
{
    private $sender;

    public function __construct(Transport $transport)
    {
        $this->sender = $transport;
    }

    /**
    * Submits a new span to collectors, possibly delayed and/or with buffering.
    *
    * @param Span $span
    */
    public function reportSpan(Span $span)
    {
        $this->sender->append($span);
    }

    /**
    * Does a clean shutdown of the reporter, flushing any traces that may be
    * buffered in memory.
    */
    public function close()
    {
        return $this->sender->close();
    }
}
