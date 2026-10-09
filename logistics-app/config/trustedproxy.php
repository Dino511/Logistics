<?php

return [

    /*
    | Proxies allowed to tell the app the visitor's real address and that the visit came
    | over HTTPS. Needed when the app sits behind an HTTPS tunnel or a load balancer:
    | without it, links and forms point at http and location sharing stays blocked.
    | Leave empty when the app is reached directly. Use "*" only behind a tunnel or
    | proxy you control, since it lets that proxy state the visitor's address.
    */

    'proxies' => env('TRUSTED_PROXIES'),

];
