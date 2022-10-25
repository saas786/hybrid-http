<?php

namespace Hybrid\Http\Client\Events;

use Hybrid\Http\Client\Request;
use Hybrid\Http\Client\Response;

class ResponseReceived {

    /**
     * The request instance.
     *
     * @var \Hybrid\Http\Client\Request
     */
    public $request;

    /**
     * The response instance.
     *
     * @var \Hybrid\Http\Client\Response
     */
    public $response;

    /**
     * Create a new event instance.
     *
     * @param  \Hybrid\Http\Client\Request  $request
     * @param  \Hybrid\Http\Client\Response $response
     * @return void
     */
    public function __construct( Request $request, Response $response ) {
        $this->request  = $request;
        $this->response = $response;
    }

}
