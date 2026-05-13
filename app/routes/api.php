<?php

declare(strict_types=1);

require_once APP_ROOT . '/app/controllers/HealthController.php';
require_once APP_ROOT . '/app/controllers/ClienteController.php';
require_once APP_ROOT . '/app/controllers/CiudadController.php';
require_once APP_ROOT . '/app/controllers/CuponController.php';
require_once APP_ROOT . '/app/controllers/PrintController.php';

Flight::route('GET /health', [HealthController::class, 'check']);

Flight::route('GET /clientes/documento/@documento', [ClienteController::class, 'obtenerPorDocumento']);
Flight::route('PUT /clientes/@id', [ClienteController::class, 'actualizar']);
Flight::route('POST /clientes/@id/cupones', [CuponController::class, 'generar']);

Flight::route('GET /ciudades', [CiudadController::class, 'listar']);

Flight::route('POST /cupones/@id/imprimir', [PrintController::class, 'imprimir']);
