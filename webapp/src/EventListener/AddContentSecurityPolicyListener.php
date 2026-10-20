<?php declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Profiler\Profiler;

#[AsEventListener]
readonly class AddContentSecurityPolicyListener
{
    public function __construct(protected ?Profiler $profiler) {}

    public function __invoke(ResponseEvent $event): void
    {
        // Set the correct CSP based on whether the profiler is enabled, since
        // the profiler requires 'unsafe-eval' for script-src 'self'.
        $response = $event->getResponse();
        $route = $event->getRequest()->attributes->get('_route');
        $cspExtra = $this->profiler ? "'unsafe-eval'" : "";
        // The team code editor checks syntax with tree-sitter, which is WebAssembly.
        if ($route === 'team_submit') {
            $cspExtra .= " 'wasm-unsafe-eval'";
        }
        $csp = "default-src 'self'; style-src 'self' 'unsafe-inline'; script-src 'self' 'unsafe-inline' $cspExtra; img-src 'self' data:; worker-src 'self' blob:; font-src 'self' data:;";
        // The team code editor shows the problem statement in an iframe. Browsers ignore
        // the webserver's X-Frame-Options: DENY when frame-ancestors is present.
        if ($route === 'team_problem_statement' || $route === 'team_contest_problemset') {
            $csp .= " frame-ancestors 'self';";
        }
        $response->headers->set('Content-Security-Policy', $csp);
    }
}
