# Jaeger Bindings for PHP OpenTracing API

This is a client-side library that implements an OpenTracing Tracer, with Zipkin-compatible data model.

The library's package is `mdrt/jaeger-client-php`.

## How to Contribute

Reach out to `@tylerc` for now.

## Installation

We recommend using a dependency manager like Composer when including this library into an application. For example, add these lines to your `composer.json` file:

```json
{
	...
    "require": {
    	...
        "mdrt/jaeger": "dev-master"
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

Pending approval from legal, we intend to use the [Apache 2.0 License](https://github.com/jaegertracing/jaeger-client-go/blob/master/LICENSE).