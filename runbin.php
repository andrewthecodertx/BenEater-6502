<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use EaterEmulator\ROM;
use EaterEmulator\RAM;
use EaterEmulator\SystemBus;
use EaterEmulator\Peripherals\VIA;
use EaterEmulator\Peripherals\ACIA;
use andrewthecoder\MOS6502\CPU;

// Register shutdown function to restore terminal
register_shutdown_function(function () {
    ACIA::restoreTerminal();
    echo "\n";
});

// Handle Ctrl+C gracefully
pcntl_signal(SIGINT, function () {
    ACIA::restoreTerminal();
    echo "\n\nInterrupted.\n";
    exit(0);
});

if ($argc < 2) {
    echo "Usage: {$argv[0]} <binary_file> [clock_hz]\n";
    echo "\nOptions:\n";
    echo "  binary_file  - ROM file to load\n";
    echo "  clock_hz     - Clock speed in Hz (default: 1000, 0 = unlimited)\n";
    exit(1);
}

$binaryFile = $argv[1];
$clockHz = isset($argv[2]) ? (int)$argv[2] : 1000;

if (!file_exists($binaryFile)) {
    echo "Error: File not found: {$binaryFile}\n";
    exit(1);
}

// Clear screen and setup display
echo "\033[2J\033[H";

echo "╔═══════════════════════════════════════════════════════════════════════════════╗\n";
echo "║                  Ben Eater 6502 Breadboard Computer Emulator                  ║\n";
echo "╚═══════════════════════════════════════════════════════════════════════════════╝\n";
echo "ROM: {$binaryFile}\n";
if ($clockHz > 0) {
    echo "Clock: " . number_format($clockHz) . " Hz\n";
} else {
    echo "Clock: Unlimited\n";
}
echo "\n";

$rom = new ROM($binaryFile);
$ram = new RAM();
$via = new VIA();
$acia = new ACIA();

$bus = new SystemBus($ram, $rom);
$bus->addPeripheral($acia);  // ACIA at $5000
$bus->addPeripheral($via);   // VIA at $6000

$cpu = new CPU($bus);

$bus->setCpu($cpu);
$cpu->reset();

// Display fixed header
echo "┌─ VIA LEDs ────────────────────────────────────────────────────────────────────┐\n";
echo "│ Port A:  ○   ○   ○   ○   ○   ○   ○   ○   [0x00]                               │\n";
echo "│ Port B:  ○   ○   ○   ○   ○   ○   ○   ○   [0x00]                               │\n";
echo "└───────────────────────────────────────────────────────────────────────────────┘\n";
echo "┌─ Serial Console (ACIA) ───────────────────────────────────────────────────────┐\n";

// Save cursor position for LED updates
$ledLineA = 8; // Line number for Port A
$ledLineB = 9; // Line number for Port B
$consoleLine = 12; // Line where console output starts

$lastPortA = 0;
$lastPortB = 0;
$cycleCount = 0;
$startTime = microtime(true);

$usecsPerInstruction = $clockHz > 0 ? (1000000 / $clockHz) : 0;
$nextInstructionTime = microtime(true);

while (true) {
    // Dispatch signals for Ctrl+C handling
    if (function_exists('pcntl_signal_dispatch')) {
        pcntl_signal_dispatch();
    }

    $cpu->step();
    $cycleCount++;

    if ($usecsPerInstruction > 0) {
        $nextInstructionTime += $usecsPerInstruction / 1000000;
        $sleepTime = $nextInstructionTime - microtime(true);

        if ($sleepTime > 0) {
            usleep((int)($sleepTime * 1000000));
        }
    }

    // Check for LED changes every 100 cycles
    if ($cycleCount % 100 === 0) {
        $currentPortA = $via->getPortAOutput();
        $currentPortB = $via->getPortBOutput();

        if ($currentPortA !== $lastPortA || $currentPortB !== $lastPortB) {
            // Update LED display
            updateLEDs($ledLineA, $ledLineB, $currentPortA, $currentPortB);

            $lastPortA = $currentPortA;
            $lastPortB = $currentPortB;
        }
    }
}

// Move to bottom of display
echo "\n";
echo "└───────────────────────────────────────────────────────────────────────────────┘\n";

$endTime = microtime(true);
$elapsed = $endTime - $startTime;

echo "\n";
echo "Execution complete.\n";
echo "Total cycles: {$cycleCount}\n";
echo "Elapsed time: " . number_format($elapsed, 3) . " seconds\n";
if ($elapsed > 0) {
    echo "Effective speed: " . number_format($cycleCount / $elapsed, 0) . " Hz\n";
}

function updateLEDs(int $lineA, int $lineB, int $portA, int $portB): void
{
    // Save current cursor position
    echo "\033[s";

    // Update Port A
    echo "\033[{$lineA};1H"; // Move to line A
    echo "│ Port A: " . formatLEDs($portA) . sprintf(" [0x%02X]                          │", $portA);

    // Update Port B
    echo "\033[{$lineB};1H"; // Move to line B
    echo "│ Port B: " . formatLEDs($portB) . sprintf(" [0x%02X]                          │", $portB);

    // Restore cursor position
    echo "\033[u";
    flush();
}

function formatLEDs(int $value): string
{
    $leds = '';
    for ($bit = 7; $bit >= 0; $bit--) {
        $isOn = ($value & (1 << $bit)) !== 0;
        if ($isOn) {
            $leds .= "\033[31m ● \033[0m";
        } else {
            $leds .= " ○ ";
        }
    }

    return $leds;
}
