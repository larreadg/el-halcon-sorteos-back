<?php

declare(strict_types=1);

use flight\database\SimplePdo;
use Mike42\Escpos\Printer;
use Mike42\Escpos\PrintConnectors\WindowsSpoolerConnector;
use Mike42\Escpos\PrintConnectors\NetworkPrintConnector;
use Mike42\Escpos\PrintConnectors\FilePrintConnector;

class PrintService
{
    private const ANCHO = 42;
    private const MAX_TICKETS = 50;

    public function __construct(private SimplePdo $db) {}

    /**
     * @return array|null null si el cupón no existe
     */
    public function imprimirCupon(int $cuponId): ?array
    {
        $datos = $this->obtenerDatosCupon($cuponId);

        if ($datos === null) {
            return null;
        }

        $connector = $this->crearConector();
        $printer   = new Printer($connector);

        try {
            $limite = min((int) $datos['cantidad_cupon'], self::MAX_TICKETS);
            for ($i = 1; $i <= $limite; $i++) {
                $this->imprimirTicket($printer, $datos, $i);
            }
        } finally {
            $printer->close();
        }

        return [
            'cupon_id'      => (int) $datos['id'],
            'cantidad_cupon' => (int) $datos['cantidad_cupon'],
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

    private function crearConector(): object
    {
        $tipo = $_ENV['PRINTER_CONNECTOR'] ?? 'windows';

        return match ($tipo) {
            'network' => new NetworkPrintConnector(
                $_ENV['PRINTER_NETWORK_IP']   ?? '127.0.0.1',
                (int) ($_ENV['PRINTER_NETWORK_PORT'] ?? 9100)
            ),
            'file'    => new FilePrintConnector(
                $_ENV['PRINTER_FILE_PATH'] ?? '/dev/usb/lp0'
            ),
            default   => new WindowsSpoolerConnector(
                $_ENV['PRINTER_WINDOWS_NAME'] ?? 'POS-80'
            ),
        };
    }

    private function imprimirTicket(Printer $printer, array $datos, int $numero): void
    {
        $sep = str_repeat('=', self::ANCHO);

        $nombre    = $this->ascii(strtoupper($datos['nombres'] . ' ' . $datos['apellidos']));
        $documento = 'CI: ' . $datos['documento'];
        $ciudad    = $this->ascii(strtoupper($datos['ciudad'] ?? '-'));
        $telefono  = $datos['telefono'] ?? '-';
        $fecha     = date('d/m/Y H:i', strtotime($datos['fecha_creacion']));
        $monto     = 'Gs. ' . number_format((float) $datos['monto_compra'], 0, ',', '.');
        $serie     = sprintf('%08d-%d', (int) $datos['id'], $numero);
        $total     = (int) $datos['cantidad_cupon'];

        // Ciudad y teléfono en la misma línea (ciudad izquierda, teléfono derecha)
        $lineaCiudad = str_pad($ciudad, self::ANCHO - strlen($telefono)) . $telefono;
        // Fecha y monto en la misma línea (fecha izquierda, monto derecha)
        $lineaFecha  = str_pad($fecha, self::ANCHO - strlen($monto)) . $monto;

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text("EL HALCON SORTEOS\n");
        $printer->setEmphasis(false);
        $printer->text("$sep\n");

        $printer->setJustification(Printer::JUSTIFY_LEFT);
        $printer->text("$nombre\n");
        $printer->text("$documento\n");
        $printer->text("$lineaCiudad\n");
        $printer->text("$lineaFecha\n");
        $printer->text("$sep\n");

        $printer->setJustification(Printer::JUSTIFY_CENTER);
        $printer->setEmphasis(true);
        $printer->text("CUPON $numero DE $total\n");
        $printer->text("SERIE: $serie\n");
        $printer->setEmphasis(false);
        $printer->text("$sep\n");

        $printer->feed(1);
        $printer->cut();
    }

    private function ascii(string $text): string
    {
        $result = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $text);

        return $result !== false ? $result : $text;
    }
}
