<?php

declare(strict_types=1);

require_once APP_ROOT . '/app/services/ClienteService.php';

class ClienteController
{
    public static function actualizar(int $id): void
    {
        $body = json_decode(Flight::request()->body, true) ?? [];

        if (empty($body['nombres']) || empty($body['apellidos'])) {
            ApiResponse::error('Nombres y apellidos son requeridos', 400)->send();
            return;
        }

        $service = new ClienteService(Flight::db());
        $cliente = $service->actualizar($id, $body);

        if ($cliente === null) {
            ApiResponse::error('Cliente no encontrado', 404)->send();
            return;
        }

        ApiResponse::success('Cliente actualizado', $cliente)->send();
    }

    public static function obtenerPorDocumento(string $documento): void
    {
        $service = new ClienteService(Flight::db());
        $cliente = $service->obtenerPorDocumento($documento);

        if ($cliente === null) {
            ApiResponse::error('Cliente no encontrado', 404)->send();
            return;
        }

        ApiResponse::success('OK', $cliente)->send();
    }
}
