<?php

declare(strict_types=1);

namespace EaterEmulator;

/**
 * Random Access Memory (16KB, $0000-$3FFF).
 *
 * Provides read/write storage with address validation.
 * Uninitialized addresses return 0.
 */
class RAM
{
    public const RAM_START = 0x0000;
    public const RAM_END = 0x3FFF;

    /** @var array<int, int> */
    private array $ram = [];

    /**
     * Creates a new RAM instance.
     */
    public function __construct()
    {
    }

    /**
     * Reads a byte from RAM.
     *
     * @param int $address Memory address
     * @return int Byte value (0-255), or 0 if uninitialized
     * @throws \OutOfBoundsException If address is outside RAM space
     */
    public function readByte(int $address): int
    {
        $address = $address & 0xFFFF;

        if ($address < self::RAM_START || $address > self::RAM_END) {
            throw new \OutOfBoundsException(
                sprintf(
                    'RAM address $%04X outside valid range ($%04X-$%04X)',
                    $address,
                    self::RAM_START,
                    self::RAM_END
                )
            );
        }

        return $this->ram[$address] ?? 0;
    }

    /**
     * Writes a byte to RAM.
     *
     * @param int $address Memory address
     * @param int $value Byte value to write (0-255)
     * @throws \OutOfBoundsException If address is outside RAM space
     */
    public function writeByte(int $address, int $value): void
    {
        $address = $address & 0xFFFF;
        $value = $value & 0xFF;

        if ($address < self::RAM_START || $address > self::RAM_END) {
            throw new \OutOfBoundsException(
                sprintf(
                    'RAM address $%04X outside valid range ($%04X-$%04X)',
                    $address,
                    self::RAM_START,
                    self::RAM_END
                )
            );
        }

        $this->ram[$address] = $value;
    }

    /**
     * Reads a block of bytes from RAM.
     *
     * @param int $address Starting address
     * @param int $length Number of bytes to read
     * @return array<int, int> Array of bytes
     * @throws \OutOfBoundsException If any address is outside RAM space
     */
    public function readBytes(int $address, int $length): array
    {
        $bytes = [];
        for ($i = 0; $i < $length; $i++) {
            $bytes[] = $this->readByte($address + $i);
        }
        return $bytes;
    }

    /**
     * Writes a block of bytes to RAM.
     *
     * @param int $address Starting address
     * @param array<int, int> $bytes Array of byte values
     * @throws \OutOfBoundsException If any address is outside RAM space
     */
    public function writeBytes(int $address, array $bytes): void
    {
        foreach ($bytes as $i => $byte) {
            $this->writeByte($address + $i, $byte);
        }
    }

    /**
     * Checks if an address is within RAM space.
     *
     * @param int $address Memory address
     * @return bool True if address is valid for RAM
     */
    public function isValidAddress(int $address): bool
    {
        $address = $address & 0xFFFF;
        return $address >= self::RAM_START && $address <= self::RAM_END;
    }

    /**
     * Clears all RAM contents.
     */
    public function clear(): void
    {
        $this->ram = [];
    }

    /**
     * Fills a range of RAM with a specific byte value.
     *
     * @param int $start Starting address
     * @param int $length Number of bytes to fill
     * @param int $value Byte value to fill with (default: 0)
     * @throws \OutOfBoundsException If any address is outside RAM space
     */
    public function fill(int $start, int $length, int $value = 0): void
    {
        $value = $value & 0xFF;
        for ($i = 0; $i < $length; $i++) {
            $this->writeByte($start + $i, $value);
        }
    }
}
