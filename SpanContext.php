<?php

namespace Shared\Libraries\Jaeger;

use OpenTracing\SpanContext as OTSpanContext;

final class SpanContext implements OTSpanContext
{
    const IS_SAMPLED = 1;
    const IS_DEBUG = 2;

    private $traceIdLow;
    private $traceIdHigh = null;
    private $spanId;
    private $parentId = null;

    private $flags = 0;

    public static function create($traceId = null, $parentId = null)
    {
        return new self($traceId, $parentId);
    }

    private function __construct($traceId, $parentId)
    {
        // span id is always some random number
        $this->spanId = rand();

        // set the trace id
        if (is_integer($traceId))
        {
            $this->traceIdLow = $traceId;
            $this->traceIdHigh = null;
        }
        else if (is_array($traceId) && is_integer($traceId['low']) && is_integer($traceId['high']))
        {
            $this->traceIdLow = $traceId['low'];
            $this->traceIdHigh = $traceId['high'];
        }
        else
        {
            $this->traceIdLow = rand();
            $this->traceIdHigh = rand();
        }

        // a trace MAY have a parent, but might not
        if (is_integer($parentId))
        {
            $this->parentId = $parentId;
        }
    }

    public function getIterator()
    {
        return new EmptyIterator();
    }

    public function getBaggageItem($key)
    {
        return null;
    }

    public function withBaggageItem($key, $value)
    {
        return new self();
    }

    // ---

    public function getTraceID()
    {
        if (is_numeric($this->traceIdHigh))
        {
            return array(
                "low" => $this->traceIdLow,
                "high" => $this->traceIdHigh,
            );
        }

        return $this->traceIdLow;
    }

    public function getFlags()
    {
        return $this->flags;
    }

    public function getSpanID()
    {
        return $this->spanId;
    }

    public function getParentID()
    {
        return $this->parentId;
    }

    public function SetSampled($sampled)
    {
        if ($sampled)
        {
            $this->flags |= self::IS_SAMPLED;
        }
        else
        {
            // this case not supported, since you shouldn't ever be "un-sampling" a span
        }
    }

    public function IsSampled()
    {
        return ($this->flags & self::IS_SAMPLED) == self::IS_SAMPLED;
    }

    public function IsDebug()
    {
        return ($this->flags & self::IS_DEBUG) == self::IS_DEBUG;
    }
}
