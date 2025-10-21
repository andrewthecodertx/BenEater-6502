<?php

declare(strict_types=1);

namespace EaterEmulator;

/**
 * Read-only memory (32KB, $8000-$FFFF).
 *
 * Supports loading from binary files (single or directory-based).
 * Uninitialized addresses return 0.
 */
class ROM
{
    public const ROM_START = 0x8000;
    public const ROM_END = 0xFFFF;
    public const ROM_SIZE = 0x8000; // 32KB

    /** @var array<int, int> */
    private array $rom = [];

    /**
     * Creates a new ROM instance.
     *
     * @param string|null $path Optional file or directory to load from
     * @throws \RuntimeException If loading fails
     */
    public function __construct(?string $path = null)
    {
        if ($path !== null) {
            if (is_dir($path)) {
                $this->loadFromDirectory($path);
            } elseif (is_file($path)) {
                $this->loadFromFile($path);
            } else {
                throw new \RuntimeException("ROM path not found: $path");
            }
        }
    }

    /**
     * Loads a binary file into ROM.
     *
     * @param string $file Path to binary file
     * @param int $offset Load offset within ROM (default: $8000)
     * @throws \RuntimeException If file cannot be read
     */
    public function loadFromFile(string $file, int $offset = self::ROM_START): void
    {
        if (!file_exists($file)) {
            throw new \RuntimeException("ROM file not found: $file");
        }

        $data = file_get_contents($file);
        if ($data === false) {
            throw new \RuntimeException("Failed to read ROM file: $file");
        }

        $this->loadBytes($data, $offset);
    }

    /**
     * Loads all .bin files from a directory into ROM.
     *
     * Files are loaded in alphabetical order. Each file's load address
     * is determined by its name: filename_ADDR.bin or defaults to $8000.
     *
     * @param string $directory Path to directory containing .bin files
     * @throws \RuntimeException If directory cannot be read
     */
    public function loadFromDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            throw new \RuntimeException("ROM directory not found: $directory");
        }

        $files = glob($directory . '/*.bin');
        if ($files === false) {
            throw new \RuntimeException("Failed to scan ROM directory: $directory");
        }

        sort($files);

        foreach ($files as $file) {
            $offset = $this->parseLoadAddress($file);
            $this->loadFromFile($file, $offset);
        }
    }

    /**
     * Loads raw bytes into ROM at the specified offset.
     *
     * @param string $data Raw binary data
     * @param int $offset Starting address in ROM space
     */
    private function loadBytes(string $data, int $offset): void
    {
        $address = $offset & 0xFFFF;
        $length = strlen($data);

        for ($i = 0; $i < $length; $i++) {
            if ($address >= self::ROM_START && $address <= self::ROM_END) {
                $this->rom[$address] = ord($data[$i]);
            }
            $address = ($address + 1) & 0xFFFF;
        }
    }

    /**
     * Parses load address from filename (e.g., program_8000.bin -> $8000).
     *
     * @param string $filename File path
     * @return int Load address (defaults to ROM_START)
     */
    private function parseLoadAddress(string $filename): int
    {
        $basename = basename($filename, '.bin');
        if (preg_match('/_([0-9A-Fa-f]{4})$/', $basename, $matches)) {
            return hexdec($matches[1]);
        }
        return self::ROM_START;
    }

    /**
     * Reads a byte from ROM.
     *
     * @param int $address Memory address
     * @return int Byte value (0-255), or 0 if uninitialized
     */
    public function readByte(int $address): int
    {
        return $this->rom[$address & 0xFFFF] ?? 0;
    }

    /**
     * Reads a block of bytes from ROM.
     *
     * @param int $address Starting address
     * @param int $length Number of bytes to read
     * @return array<int, int> Array of bytes
     */
    public function readBytes(int $address, int $length): array
    {
        $bytes = [];
        for ($i = 0; $i < $length; $i++) {
            $bytes[] = $this->readByte(($address + $i) & 0xFFFF);
        }
        return $bytes;
    }

    /**
     * Writes ROM contents to a binary file.
     *
     * @param string $file Output file path
     * @param int $start Starting address (default: ROM_START)
     * @param int $length Number of bytes (default: ROM_SIZE)
     * @throws \RuntimeException If file cannot be written
     */
    public function saveToFile(string $file, int $start = self::ROM_START, int $length = self::ROM_SIZE): void
    {
        $data = '';
        for ($i = 0; $i < $length; $i++) {
            $address = ($start + $i) & 0xFFFF;
            $data .= chr($this->rom[$address] ?? 0);
        }

        if (file_put_contents($file, $data) === false) {
            throw new \RuntimeException("Failed to write ROM file: $file");
        }
    }

    /**
     * Clears all ROM contents.
     */
    public function clear(): void
    {
        $this->rom = [];
    }
}
