<?php

declare(strict_types=1);

namespace Mike42\Escpos\PrintConnectors;

use Exception;

/**
 * Sends raw ESC/POS data to a locally installed Windows printer (USB or otherwise)
 * without requiring the printer to be shared on the network.
 * Uses winspool.drv via an inline PowerShell script.
 */
class WindowsSpoolerConnector implements PrintConnector
{
    private ?array $buffer;

    public function __construct(private string $printerName)
    {
        $this->buffer = [];
    }

    public function __destruct()
    {
        if ($this->buffer !== null) {
            trigger_error('Print connector was not finalized. Did you forget to close the printer?', E_USER_NOTICE);
        }
    }

    public function write($data): void
    {
        $this->buffer[] = $data;
    }

    /** @return string|false */
    public function read($_len)
    {
        return false;
    }

    public function finalize(): void
    {
        $data         = implode($this->buffer);
        $this->buffer = null;

        $dataFile = tempnam(sys_get_temp_dir(), 'escpos');
        file_put_contents($dataFile, $data);

        $psFile = tempnam(sys_get_temp_dir(), 'escpos') . '.ps1';
        file_put_contents($psFile, $this->buildScript());

        try {
            $this->runScript($psFile, $dataFile);
        } finally {
            @unlink($dataFile);
            @unlink($psFile);
        }
    }

    private function runScript(string $psFile, string $dataFile): void
    {
        $powershell = 'C:\\Windows\\System32\\WindowsPowerShell\\v1.0\\powershell.exe';

        $cmd = sprintf(
            '"%s" -NonInteractive -NoProfile -ExecutionPolicy Bypass -File "%s" "%s" "%s"',
            $powershell,
            $psFile,
            $this->printerName,
            $dataFile
        );

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (!is_resource($process)) {
            throw new Exception('WindowsSpoolerConnector: no se pudo iniciar PowerShell');
        }

        fclose($pipes[0]);
        $stdout   = stream_get_contents($pipes[1]);
        $stderr   = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);

        if ($exitCode !== 0 || trim($stderr) !== '') {
            $detail = trim($stderr ?: $stdout);
            throw new Exception("WindowsSpoolerConnector (exit $exitCode): $detail");
        }
    }

    private function buildScript(): string
    {
        return <<<'PWSH'
param([string]$printerName, [string]$filePath)
$ErrorActionPreference = "Stop"
Add-Type -TypeDefinition @"
using System;
using System.Runtime.InteropServices;
public class WinSpooler {
    [DllImport("winspool.drv", CharSet=CharSet.Auto, SetLastError=true)]
    public static extern bool OpenPrinter(string name, out IntPtr h, IntPtr d);
    [DllImport("winspool.drv", CharSet=CharSet.Auto, SetLastError=true)]
    public static extern int StartDocPrinter(IntPtr h, int lv, ref DOCINFO di);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool StartPagePrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool WritePrinter(IntPtr h, byte[] buf, int n, out int written);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool EndPagePrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool EndDocPrinter(IntPtr h);
    [DllImport("winspool.drv", SetLastError=true)]
    public static extern bool ClosePrinter(IntPtr h);
    [StructLayout(LayoutKind.Sequential, CharSet=CharSet.Auto)]
    public struct DOCINFO {
        [MarshalAs(UnmanagedType.LPTStr)] public string pDocName;
        [MarshalAs(UnmanagedType.LPTStr)] public string pOutputFile;
        [MarshalAs(UnmanagedType.LPTStr)] public string pDataType;
    }
}
"@
$bytes = [System.IO.File]::ReadAllBytes($filePath)
$h = [IntPtr]::Zero
if (-not [WinSpooler]::OpenPrinter($printerName, [ref]$h, [IntPtr]::Zero)) {
    $err = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
    throw "OpenPrinter fallo (Win32 error $err) para '$printerName'"
}
$di = New-Object WinSpooler+DOCINFO
$di.pDocName    = "ESCPOS"
$di.pDataType   = "RAW"
$di.pOutputFile = $null
$jobId = [WinSpooler]::StartDocPrinter($h, 1, [ref]$di)
if ($jobId -le 0) {
    $err = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
    [WinSpooler]::ClosePrinter($h) | Out-Null
    throw "StartDocPrinter fallo (Win32 error $err)"
}
if (-not [WinSpooler]::StartPagePrinter($h)) {
    $err = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
    [WinSpooler]::EndDocPrinter($h) | Out-Null
    [WinSpooler]::ClosePrinter($h) | Out-Null
    throw "StartPagePrinter fallo (Win32 error $err)"
}
$written = 0
if (-not [WinSpooler]::WritePrinter($h, $bytes, $bytes.Length, [ref]$written)) {
    $err = [System.Runtime.InteropServices.Marshal]::GetLastWin32Error()
    [WinSpooler]::EndPagePrinter($h) | Out-Null
    [WinSpooler]::EndDocPrinter($h) | Out-Null
    [WinSpooler]::ClosePrinter($h) | Out-Null
    throw "WritePrinter fallo (Win32 error $err)"
}
[WinSpooler]::EndPagePrinter($h) | Out-Null
[WinSpooler]::EndDocPrinter($h) | Out-Null
[WinSpooler]::ClosePrinter($h) | Out-Null
Write-Output "OK: $written bytes enviados a '$printerName' (job $jobId)"
PWSH;
    }
}
