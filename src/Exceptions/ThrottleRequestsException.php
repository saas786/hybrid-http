<?php

namespace Illuminate\Http\Exceptions;

class ThrottleRequestsException extends \Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException {

    /**
     * Create a new throttle requests exception instance.
     *
     * @param  string          $message
     * @param  \Throwable|null $previous
     * @param  array           $headers
     * @param  int             $code
     * @return void
     */
    public function __construct( $message = '', \Throwable $previous = null, array $headers = [], $code = 0 ) {
        parent::__construct( null, $message, $previous, $code, $headers );
    }

}
