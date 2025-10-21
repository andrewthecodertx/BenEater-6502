<?php

declare(strict_types=1);

require_once __DIR__ . '/vendor/autoload.php';

use EaterEmulator\ROM;
use EaterEmulator\RAM;
use EaterEmulator\SystemBus;
use EaterEmulator\Peripherals\VIA;
use andrewthecoder\MOS6502\CPU;

if ($argc < 1) {
    echo "Usage: {$argv[0]} <binary_file>\n";
    echo "\nOptions:\n";
    echo "  binary_file  - ROM file to load\n";
    exit(1);
}

$binaryFile = $argv[1];
$clockHz = 1000;

if (!file_exists($binaryFile)) {
    echo "Error: File not found: {$binaryFile}\n";
    exit(1);
}

echo "Ben Eater Breadboard 65C02 Emulator\n";
echo "===================================\n";
echo "Loading: {$binaryFile}\n";
echo "Clock: 1 kHz\n";
echo "\n";

$rom = new ROM($binaryFile);
$ram = new RAM();
$via = new VIA();

$bus = new SystemBus($ram, $rom);
$bus->addPeripheral($via);

$cpu = new CPU($bus);

$bus->setCpu($cpu);
$cpu->reset();

echo "VIA Output (Press Ctrl+C to stop):\n\n";

$lastPortA = null;
$lastPortB = null;
$changeCount = 0;
$cycleCount = 0;
$startTime = microtime(true);

$usecsPerInstruction = (1000000 / $clockHz);
$nextInstructionTime = microtime(true);

// stop after 20,000 cycles if CTRL-C isn't pressed!
while ($cycleCount < 20000) {
    $cpu->step();
    $cycleCount++;

    if ($usecsPerInstruction > 0) {
        $nextInstructionTime += $usecsPerInstruction / 1000000;
        $sleepTime = $nextInstructionTime - microtime(true);

        if ($sleepTime > 0) {
            usleep((int)($sleepTime * 1000000));
        }
    }

    if ($cycleCount % 100 === 0) {
        $currentPortA = $via->getPortAOutput();
        $currentPortB = $via->getPortBOutput();

        if ($currentPortA !== $lastPortA || $currentPortB !== $lastPortB) {
            echo "\r\033[K";
            echo formatVIADisplay($currentPortA, $currentPortB, $cycleCount);
            flush();

            $lastPortA = $currentPortA;
            $lastPortB = $currentPortB;
            $changeCount++;
        }
    }
}

$endTime = microtime(true);
$elapsed = $endTime - $startTime;

echo "\n\n";
echo "Execution complete.\n";
echo "Total cycles: {$cycleCount}\n";
echo "Elapsed time: " . number_format($elapsed, 3) . " seconds\n";
if ($elapsed > 0) {
    echo "Effective speed: " . number_format($cycleCount / $elapsed, 0) . " Hz\n";
}
echo "Total changes: {$changeCount}\n";

function formatVIADisplay(int $portA, int $portB, int $cycle): string
{
    $display = '';
    if ($portA !== 0) {
        $display .= "A: " . formatLEDs($portA) . sprintf(" [0x%02X]  ", $portA);
    }

    if ($portB !== 0) {
        $display .= "B: " . formatLEDs($portB) . sprintf(" [0x%02X]  ", $portB);
    }

    if ($portA === 0 && $portB === 0) {
        $display .= "A: " . formatLEDs($portA) . " [0x00]  ";
        $display .= "B: " . formatLEDs($portB) . " [0x00]  ";
    }
    $display .= sprintf("Cycle: %d", $cycle);

    return $display;
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
