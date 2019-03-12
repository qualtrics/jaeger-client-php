<?php

namespace Jaeger;

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

    public static function create($traceId = null, $parentId = null, $spanId = null)
    {
        return new self($traceId, $parentId, $spanId);
    }

    private function __construct($traceId, $parentId, $spanId)
    {
        if (is_integer($spanId)) {
            $this->spanId = $spanId;
        } else {
            // span id is some random number
            $this->spanId = (int) mt_rand() << 31 | mt_rand(); // max: 2^62 - 1
        }

        // set the trace id
        if (is_integer($traceId)) {
            $this->traceIdLow = $traceId;
            $this->traceIdHigh = null;
        } elseif (is_array($traceId) && is_integer($traceId['low']) && is_integer($traceId['high'])) {
            $this->traceIdLow = $traceId['low'];
            $this->traceIdHigh = $traceId['high'];
        } else {
            $this->traceIdLow = (int) (mt_rand() << 31 | mt_rand()); // max: 2^62 - 1
            $this->traceIdHigh = (int) (mt_rand() << 31 | mt_rand());
        }

        // a trace MAY have a parent, but might not
        if (is_integer($parentId)) {
            $this->parentId = $parentId;
        }

        // error_log("Generated trace: " . $this->traceIdHigh . "-" . $this->traceIdLow);
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

    public function encode()
    {
        if (is_numeric($this->traceIdHigh)) {
            return sprintf(
                "%x%016x:%x:%x:%x",
                $this->traceIdHigh,
                $this->traceIdLow,
                $this->spanId,
                $this->parentId,
                $this->flags
            );
        }
        return sprintf("%x:%x:%x:%x", $this->traceIdLow, $this->spanId, $this->parentId, $this->flags);
    }

    public function getTraceID()
    {
        if (is_numeric($this->traceIdHigh)) {
            return array(
                "low" => $this->traceIdLow,
                "high" => $this->traceIdHigh,
            );
        }

        return $this->traceIdLow;
    }

    public function getTraceIDHex()
    {
        if (is_numeric($this->traceIdHigh)) {
            return sprintf("%x%016x", $this->traceIdHigh, $this->traceIdLow);
        }
        return sprintf("%x", $this->traceIdLow);
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

    public function setSampled($sampled)
    {
        if ($sampled) {
            $this->flags |= self::IS_SAMPLED;
        } else {
            // this case not supported, since you shouldn't ever be "un-sampling" a span
        }
    }

    public function isSampled()
    {
        return ($this->flags & self::IS_SAMPLED) == self::IS_SAMPLED;
    }

    public function isDebug()
    {
        return ($this->flags & self::IS_DEBUG) == self::IS_DEBUG;
    }
}
