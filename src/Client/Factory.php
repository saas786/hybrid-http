<?php

namespace Hybrid\Http\Client;

use Closure;
use GuzzleHttp\Promise\Create;
use GuzzleHttp\Promise\PromiseInterface;
use GuzzleHttp\Psr7\Response as Psr7Response;
use GuzzleHttp\TransferStats;
use Hybrid\Contracts\Events\Dispatcher;
use Hybrid\Tools\Str;
use Hybrid\Tools\Traits\Macroable;
use PHPUnit\Framework\Assert as PHPUnit;

use function GuzzleHttp\Promise\promise_for;

/**
 * @see \Hybrid\Http\Client\PendingRequest
 *
 * @method \Hybrid\Http\Client\PendingRequest accept(string $contentType)
 * @method \Hybrid\Http\Client\PendingRequest acceptJson()
 * @method \Hybrid\Http\Client\PendingRequest asForm()
 * @method \Hybrid\Http\Client\PendingRequest asJson()
 * @method \Hybrid\Http\Client\PendingRequest asMultipart()
 * @method \Hybrid\Http\Client\PendingRequest async()
 * @method \Hybrid\Http\Client\PendingRequest attach(string|array $name, string|resource $contents = '', string|null $filename = null, array $headers = [])
 * @method \Hybrid\Http\Client\PendingRequest baseUrl(string $url)
 * @method \Hybrid\Http\Client\PendingRequest beforeSending(callable $callback)
 * @method \Hybrid\Http\Client\PendingRequest bodyFormat(string $format)
 * @method \Hybrid\Http\Client\PendingRequest connectTimeout(int $seconds)
 * @method \Hybrid\Http\Client\PendingRequest contentType(string $contentType)
 * @method \Hybrid\Http\Client\PendingRequest dd()
 * @method \Hybrid\Http\Client\PendingRequest dump()
 * @method \Hybrid\Http\Client\PendingRequest maxRedirects(int $max)
 * @method \Hybrid\Http\Client\PendingRequest retry(int $times, int $sleepMilliseconds = 0, ?callable $when = null, bool $throw = true)
 * @method \Hybrid\Http\Client\PendingRequest sink(string|resource $to)
 * @method \Hybrid\Http\Client\PendingRequest stub(callable $callback)
 * @method \Hybrid\Http\Client\PendingRequest timeout(int $seconds)
 * @method \Hybrid\Http\Client\PendingRequest withBasicAuth(string $username, string $password)
 * @method \Hybrid\Http\Client\PendingRequest withBody(resource|string $content, string $contentType)
 * @method \Hybrid\Http\Client\PendingRequest withCookies(array $cookies, string $domain)
 * @method \Hybrid\Http\Client\PendingRequest withDigestAuth(string $username, string $password)
 * @method \Hybrid\Http\Client\PendingRequest withHeaders(array $headers)
 * @method \Hybrid\Http\Client\PendingRequest withMiddleware(callable $middleware)
 * @method \Hybrid\Http\Client\PendingRequest withOptions(array $options)
 * @method \Hybrid\Http\Client\PendingRequest withToken(string $token, string $type = 'Bearer')
 * @method \Hybrid\Http\Client\PendingRequest withUserAgent(string $userAgent)
 * @method \Hybrid\Http\Client\PendingRequest withoutRedirecting()
 * @method \Hybrid\Http\Client\PendingRequest withoutVerifying()
 * @method \Hybrid\Http\Client\PendingRequest throw(callable $callback = null)
 * @method \Hybrid\Http\Client\PendingRequest throwIf($condition)
 * @method \Hybrid\Http\Client\PendingRequest throwUnless($condition)
 * @method array pool(callable $callback)
 * @method \Hybrid\Http\Client\Response delete(string $url, array $data = [])
 * @method \Hybrid\Http\Client\Response get(string $url, array|string|null $query = null)
 * @method \Hybrid\Http\Client\Response head(string $url, array|string|null $query = null)
 * @method \Hybrid\Http\Client\Response patch(string $url, array $data = [])
 * @method \Hybrid\Http\Client\Response post(string $url, array $data = [])
 * @method \Hybrid\Http\Client\Response put(string $url, array $data = [])
 * @method \Hybrid\Http\Client\Response send(string $method, string $url, array $options = [])
 */
