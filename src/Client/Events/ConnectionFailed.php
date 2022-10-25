<?php

namespace Hybrid\Http\Client\Events;

use Hybrid\Http\Client\Request;

class ConnectionFailed {

    /**
     * The request instance.
     *
     * @var \Hybrid\Http\Client\Request
     */
    public $request;

    /**
     * Create a new event instance.
     *
     * @param  \Hybrid\Http\Client\Request $request
     * @return void
     */
    public function __construct( Request $request ) {
        $this->request = $request;
    }

}
