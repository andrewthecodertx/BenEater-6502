<?php

declare(strict_types=1);

if ($argc < 2) {
    echo "Usage: {$argc[0]} <assembly_file> [output_file]";
    echo "\nOptions:\n";
    echo " assembly_file - Source program\n";
    echo " out_putfile - Resulting ROM name\n";
    exit(1);
}

$assemblyFile = $argv[1];
$binFile = pathinfo($argv[1])['filename'];
$outputFile = isset($argv[2]) ? $argv[2] : "$binFile.bin";

if (!file_exists($assemblyFile)) {
    echo "Error: File not found: {$assemblyFile}\n";
    exit(1);
}

$output = [];
$returnCode = 0;

exec("./bin/vasm6502_oldstyle -Fbin -dotdir $assemblyFile -o $outputFile", $output, $returnCode);

if ($returnCode == 0) {
    echo "Binary built successfully.\n";
    echo "Output:\n";
    foreach ($output as $line) {
        echo "$line\n";
    }
} else {
    echo "Program failed with exit code: $returnCode\n";
}
