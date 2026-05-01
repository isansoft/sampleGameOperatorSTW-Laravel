<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AllowLiveKitFramePermissions
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $origin = $this->liveKitFrameOrigin();

        if ($origin !== null) {
            $response->headers->set('Permissions-Policy', $this->policyFor($origin));
        }

        return $response;
    }

    private function liveKitFrameOrigin(): ?string
    {
        $configuredOrigin = (string) config('services.prime_mac.livekit_frame_origin', '');
        $parts = parse_url($configuredOrigin);

        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return $parts['scheme'].'://'.$parts['host'].$port;
    }

    private function policyFor(string $origin): string
    {
        $features = [
            'camera',
            'microphone',
            'autoplay',
            'fullscreen',
            'display-capture',
        ];

        return collect($features)
            ->map(fn (string $feature) => "{$feature}=(self \"{$origin}\")")
            ->implode(', ');
    }
}
