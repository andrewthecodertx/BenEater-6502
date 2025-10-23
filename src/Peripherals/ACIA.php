<?php

declare(strict_types=1);

namespace EaterEmulator\Peripherals;

use andrewthecoder\MOS6502\PeripheralInterface;

/**
 * W65C51 ACIA (Asynchronous Communications Interface Adapter) Emulation
 *
 * Emulates RS-232 serial communication via console I/O, matching Ben Eater's
 * breadboard computer design. Provides buffered input/output with status flags.
 *
 * Register Map (base address $5000):
 * - $5000: Data Register (read/write)
 * - $5001: Status Register (read) / Programmed Reset (write)
 * - $5002: Command Register (write)
 * - $5003: Control Register (write)
 */
class ACIA implements PeripheralInterface
{
    // Address space constants
    public const ACIA_START = 0x5000;
    public const ACIA_END = 0x5003;
    public const ACIA_SIZE = 4;

    // Register offsets
    private const REG_DATA = 0x0;      // Read: RX data, Write: TX data
    private const REG_STATUS = 0x1;    // Read: status, Write: reset
    private const REG_COMMAND = 0x2;   // Write: command register
    private const REG_CONTROL = 0x3;   // Write: control register

    // Status Register bits
    private const STATUS_IRQ = 0x80;       // Interrupt Request
    private const STATUS_DSR = 0x40;       // Data Set Ready (inverted)
    private const STATUS_DCD = 0x20;       // Data Carrier Detect
    private const STATUS_TDRE = 0x10;      // Transmit Data Register Empty
    private const STATUS_RDRF = 0x08;      // Receive Data Register Full
    private const STATUS_OVRN = 0x04;      // Overrun error
    private const STATUS_FE = 0x02;        // Framing Error
    private const STATUS_PE = 0x01;        // Parity Error

    private int $baseAddress;
    private int $dataRegister = 0x00;
    private int $statusRegister = self::STATUS_TDRE; // TX empty on startup
    private int $commandRegister = 0x00;
    private int $controlRegister = 0x00;

    /** @var array<int, int> */
    private array $receiveBuffer = [];
    private int $receiveBufferSize = 256;
    private bool $transmitReady = true;

    /**
     * Creates a new ACIA instance.
     *
     * @param int $baseAddress Base address for ACIA registers (default: $5000)
     */
    public function __construct(int $baseAddress = self::ACIA_START)
    {
        $this->baseAddress = $baseAddress & 0xFFFF;

        // Set up non-blocking input and raw mode for terminal
        if (php_sapi_name() === 'cli') {
            stream_set_blocking(STDIN, false);

            // Put terminal in raw mode (no line buffering, no echo) only if stdin is a TTY
            if (function_exists('system') && stripos(PHP_OS, 'WIN') === false && posix_isatty(STDIN)) {
                system('stty -icanon -echo');
            }
        }

        // Initialize status: TX ready, RX empty, DSR active (low = active, bit inverted)
        $this->statusRegister = self::STATUS_TDRE;
    }

    public function getStartAddress(): int
    {
        return $this->baseAddress;
    }

    public function getEndAddress(): int
    {
        return $this->baseAddress + (self::ACIA_SIZE - 1);
    }

    public function handlesAddress(int $address): bool
    {
        $address = $address & 0xFFFF;
        return $address >= $this->baseAddress && $address < ($this->baseAddress + self::ACIA_SIZE);
    }

    public function read(int $address): int
    {
        $address = $address & 0xFFFF;
        $offset = $address - $this->baseAddress;

        return match ($offset) {
            self::REG_DATA => $this->readData(),
            self::REG_STATUS => $this->readStatus(),
            default => 0x00,
        };
    }

    public function write(int $address, int $value): void
    {
        $address = $address & 0xFFFF;
        $value = $value & 0xFF;
        $offset = $address - $this->baseAddress;

        match ($offset) {
            self::REG_DATA => $this->writeData($value),
            self::REG_STATUS => $this->reset(),
            self::REG_COMMAND => $this->commandRegister = $value,
            self::REG_CONTROL => $this->controlRegister = $value,
            default => null,
        };
    }

    public function tick(): void
    {
        // Check for new input from console
        $this->pollInput();
    }

    public function hasInterruptRequest(): bool
    {
        // IRQ is triggered if enabled in command register and RX buffer has data
        $irqEnabled = ($this->commandRegister & 0x80) === 0; // Bit 7 = 0 enables IRQ on RX
        return $irqEnabled && !empty($this->receiveBuffer);
    }

    /**
     * Reads a byte from the receive data register.
     */
    private function readData(): int
    {
        if (!empty($this->receiveBuffer)) {
            $data = array_shift($this->receiveBuffer);

            // Update status: clear RDRF if buffer now empty
            if (empty($this->receiveBuffer)) {
                $this->statusRegister &= ~self::STATUS_RDRF;
            }

            return $data;
        }

        return 0x00;
    }

    /**
     * Writes a byte to the transmit data register (sends to console).
     */
    private function writeData(int $value): void
    {
        // Write character to console output
        $this->transmitCharacter($value);

        // Mark transmit register as empty (always ready in emulation)
        $this->statusRegister |= self::STATUS_TDRE;
        $this->transmitReady = true;
    }

    /**
     * Reads the status register.
     */
    private function readStatus(): int
    {
        return $this->statusRegister;
    }

    /**
     * Programmed reset of ACIA.
     */
    private function reset(): void
    {
        $this->statusRegister = self::STATUS_TDRE;
        $this->receiveBuffer = [];
        $this->transmitReady = true;
    }

    /**
     * Polls STDIN for new input and adds to receive buffer.
     */
    private function pollInput(): void
    {
        if (php_sapi_name() !== 'cli') {
            return;
        }

        $input = fread(STDIN, 1024);
        if ($input === false || $input === '') {
            return;
        }

        // Add characters to receive buffer
        for ($i = 0; $i < strlen($input); $i++) {
            if (count($this->receiveBuffer) < $this->receiveBufferSize) {
                $this->receiveBuffer[] = ord($input[$i]);
            } else {
                // Buffer overflow - set overrun error
                $this->statusRegister |= self::STATUS_OVRN;
            }
        }

        // Set RDRF flag if we have data
        if (!empty($this->receiveBuffer)) {
            $this->statusRegister |= self::STATUS_RDRF;
        }
    }

    /**
     * Transmits a character to console output.
     */
    private function transmitCharacter(int $character): void
    {
        $char = chr($character & 0x7F);

        // Handle special control characters
        switch ($character) {
            case 0x0A: // LF
                echo "\n";
                break;
            case 0x0D: // CR - output CR+LF for terminal newline
                echo "\r\n";
                break;
            case 0x08: // BS
                echo "\b";
                break;
            case 0x07: // BEL
                echo "\a";
                break;
            default:
                // Print if printable ASCII
                if ($character >= 0x20 && $character <= 0x7E) {
                    echo $char;
                }
                break;
        }

        flush();
    }

    /**
     * Resets the ACIA to power-on state.
     */
    public function resetACIA(): void
    {
        $this->reset();
    }

    /**
     * Restores terminal to normal mode (call on exit).
     */
    public static function restoreTerminal(): void
    {
        if (php_sapi_name() === 'cli' && stripos(PHP_OS, 'WIN') === false) {
            system('stty sane');
        }
    }
}
