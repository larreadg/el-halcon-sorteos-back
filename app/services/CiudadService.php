<?php

declare(strict_types=1);

use flight\database\SimplePdo;

class CiudadService
{
    public function __construct(private SimplePdo $db) {}

    public function listar(): array
    {
        return $this->db->fetchAll(
            'SELECT id, ciudad, departamento FROM ciudad ORDER BY ciudad ASC'
        ) ?? [];
    }
}
