<?php

declare(strict_types=1);

require_once APP_ROOT . '/app/services/CuponService.php';

class CuponController
{
    public static function generar(int $id): void
    {
        $body = json_decode(Flight::request()->body, true) ?? [];
        $montoCompra = $body['monto_compra'] ?? null;

        if (!is_numeric($montoCompra) || (float) $montoCompra <= 0) {
            ApiResponse::error('El monto de compra debe ser mayor a cero', 400)->send();
            return;
        }

        try {
            $service = new CuponService(Flight::db());
            $resultado = $service->generar($id, (float) $montoCompra);

            if ($resultado === null) {
                ApiResponse::error('Cliente no encontrado', 404)->send();
                return;
            }

            ApiResponse::success('Cupones generados', $resultado, 201)->send();
        } catch (InvalidArgumentException $e) {
            ApiResponse::error($e->getMessage(), 400)->send();
        } catch (Throwable $e) {
            ApiResponse::error('No se pudieron generar los cupones', 500)->send();
        }
    }
}
