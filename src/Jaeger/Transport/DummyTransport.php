<?php

namespace Jaeger\Transport;

use Jaeger\Span;
use Jaeger\Transport\Transport;

class DummyTransport implements Transport
{
    public $spans = [];

    public $flushes = [];

    public function append(Span $span)
    {
        $this->spans[] = $span;
    }

    public function flush()
    {
        $spans = count($this->spans);
        $this->flushes[] = $spans;
        return $spans;
    }

    public function close()
    {
        return;
    }
}
