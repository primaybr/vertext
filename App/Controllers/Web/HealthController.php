<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use Core\Controller as Controller;

/**
 * GET /health - Kubernetes liveness/readiness probe target (see Dockerfile's
 * HEALTHCHECK and the Helm chart's probe config). Deliberately does a real,
 * cheap DB round-trip (not just "PHP is up") - a pod that can't reach Postgres
 * should fail its readiness check and stop receiving traffic, not report healthy.
 */
class HealthController extends Controller
{
    public function index(): void
    {
        try {
            (new \Core\Model('settings'))->select('1 as ok')->get(1);
            $this->json(['ok' => true]);
        } catch (\Throwable) {
            $this->json(['ok' => false], 503);
        }
    }
}
