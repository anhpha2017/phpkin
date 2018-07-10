<?php
/**
 * Created by PhpStorm.
 * User: anhpha
 * Date: 10/07/2018
 * Time: 16:15
 */

namespace whitemerry\phpkin;

use Symfony\Component\HttpFoundation\Request;
use whitemerry\phpkin\Identifier\SpanIdentifier;
use whitemerry\phpkin\Identifier\TraceIdentifier;
use whitemerry\phpkin\Logger\Logger;
use whitemerry\phpkin\Logger\SimpleHttpAsyncLogger;


class SimpleSymfonyTracerHandler
{
    // A service is not only a server but also a client of other service
    private $clientTracer;
    private $serverTracer;
    private $spanId;
    private $requestStart;

    /**
     * ZipkinTracer constructor.
     * @param string $zipkinUrl url to zinkin server for storing tracing data
     * @param Request $request
     * @param string $appName
     */
    function __construct(string $zipkinUrl, Request $request, string $appName)
    {
        /**
         * Initialize tracer, and setup info about you application
         */
        $endpoint = new Endpoint($appName, '127.0.0.1', '80');
        /**
         * Create logger to Zipkin, host is Zipkin's ip
         * Read more about loggers https://github.com/whitemerry/phpkin#why-do-i-prefer-filelogger
         *
         * Make sure host is available with http:// and port because SimpleHttpLogger does not throw error on failure
         * For debug purposes you can disable muteErrors
         */
        $logger = new SimpleHttpAsyncLogger(['host' => $zipkinUrl, 'muteErrors' => false]);
        // Init server tracer
        $this->serverTracer = $this->initServerTracer($request, $logger, $appName, $endpoint);
        // Init client tracer
        $this->clientTracer = new Tracer($appName . ' ' . $request->getRequestUri(), $endpoint, $logger);
        // Init span id
        $this->spanId = new SpanIdentifier();
    }

    /**
     * @return array headers for B3 propagation
     */
    public function getZipkinB3Headers(): array {
        return array(
            'X-B3-TraceId' => TracerInfo::getTraceId(),
            'X-B3-SpanId'   => (string) $this->spanId,
            'X-B3-ParentSpanId' =>  TracerInfo::getTraceSpanId(),
            'X-B3-Sampled'  => (int) TracerInfo::isSampled()
        );
    }
    // Set start time for a new request
    public function startExternalCall() {
        $this->requestStart = zipkin_timestamp();
    }

    /**
     * This method must be coupled with startExternalCall. startExternalCall is called before a RPC call
     * This method will be after a RPC call
     * @param string $serviceName
     * @param string $serviceHost
     * @param string $servicePort
     * @param string $serviceRequestUrl
     * @param string $method
     */
    public function prepareRPCSpan(string $serviceName, string $serviceHost, string $servicePort,
        string $serviceRequestUrl, string $method) {
        // Setup zipkin data for this request
        $endpoint = new Endpoint($serviceName, $serviceHost, $servicePort);
        $annotationBlock = new AnnotationBlock($endpoint, $this->requestStart);
        $span = new Span($this->spanId, $method . ' '. $serviceRequestUrl, $annotationBlock);
        // Add span to Zipkin
        $this->clientTracer->addSpan($span);
    }

    public function clientTrace() {
        if ($this->clientTracer instanceof Tracer) {
            $this->clientTracer->trace();
        }
    }

    public function serverTrace() {
        if ($this->serverTracer instanceof Tracer) {
            $this->serverTracer->trace();
        }
    }

    public function flushAllTracer() {
        $this->clientTrace();
        $this->serverTrace();
    }

    private function initServerTracer(
        Request $request, Logger $logger,
        string $tracerName, Endpoint $thisEndpoint): Tracer {
        /**
         * Read headers
         */
        $traceId = null;
        if ($request->headers->has('HTTP_X_B3_TRACEID')) {
            $traceId = new TraceIdentifier($request->headers->get('HTTP_X_B3_TRACEID'));
        }
        $traceSpanId = null;
        if ($request->headers->has('HTTP_X_B3_SPANID')) {
            $traceSpanId = new SpanIdentifier($request->headers->get('HTTP_X_B3_SPANID'));
        }
        $isSampled = null;
        if ($request->headers->has('HTTP_X_B3_SAMPLED')) {
            $isSampled = (bool) $request->headers->get('HTTP_X_B3_SAMPLED');
        }
        // Init server tracer
        $serverTracer = new Tracer($tracerName . ' '. $request->getRequestUri(),
            $thisEndpoint, $logger, $isSampled, $traceId, $traceSpanId);
        $serverTracer->setProfile(Tracer::BACKEND);
        return $serverTracer;
    }
}