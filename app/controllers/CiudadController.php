<?php

declare(strict_types=1);

require_once APP_ROOT . '/app/services/CiudadService.php';

class CiudadController
{
    public static function listar(): void
    {
        $service = new CiudadService(Flight::db());
        ApiResponse::success('OK', $service->listar())->send();
    }
}
