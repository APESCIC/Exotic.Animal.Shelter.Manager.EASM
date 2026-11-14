<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Trusted proxies
    |--------------------------------------------------------------------------
    |
    | Laravel's TrustProxies middleware reads this on every request. Off by
    | default, so X-Forwarded-* headers are ignored. Set EASM_TRUSTED_PROXIES
    | when a proxy in front of EASM ends HTTPS (Cloudflare, a load balancer,
    | many shared hosts): "*" trusts whichever address connects, or give a
    | comma-separated list of proxy addresses or CIDR ranges.
    |
    */

    'proxies' => env('EASM_TRUSTED_PROXIES') ?: null,

];
