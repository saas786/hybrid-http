<?php

namespace Hybrid\Http\Client;

use GuzzleHttp\Psr7\Message;

use function GuzzleHttp\Psr7\get_message_body_summary;

class RequestException extends \Hybrid\Http\Client\HttpClientException {

    /**
     * The response instance.
     *
     * @var \Hybrid\Http\Client\Response
     */
    public $response;

    /**
     * Create a new exception instance.
     *
     * @param  \Hybrid\Http\Client\Response $response
     * @return void
     */
    public function __construct( Response $response ) {
        parent::__construct( $this->prepareMessage( $response ), $response->status() );

        $this->response = $response;
    }

    /**
     * Prepare the exception message.
     *
     * @param  \Hybrid\Http\Client\Response $response
     * @return string
     */
    protected function prepareMessage( Response $response ) {
        $message = "HTTP request returned status code {$response->status()}";

        $summary = class_exists( Message::class )
            ? Message::bodySummary( $response->toPsrResponse() )
            : get_message_body_summary( $response->toPsrResponse() );

        return is_null( $summary ) ? $message : $message .= ":\n{$summary}\n";
    }

}