class Factory {

    use Macroable {
        __call as macroCall;
    }

    /**
     * The event dispatcher implementation.
     *
     * @var \Hybrid\Contracts\Events\Dispatcher|null
     */
    protected $dispatcher;

    /**
     * The stub callables that will handle requests.
     *
     * @var \Hybrid\Tools\Collection
     */
    protected $stubCallbacks;

    /**
     * Indicates if the factory is recording requests and responses.
     *
     * @var bool
     */
    protected $recording = false;

    /**
     * The recorded response array.
     *
     * @var array
     */
    protected $recorded = [];

    /**
     * All created response sequences.
     *
     * @var array
     */
    protected $responseSequences = [];

    /**
     * Indicates that an exception should be thrown if any request is not faked.
     *
     * @var bool
     */
    protected $preventStrayRequests = false;

    /**
     * Create a new factory instance.
     *
     * @param  \Hybrid\Contracts\Events\Dispatcher|null $dispatcher
     * @return void
     */
    public function __construct( Dispatcher $dispatcher = null ) {
        $this->dispatcher = $dispatcher;

        $this->stubCallbacks = collect();
    }

    /**
     * Create a new response instance for use during stubbing.
     *
     * @param  array|string|null $body
     * @param  int               $status
     * @param  array             $headers
     * @return \GuzzleHttp\Promise\PromiseInterface
     */
    public static function response( $body = null, $status = 200, $headers = [] ) {
        if ( is_array( $body ) ) {
            $body = json_encode( $body );

            $headers['Content-Type'] = 'application/json';
        }

        $response = new Psr7Response( $status, $headers, $body );

        return class_exists( Create::class )
            ? Create::promiseFor( $response )
            : promise_for( $response );
    }

    /**
     * Get an invokable object that returns a sequence of responses in order for use during stubbing.
     *
     * @param  array $responses
     * @return \Hybrid\Http\Client\ResponseSequence
     */
    public function sequence( array $responses = [] ) {
        return $this->responseSequences[] = new ResponseSequence( $responses );
    }

    /**
     * Register a stub callable that will intercept requests and be able to return stub responses.
     *
     * @param  callable|array $callback
     * @return $this
     */
    public function fake( $callback = null ) {
        $this->record();

        $this->recorded = [];

        if ( is_null( $callback ) ) {
            $callback = static fn() => static::response();
        }

        if ( is_array( $callback ) ) {
            foreach ( $callback as $url => $callable ) {
                $this->stubUrl( $url, $callable );
            }

            return $this;
        }

        $this->stubCallbacks = $this->stubCallbacks->merge(collect([
            static function ( $request, $options ) use ( $callback ) {
                $response = $callback instanceof Closure
                                ? $callback( $request, $options )
                                : $callback;

                if ( $response instanceof PromiseInterface ) {
                    $options['on_stats'](new TransferStats(
                        $request->toPsrRequest(),
                        $response->wait()
                    ));
                }

                return $response;
            },
        ]));

        return $this;
    }

    /**
     * Register a response sequence for the given URL pattern.
     *
     * @param  string $url
     * @return \Hybrid\Http\Client\ResponseSequence
     */
    public function fakeSequence( $url = '*' ) {
        return tap($this->sequence(), function ( $sequence ) use ( $url ) {
            $this->fake( [ $url => $sequence ] );
        });
    }

    /**
     * Stub the given URL using the given callback.
     *
     * @param  string                                                                         $url
     * @param  \Hybrid\Http\Client\Response|\GuzzleHttp\Promise\PromiseInterface|callable $callback
     * @return $this
     */
    public function stubUrl( $url, $callback ) {
        return $this->fake(static function ( $request, $options ) use ( $url, $callback ) {
            if ( ! Str::is( Str::start( $url, '*' ), $request->url() ) ) {
                return;
            }

            return $callback instanceof Closure || $callback instanceof ResponseSequence
                        ? $callback( $request, $options )
                        : $callback;
        });
    }

