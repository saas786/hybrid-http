<?php

namespace Hybrid\Http\Client;

use GuzzleHttp\Utils;

use function GuzzleHttp\choose_handler;

/**
 * @mixin \Hybrid\Http\Client\Factory
 */
class Pool {

    /**
     * The factory instance.
     *
     * @var \Hybrid\Http\Client\Factory
     */
    protected $factory;

    /**
     * The handler function for the Guzzle client.
     *
     * @var callable
     */
    protected $handler;

    /**
     * The pool of requests.
     *
     * @var array
     */
    protected $pool = [];

    /**
     * Create a new requests pool.
     *
     * @param  \Hybrid\Http\Client\Factory|null $factory
     * @return void
     */
    public function __construct( Factory $factory = null ) {
        $this->factory = $factory ?: new Factory();

        $this->handler = method_exists( Utils::class, 'chooseHandler' ) ? Utils::chooseHandler() : choose_handler();
    }

    /**
     * Add a request to the pool with a key.
     *
     * @param  string $key
     * @return \Hybrid\Http\Client\PendingRequest
     */
    public function as( string $key ) {
        return $this->pool[ $key ] = $this->asyncRequest();
    }

    /**
     * Retrieve a new async pending request.
     *
     * @return \Hybrid\Http\Client\PendingRequest
     */
    protected function asyncRequest() {
        return $this->factory->setHandler( $this->handler )->async();
    }

    /**
     * Retrieve the requests in the pool.
     *
     * @return array
     */
    public function getRequests() {
        return $this->pool;
    }

    /**
     * Add a request to the pool with a numeric index.
     *
     * @param  string $method
     * @param  array  $parameters
     * @return \Hybrid\Http\Client\PendingRequest
     */
    public function __call( $method, $parameters ) {
        return $this->pool[] = $this->asyncRequest()->$method( ...$parameters );
    }

}
