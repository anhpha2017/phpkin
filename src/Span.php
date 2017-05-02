<?php
namespace whitemerry\phpkin;

/**
 * Class Span
 *
 * @author Piotr Bugaj <whitemerry@outlook.com>
 * @package whitemerry\phpkin
 */
class Span
{
    const EMPTY_IDENTIFIER = 'EMPTY_IDENTIFIER';
    const AUTO_IDENTIFIER = 'AUTO_IDENTIFIER';

    /**
     * @var string
     */
    protected $name;

    /**
     * @var string
     */
    protected $startTimestamp;

    /**
     * @var string
     */
    protected $stopTimestamp;

    /**
     * @var Endpoint
     */
    protected $endpoint;

    /**
     * @var Identifier
     */
    protected $spanId;

    /**
     * @var null|Identifier
     */
    protected $traceId;

    /**
     * @var null|Identifier
     */
    protected $parentId;

    /**
     * Span constructor.
     *
     * @param $name string Span name
     * @param $startTimestamp string Request start timestamp (from Zipkin::getTimestamp())
     * @param $stopTimestamp string Request end timestamp (from Zipkin::getTimestamp())
     * @param $endpoint Endpoint Endpoint object
     * @param $spanId Identifier Span identifier
     * @param $traceId string|Identifier Trace identifier (default from Zipkin::getTraceId())
     * @param $parentId string|Identifier Parent identifier (default from Zipkin::getTraceSpanId())
     */
    function __construct(
        $name,
        $startTimestamp,
        $stopTimestamp,
        $endpoint,
        $spanId,
        $traceId = Span::AUTO_IDENTIFIER,
        $parentId = Span::AUTO_IDENTIFIER
    )
    {
        $this->setName($name);
        $this->setTimestamp('startTimestamp', (string) $startTimestamp);
        $this->setTimestamp('stopTimestamp', (string) $stopTimestamp);
        $this->setEndpoint($endpoint);
        $this->setIdentifier('spanId', $spanId, false, false);
        $this->setIdentifier('traceId', $traceId, false, true, [Zipkin::class, 'getTraceId']);
        $this->setIdentifier('parentId', $parentId, true, true, [Zipkin::class, 'getTraceSpanId']);
    }

    /**
     * Converts Span to array
     *
     * @return array
     */
    public function toArray()
    {
        $span = [
            'id' => (string) $this->spanId,
            'traceId' => (string) $this->traceId,
            'name' => $this->name,
            'timestamp' => $this->startTimestamp,
            'duration' => ($this->stopTimestamp - $this->startTimestamp),
            'annotations' => [
                [
                    'endpoint' => $this->endpoint->toArray(),
                    'timestamp' => $this->startTimestamp,
                    'value' => 'cs'
                ],
                [
                    'endpoint' => $this->endpoint->toArray(),
                    'timestamp' => $this->stopTimestamp,
                    'value' => 'cr'
                ]
            ]
        ];

        if ($this->parentId !== null) {
            $span['parentId'] = (string) $this->parentId;
        }

        return $span;
    }

    /**
     * Valid and set name
     *
     * @param $name string
     *
     * @throws \InvalidArgumentException
     */
    protected function setName($name)
    {
        if (!is_string($name)) {
            throw new \InvalidArgumentException('The name must be a string');
        }

        $this->name = $name;
    }

    /**
     * Valid and set timestamp
     *
     * @param $field string
     * @param $timestamp int
     *
     * @throws \InvalidArgumentException
     */
    protected function setTimestamp($field, $timestamp)
    {
        if (!ctype_digit($timestamp) || strlen($timestamp) !== 16) {
            throw new \InvalidArgumentException($field . ' must be generated by Zipkin::getTimestamp()');
        }

        $this->{$field} = $timestamp;
    }

    /**
     * Valid and set endpoint
     *
     * @param $endpoint Endpoint
     *
     * @throws \InvalidArgumentException
     */
    protected function setEndpoint($endpoint)
    {
        if (!($endpoint instanceof Endpoint)) {
            throw new \InvalidArgumentException('$endpoint must be instance of Endpoint');
        }

        $this->endpoint = $endpoint;
    }

    /**
     * Valid and set identifier
     *
     * @param $field string
     * @param $identifier Identifier|null
     * @param $allowEmpty bool Allow identifier be null
     * @param $allowAuto bool Allow identifier be set automatic
     * @param $autoValue callable|null default value for auto identifier
     *
     * @throws \InvalidArgumentException
     *
     * @return null
     */
    protected function setIdentifier(
        $field,
        $identifier,
        $allowEmpty = false,
        $allowAuto = false,
        $autoValue = null
    )
    {
        if ($identifier === static::EMPTY_IDENTIFIER) {
            if ($allowEmpty) {
                $this->{$field} = null;
            } else {
                throw new \InvalidArgumentException('Identifier (' . $field . ') can not be empty');
            }
            return null;
        }

        if ($identifier === static::AUTO_IDENTIFIER) {
            if ($allowAuto) {
                $this->{$field} = ($autoValue === null) ? null : call_user_func($autoValue);
            } else {
                throw new \InvalidArgumentException('Identifier (' . $field . ') can not be auto set');
            }
            return null;
        }

        if (!($identifier instanceof Identifier)) {
            throw new \InvalidArgumentException('$identifier must be instance of Identifier');
        }

        $this->{$field} = $identifier;
        return null;
    }
}