    /**
     * Indicate that an exception should be thrown if any request is not faked.
     *
     * @param  bool $prevent
     * @return $this
     */
    public function preventStrayRequests( $prevent = true ) {
        $this->preventStrayRequests = $prevent;

        return $this;
    }

    /**
     * Indicate that an exception should not be thrown if any request is not faked.
     *
     * @return $this
     */
    public function allowStrayRequests() {
        return $this->preventStrayRequests( false );
    }

    /**
     * Begin recording request / response pairs.
     *
     * @return $this
     */
    protected function record() {
        $this->recording = true;

        return $this;
    }

    /**
     * Record a request response pair.
     *
     * @param  \Hybrid\Http\Client\Request  $request
     * @param  \Hybrid\Http\Client\Response $response
     * @return void
     */
    public function recordRequestResponsePair( $request, $response ) {
        if ( $this->recording ) {
            $this->recorded[] = [ $request, $response ];
        }
    }

    /**
     * Assert that a request / response pair was recorded matching a given truth test.
     *
     * @param  callable $callback
     * @return void
     */
    public function assertSent( $callback ) {
        PHPUnit::assertTrue(
            $this->recorded( $callback )->count() > 0,
            'An expected request was not recorded.'
        );
    }

    /**
     * Assert that the given request was sent in the given order.
     *
     * @param  array $callbacks
     * @return void
     */
    public function assertSentInOrder( $callbacks ) {
        $this->assertSentCount( count( $callbacks ) );

        foreach ( $callbacks as $index => $url ) {
            $callback = is_callable( $url )
                ? $url
                : static fn( $request ) => $request->url() === $url;

            PHPUnit::assertTrue($callback(
                $this->recorded[ $index ][0],
                $this->recorded[ $index ][1]
            ), 'An expected request (#' . ( $index + 1 ) . ') was not recorded.');
        }
    }

    /**
     * Assert that a request / response pair was not recorded matching a given truth test.
     *
     * @param  callable $callback
     * @return void
     */
    public function assertNotSent( $callback ) {
        PHPUnit::assertFalse(
            $this->recorded( $callback )->count() > 0,
            'Unexpected request was recorded.'
        );
    }

    /**
     * Assert that no request / response pair was recorded.
     *
     * @return void
     */
    public function assertNothingSent() {
        PHPUnit::assertEmpty( $this->recorded, 'Requests were recorded.' );
    }

    /**
     * Assert how many requests have been recorded.
     *
     * @param  int $count
     * @return void
     */
    public function assertSentCount( $count ) {
        PHPUnit::assertCount( $count, $this->recorded );
    }

    /**
     * Assert that every created response sequence is empty.
     *
     * @return void
     */
    public function assertSequencesAreEmpty() {
        foreach ( $this->responseSequences as $responseSequence ) {
            PHPUnit::assertTrue(
                $responseSequence->isEmpty(),
                'Not all response sequences are empty.'
            );
        }
    }

    /**
     * Get a collection of the request / response pairs matching the given truth test.
     *
     * @param  callable $callback
     * @return \Hybrid\Tools\Collection
     */
    public function recorded( $callback = null ) {
        if ( empty( $this->recorded ) ) {
            return collect();
        }

        $callback = $callback ?: static fn() => true;

        return collect( $this->recorded )->filter( static fn( $pair ) => $callback( $pair[0], $pair[1] ) );
    }

    /**
     * Create a new pending request instance for this factory.
     *
     * @return \Hybrid\Http\Client\PendingRequest
     */
    protected function newPendingRequest() {
        return new PendingRequest( $this );
    }

    /**
     * Get the current event dispatcher implementation.
     *
     * @return \Hybrid\Contracts\Events\Dispatcher|null
     */
    public function getDispatcher() {
        return $this->dispatcher;
    }

    /**
     * Execute a method against a new pending request instance.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return mixed
     */
    public function __call( $method, $parameters ) {
        if ( static::hasMacro( $method ) ) {
            return $this->macroCall( $method, $parameters );
        }

        return tap($this->newPendingRequest(), function ( $request ) {
            $request->stub( $this->stubCallbacks )->preventStrayRequests( $this->preventStrayRequests );
        })->{$method}( ...$parameters );
    }

}
