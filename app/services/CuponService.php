<?php

declare(strict_types=1);

use flight\database\SimplePdo;

class CuponService
{
    public function __construct(private SimplePdo $db) {}

    public function generar(int $clienteId, float $montoCompra): ?array
    {
        if ($montoCompra <= 0) {
            throw new InvalidArgumentException('El monto de compra debe ser mayor a cero');
        }

        $cliente = $this->db->fetchRow(
            'SELECT id FROM cliente WHERE id = ?',
            [$clienteId]
        );

        if ($cliente === null) {
            return null;
        }

        $parametros = $this->obtenerParametros();

        if ($montoCompra < $parametros['monto_por_regla']) {
            throw new InvalidArgumentException(
                'El monto mínimo de compra para generar cupones es Gs. ' .
                number_format($parametros['monto_por_regla'], 0, ',', '.')
            );
        }

        $cantidadCupones = $this->calcularCantidadCupones(
            $montoCompra,
            $parametros['monto_por_regla'],
            $parametros['cupones_por_regla']
        );

        $fechaCreacion = date('Y-m-d H:i:s');

        return $this->db->transaction(function (SimplePdo $db) use ($clienteId, $montoCompra, $cantidadCupones, $fechaCreacion) {
            $cuponId = $db->insert('cupon', [
                'cliente_id' => $clienteId,
                'monto_compra' => $montoCompra,
                'cantidad_cupon' => $cantidadCupones,
                'fecha_creacion' => $fechaCreacion,
            ]);

            return [
                'id' => (int) $cuponId,
                'cliente_id' => $clienteId,
                'monto_compra' => $montoCompra,
                'cantidad_cupon' => $cantidadCupones,
                'fecha_creacion' => $fechaCreacion,
            ];
        });
    }

    private function obtenerParametros(): array
    {
        $rows = $this->db->fetchAll(
            'SELECT clave, valor FROM parametro WHERE clave IN (?, ?)',
            ['monto_por_regla', 'cupones_por_regla']
        );

        $parametros = [];

        foreach ($rows as $row) {
            $parametros[$row['clave']] = (float) $row['valor'];
        }

        $montoPorRegla = $parametros['monto_por_regla'] ?? 0;
        $cuponesPorRegla = $parametros['cupones_por_regla'] ?? 0;

        if ($montoPorRegla <= 0) {
            throw new InvalidArgumentException('El parametro monto_por_regla debe ser mayor a cero');
        }

        if ($cuponesPorRegla <= 0) {
            throw new InvalidArgumentException('El parametro cupones_por_regla debe ser mayor a cero');
        }

        return [
            'monto_por_regla' => $montoPorRegla,
            'cupones_por_regla' => (int) $cuponesPorRegla,
        ];
    }

    private function calcularCantidadCupones(float $montoCompra, float $montoPorRegla, int $cuponesPorRegla): int
    {
        $reglasCumplidas = (int) floor($montoCompra / $montoPorRegla);

        return $reglasCumplidas * $cuponesPorRegla;
    }
}
