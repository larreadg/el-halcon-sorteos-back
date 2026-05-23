<?php

declare(strict_types=1);

use flight\database\SimplePdo;

class PrintService
{
    private const ANCHO = 48;
    private const MAX_TICKETS = 50;
    private const PRINTER_NAME = 'FTX-TDR080UE';

    public function __construct(private SimplePdo $db) {}

    public function imprimirCupon(int $cuponId): ?array
    {
        $datos = $this->obtenerDatosCupon($cuponId);

        if ($datos === null) {
            return null;
        }

        $tmpFile = '/tmp/ticket-cupon-' . $cuponId . '-' . time() . '.bin';

        $contenido = '';
        $limite = min((int) $datos['cantidad_cupon'], self::MAX_TICKETS);

        for ($i = 1; $i <= $limite; $i++) {
            $contenido .= $this->generarTicket($datos, $i);
        }

        file_put_contents($tmpFile, $contenido);

        $this->enviarACups($tmpFile);

        @unlink($tmpFile);

        return [
            'cupon_id' => (int) $datos['id'],
            'cantidad_cupon' => (int) $datos['cantidad_cupon'],
            'impresora' => self::PRINTER_NAME,
        ];
    }

    private function obtenerDatosCupon(int $cuponId): ?array
    {
        $row = $this->db->fetchRow(
            'SELECT c.id, c.monto_compra, c.cantidad_cupon, c.fecha_creacion,
                    cl.nombres, cl.apellidos, cl.documento, cl.ciudad, cl.telefono
             FROM cupon c
             JOIN cliente cl ON cl.id = c.cliente_id
             WHERE c.id = ?',
            [$cuponId]
        );

        return $row ? $row->getData() : null;
    }

    private function enviarACups(string $tmpFile): void
    {
        if (!file_exists($tmpFile)) {
            throw new RuntimeException('No se generó el archivo temporal de impresión');
        }

        $printer = escapeshellarg(self::PRINTER_NAME);
        $file = escapeshellarg($tmpFile);

        exec("lp -d {$printer} -o raw {$file} 2>&1", $output, $code);

        if ($code !== 0) {
            throw new RuntimeException('Error enviando a CUPS: ' . implode(' ', $output));
        }
    }

    private function generarTicket(array $datos, int $numero): string
    {
        $sep = str_repeat('=', self::ANCHO);

        $nombre = $this->recortar($this->ascii(strtoupper($datos['nombres'] . ' ' . $datos['apellidos'])));
        $documento = $this->recortar('CI: ' . $datos['documento']);
        $ciudad = $this->recortar($this->ascii(strtoupper($datos['ciudad'] ?? '-')));
        $telefono = $this->recortar($datos['telefono'] ?? '-');
        $fecha = date('d/m/Y H:i', strtotime($datos['fecha_creacion']));
        $monto = 'Gs. ' . number_format((float) $datos['monto_compra'], 0, ',', '.');
        $serie = sprintf('%08d-%d', (int) $datos['id'], $numero);
        $total = (int) $datos['cantidad_cupon'];

        return
            $this->initPrinter() .
            $this->alignCenter() .
            "EL HALCON SORTEOS\n" .
            $sep . "\n" .
            $this->alignLeft() .
            $nombre . "\n" .
            $documento . "\n" .
            $ciudad . "\n" .
            $telefono . "\n" .
            $this->recortar($fecha . ' ' . $monto) . "\n" .
            $this->alignCenter() .
            $sep . "\n" .
            "CUPON {$numero} DE {$total}\n" .
            "SERIE: {$serie}\n" .
            $sep . "\n" .
            "\n\n\n" .
            $this->cutPaper();
    }

    private function initPrinter(): string
    {
        return chr(27) . chr(64); // ESC @
    }

    private function alignLeft(): string
    {
        return chr(27) . chr(97) . chr(0); // ESC a 0
    }

    private function alignCenter(): string
    {
        return chr(27) . chr(97) . chr(1); // ESC a 1
    }

    private function cutPaper(): string
    {
        return chr(29) . chr(86) . chr(0); // GS V 0
    }

    private function recortar(string $text): string
    {
        return substr($text, 0, self::ANCHO);
    }

    private function ascii(string $text): string
    {
        $result = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);
        return $result !== false ? $result : $text;
    }
}