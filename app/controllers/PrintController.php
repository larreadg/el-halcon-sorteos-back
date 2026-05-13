<?php

declare(strict_types=1);

require_once APP_ROOT . '/app/services/PrintService.php';

class PrintController
{
    public static function imprimir(int $id): void
    {
        try {
            $service   = new PrintService(Flight::db());
            $resultado = $service->imprimirCupon($id);

            if ($resultado === null) {
                ApiResponse::error('Cupón no encontrado', 404)->send();
                return;
            }

            ApiResponse::success('Impresión enviada', $resultado)->send();
        } catch (Throwable $e) {
            ApiResponse::error('No se pudo imprimir: ' . $e->getMessage(), 500)->send();
        }
    }
}
