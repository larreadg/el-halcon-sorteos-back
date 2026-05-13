<?php

declare(strict_types=1);

use flight\database\SimplePdo;
use flight\util\Collection;

class ClienteService
{
    public function __construct(private SimplePdo $db) {}

    public function obtenerPorDocumento(string $documento): ?Collection
    {
        $cliente = $this->buscarEnLocal($documento);

        if ($cliente !== null) {
            return $cliente;
        }

        $datosApi = $this->consultarApi($documento);

        if ($datosApi === null) {
            return null;
        }

        return $this->insertarDesdeApi($datosApi);
    }

    private function buscarEnLocal(string $documento): ?Collection
    {
        return $this->db->fetchRow(
            'SELECT * FROM cliente WHERE documento = ?',
            [$documento]
        );
    }

    private function consultarApi(string $documento): ?array
    {
        $ch = curl_init(API_PERSONAS_URL . '?cedula=' . urlencode($documento));

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_USERPWD        => API_PERSONAS_USER . ':' . API_PERSONAS_PASS,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($response === false || $httpCode !== 200) {
            return null;
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
            return null;
        }

        return $data;
    }

    public function actualizar(int $id, array $data): ?Collection
    {
        $ahora = date('Y-m-d H:i:s');

        $this->db->runQuery(
            'UPDATE cliente SET nombres = ?, apellidos = ?, telefono = ?, ciudad = ?, fecha_actualizacion = ? WHERE id = ?',
            [
                $data['nombres'],
                $data['apellidos'],
                $data['telefono'] ?: null,
                $data['ciudad']   ?: null,
                $ahora,
                $id,
            ]
        );

        return $this->db->fetchRow('SELECT * FROM cliente WHERE id = ?', [$id]) ?: null;
    }

    private function insertarDesdeApi(array $datos): ?Collection
    {
        $ahora = date('Y-m-d H:i:s');

        $this->db->runQuery(
            'INSERT INTO cliente (nombres, apellidos, documento, fecha_creacion, fecha_actualizacion) VALUES (?, ?, ?, ?, ?)',
            [
                $datos['nombres'],
                $datos['apellidos'],
                $datos['cedula_identidad'],
                $ahora,
                $ahora,
            ]
        );

        return $this->buscarEnLocal($datos['cedula_identidad']);
    }
}
