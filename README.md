# Jaeger Bindings for PHP OpenTracing API

[![Build Status](https://travis-ci.org/qualtrics/jaeger-client-php.svg?branch=master)](https://travis-ci.org/qualtrics/jaeger-client-php)
[![OpenTracing Badge](https://img.shields.io/badge/OpenTracing-enabled-blue.svg)](http://opentracing.io)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%205.6-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-Apache%202.0-blue.svg)](https://github.com/qualtrics/jaeger-client-php/blob/master/LICENSE)

This is a client-side library that implements an OpenTracing Tracer, with Zipkin-compatible data model. The library's package is `qualtrics/jaeger-client-php`.

**IMPORTANT**: Please note that while `jaeger-client-php` can record and report spans, it is **still under active development** and remains incomplete in a number of ways. It's modeled after [jaeger-client-go](https://github.com/jaegertracing/jaeger-client-go) in design, but many components are not yet implemented.

## Required Reading

In order to understand the library, one must first be familiar with the
[OpenTracing project](http://opentracing.io) and
[specification](http://opentracing.io/documentation/pages/spec.html) more specifically. Additionally, one should review the [PHP OpenTracing API](https://github.com/opentracing/opentracing-php/blob/master/README.md).

## How to Contribute

We're still working on this; reach out to `@tylerchr` for now.

## Installation

We recommend using a dependency manager like Composer when including this library into an application. For example, add these lines to your `composer.json` file:

```json
{
	...
    "require": {
    	...
        "qualtrics/jaeger": "dev-master"
    }
}
```

## Initialization

```php
use Jaeger/Tracer;
use OpenTracing\GlobalTracer;

GlobalTracer::set(Tracer::create("service-name", [
	Tracer::SAMPLER => new ConstSampler(true),
	Tracer::Reporter => new NullReporter(),
]));
```

You'll likely also want to make sure to flush the tracer just prior to application shutdown. The simplest way to do this is by registering a custom shutdown function:

```php
register_shutdown_function(function() {

	GlobalTracer::get()->flush();

})
```

## Instrumentation for Tracing

Since this tracer is fully compliant with the OpenTracing API 1.0, all code instrumentation should only use the API itself, as descriped in the [opentracing-php](https://github.com/opentracing/opentracing-php) documentation.

## Features

### Reporters

A "reporter" is a component that receives the finished spans and reports them somewhere. Three standard reporters are implemented at present:

- `NullReporter`: a no-op reporter that does nothing
- `JaegerReporter`: a reporter that forwards spans to a [jaeger-agent](https://github.com/jaegertracing/jaeger/tree/master/cmd/agent) using the `emitBatch` API.
- `ZipkinReporter`: a reporter that forwards spans to a [jaeger-agent](https://github.com/jaegertracing/jaeger/tree/master/cmd/agent) using the `emitZipkinBatch` API.

TODO: Introduce the "Transport" construct and refactor the latter two Reporters as Transports, and implement a RemoteReporter to match the jaeger-client-go API.

### Sampling

The tracer does not record all spans, but only those that have the sampling bit set in the flags. When a new trace is started and a new unique ID is generated, a sampling decision is made concerning whether this trace should be sampled. The sampling decision is propagated to all downstream calls via the `flags` field of the trace context. The following samplers are available:

- `ConstSampler` always makes the same sampling decision for all trace IDs. it can be configured to either sample all traces, or to sample none.
- `ProbabilisticSampler` uses a fixed sampling rate as a probability for a given trace to be sampled. The actual decision is made by comparing the trace ID with a random number multiplied by the sampling rate.

### Baggage Injection

Baggage is not currently supported.

## License

[Apache 2.0 License](https://github.com/qualtrics/jaeger-client-php/blob/master/LICENSE).