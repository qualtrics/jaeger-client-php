<?php

namespace Shared\Libraries\Jaeger;

use OpenTracing\SpanContext;

final class JSpanContext implements SpanContext
{
    const IS_SAMPLED = 1;
    const IS_DEBUG = 2;

    private $traceIdLow;
    // private $traceIdHigh;
    private $spanId;
    private $parentId;

    private $flags = 0;

    public static function create($traceId = 0, $parentId = 0)
    {
        return new self($traceId, $parentId);
    }

    private function __construct($traceId, $parentId)
    {
        $this->traceIdLow = rand();
        // $this->traceIdHigh = rand();
        $this->spanId = rand();

        if ($traceId > 0)
        {
            $this->traceIdLow = $traceId;
        }

        if ($parentId > 0)
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
        // return array(
        //     $this->traceIdHigh,
        //     $this->traceIdLow
        // );
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
