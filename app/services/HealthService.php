<?php

declare(strict_types=1);

use flight\database\SimplePdo;

class HealthService
{
    public function __construct(private SimplePdo $db) {}

    public function check(): array
    {
        return [
            'status'    => 'ok',
            'timestamp' => date('c'),
            'database'  => $this->checkDatabase(),
        ];
    }

    private function checkDatabase(): string
    {
        try {
            $this->db->runQuery('SELECT 1');
            return 'ok';
        } catch (\Exception $e) {
            return 'error';
        }
    }
}
