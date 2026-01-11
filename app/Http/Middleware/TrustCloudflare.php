<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrustCloudflare
{
    /**
     * Handle an incoming request.
     *
     * Configurar los proxies de Cloudflare para que Laravel confíe en las IPs correctas
     */
    public function handle(Request $request, Closure $next): Response
    {
        // IPs de Cloudflare (puedes actualizar esta lista desde https://www.cloudflare.com/ips/)
        $cloudflareIPs = [
            '173.245.48.0/20',
            '103.21.244.0/22',
            '103.22.200.0/22',
            '103.31.4.0/22',
            '141.101.64.0/18',
            '108.162.192.0/18',
            '190.93.240.0/20',
            '188.114.96.0/20',
            '197.234.240.0/22',
            '198.41.128.0/17',
            '162.158.0.0/15',
            '104.16.0.0/13',
            '104.24.0.0/14',
            '172.64.0.0/13',
            '131.0.72.0/22',
        ];

        // Configurar los proxies de confianza
        if (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $request->setTrustedProxies($cloudflareIPs, Request::HEADER_X_FORWARDED_FOR);
        }

        return $next($request);
    }
}
