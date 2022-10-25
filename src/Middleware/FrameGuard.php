<?php

namespace Hybrid\Http\Middleware;

use Closure;

class FrameGuard {

    /**
     * Handle the given request and get the response.
     *
     * @param  \Hybrid\Http\Request $request
     * @param  \Closure                 $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle( $request, Closure $next ) {
        $response = $next( $request );

        $response->headers->set( 'X-Frame-Options', 'SAMEORIGIN', false );

        return $response;
    }

}
