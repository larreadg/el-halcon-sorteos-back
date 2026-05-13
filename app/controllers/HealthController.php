<?php

declare(strict_types=1);

require_once APP_ROOT . '/app/services/HealthService.php';

class HealthController
{
    public static function check(): void
    {
        $service = new HealthService(Flight::db());
        ApiResponse::success('OK', $service->check())->send();
    }
}
