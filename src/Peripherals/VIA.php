<?php

declare(strict_types=1);

namespace EaterEmulator\Peripherals;

use andrewthecoder\Core\PeripheralInterface;

/**
 * W65C22 Versatile Interface Adapter (VIA) emulation.
 *
 * Provides two 8-bit bidirectional I/O ports, two 16-bit timers/counters,
 * shift register for serial I/O, and interrupt management. Memory-mapped
 * to 16 consecutive addresses ($6000-$600F by default).
 *
 * Features:
 * - Port A and Port B with individual data direction control
 * - Timer 1: 16-bit interval timer with one-shot and free-run modes
 * - Timer 2: 16-bit interval timer and pulse counter
 * - 8-bit shift register for serial data transfer
 * - Interrupt flag and enable registers
 * - Auxiliary and peripheral control registers
 */
class VIA implements PeripheralInterface
{
    // Address space constants
    public const VIA_START = 0x6000;
    public const VIA_END = 0x600F;

    // I/O Port Registers
    /** @var int Output Register B / Input Register B (ORB/IRB) */
    private int $orb = 0x00;
    /** @var int Output Register A / Input Register A (ORA/IRA) */
    private int $ora = 0x00;
    /** @var int Data Direction Register B (0=input, 1=output) */
    private int $ddrb = 0x00;
    /** @var int Data Direction Register A (0=input, 1=output) */
    private int $ddra = 0x00;

    // Timer 1 Registers
    /** @var int Timer 1 Counter Low Byte */
    private int $t1cL = 0x00;
    /** @var int Timer 1 Counter High Byte */
    private int $t1cH = 0x00;
    /** @var int Timer 1 Latch Low Byte */
    private int $t1lL = 0x00;
    /** @var int Timer 1 Latch High Byte */
    private int $t1lH = 0x00;
    /** @var int Timer 1 16-bit counter */
    private int $t1Counter = 0x0000;
    /** @var bool Timer 1 interrupt flag */
    private bool $t1Interrupt = false;

    // Timer 2 Registers
    /** @var int Timer 2 Counter Low Byte */
    private int $t2cL = 0x00;
    /** @var int Timer 2 Counter High Byte */
    private int $t2cH = 0x00;
    /** @var int Timer 2 16-bit counter */
    private int $t2Counter = 0x0000;
    /** @var bool Timer 2 interrupt flag */
    private bool $t2Interrupt = false;

    // Shift Register
    /** @var int Shift Register */
    private int $sr = 0x00;

    // Control Registers
    /** @var int Auxiliary Control Register */
    private int $acr = 0x00;
    /** @var int Peripheral Control Register */
    private int $pcr = 0x00;
    /** @var int Interrupt Flag Register */
    private int $ifr = 0x00;
    /** @var int Interrupt Enable Register */
    private int $ier = 0x00;
    private int $viaSize;


    // Register offsets
    private const REG_ORB_IRB = 0x0;
    private const REG_ORA_IRA = 0x1;
    private const REG_DDRB = 0x2;
    private const REG_DDRA = 0x3;
    private const REG_T1C_L = 0x4;
    private const REG_T1C_H = 0x5;
    private const REG_T1L_L = 0x6;
    private const REG_T1L_H = 0x7;
    private const REG_T2C_L = 0x8;
    private const REG_T2C_H = 0x9;
    private const REG_SR = 0xA;
    private const REG_ACR = 0xB;
    private const REG_PCR = 0xC;
    private const REG_IFR = 0xD;
    private const REG_IER = 0xE;
    private const REG_ORA_NH = 0xF;

    // Interrupt Flag Register (IFR) bits
    private const IFR_CA2 = 0x01;
    private const IFR_CA1 = 0x02;
    private const IFR_SR = 0x04;
    private const IFR_CB2 = 0x08;
    private const IFR_CB1 = 0x10;
    private const IFR_T2 = 0x20;
    private const IFR_T1 = 0x40;
    private const IFR_IRQ = 0x80;

    // Auxiliary Control Register (ACR) bits
    private const ACR_PA_LATCH = 0x01;
    private const ACR_PB_LATCH = 0x02;
    private const ACR_SR_MASK = 0x1C;
    private const ACR_T2_CONTROL = 0x20;
    private const ACR_T1_CONTROL_MASK = 0xC0;
    private const ACR_T1_PB7_DISABLE = 0x00;
    private const ACR_T1_PB7_ONE_SHOT = 0x80;
    private const ACR_T1_PB7_FREE_RUN = 0xC0;

    /**
     * Creates a new VIA instance.
     *
     * @param int $baseAddress Base address for VIA registers (default: $6000)
     */
    public function __construct(
        /** @var int Base address of VIA registers */
        private int $baseAddress = self::VIA_START,
    ) {
        $this->baseAddress = $baseAddress & 0xFFFF;
        $this->viaSize = (self::VIA_END - $this->baseAddress) & 0xFFFF;
        $this->reset();
    }

    /**
     * Resets the VIA to power-on state.
     */
    public function reset(): void
    {
        // Reset I/O ports
        $this->orb = 0x00;
        $this->ora = 0x00;
        $this->ddrb = 0x00;
        $this->ddra = 0x00;

        // Reset timers
        $this->t1cL = 0x00;
        $this->t1cH = 0x00;
        $this->t1lL = 0x00;
        $this->t1lH = 0x00;
        $this->t1Counter = 0x0000;
        $this->t1Interrupt = false;

        $this->t2cL = 0x00;
        $this->t2cH = 0x00;
        $this->t2Counter = 0x0000;
        $this->t2Interrupt = false;

        // Reset shift register
        $this->sr = 0x00;

        // Reset control registers
        $this->acr = 0x00;
        $this->pcr = 0x00;
        $this->ifr = 0x00;
        $this->ier = 0x00;
    }

    public function getStartAddress(): int
    {
        return $this->baseAddress;
    }

    public function getEndAddress(): int
    {
        return self::VIA_END;
    }

    public function getViaSize(): int
    {
        return self::VIA_END - $this->baseAddress;
    }

    public function handlesAddress(int $address): bool
    {
        $address = $address & 0xFFFF;
        return $address >= $this->baseAddress && $address < ($this->baseAddress + $this->viaSize);
    }

    public function read(int $address): int
    {
        $address = $address & 0xFFFF;
        $offset = $address - $this->baseAddress;

        return match ($offset) {
            self::REG_ORB_IRB => $this->readPortB(),
            self::REG_ORA_IRA => $this->readPortA(true),
            self::REG_DDRB => $this->ddrb,
            self::REG_DDRA => $this->ddra,
            self::REG_T1C_L => $this->readT1CL(),
            self::REG_T1C_H => $this->readT1CH(),
            self::REG_T1L_L => $this->t1lL,
            self::REG_T1L_H => $this->t1lH,
            self::REG_T2C_L => $this->readT2CL(),
            self::REG_T2C_H => $this->readT2CH(),
            self::REG_SR => $this->sr,
            self::REG_ACR => $this->acr,
            self::REG_PCR => $this->pcr,
            self::REG_IFR => $this->readIFR(),
            self::REG_IER => $this->ier | 0x80,
            self::REG_ORA_NH => $this->readPortA(false),
            default => 0x00,
        };
    }

    public function write(int $address, int $value): void
    {
        $address = $address & 0xFFFF;
        $value = $value & 0xFF;
        $offset = $address - $this->baseAddress;

        match ($offset) {
            self::REG_ORB_IRB => $this->writePortB($value),
            self::REG_ORA_IRA => $this->writePortA($value, true),
            self::REG_DDRB => $this->ddrb = $value,
            self::REG_DDRA => $this->ddra = $value,
            self::REG_T1C_L => $this->writeT1CL($value),
            self::REG_T1C_H => $this->writeT1CH($value),
            self::REG_T1L_L => $this->t1lL = $value,
            self::REG_T1L_H => $this->writeT1LH($value),
            self::REG_T2C_L => $this->t2cL = $value,
            self::REG_T2C_H => $this->writeT2CH($value),
            self::REG_SR => $this->sr = $value,
            self::REG_ACR => $this->acr = $value,
            self::REG_PCR => $this->pcr = $value,
            self::REG_IFR => $this->writeIFR($value),
            self::REG_IER => $this->writeIER($value),
            self::REG_ORA_NH => $this->writePortA($value, false),
            default => null,
        };
    }

    public function tick(): void
    {
        // Update Timer 1
        $this->tickTimer1();

        // Update Timer 2
        $this->tickTimer2();

        // Update interrupt flag register
        $this->updateIFR();
    }

    public function hasInterruptRequest(): bool
    {
        // IRQ is active if any enabled interrupt is flagged
        return ($this->ifr & 0x80) !== 0;
    }

    /**
     * Reads from Port B, applying data direction mask.
     */
    private function readPortB(): int
    {
        // Return output bits where DDRB=1, input bits where DDRB=0
        // For now, input bits read as 0 (no external input connected)
        return $this->orb & $this->ddrb;
    }

    /**
     * Reads from Port A, applying data direction mask.
     *
     * @param bool $clearHandshake Whether to clear CA1/CA2 interrupt flags
     */
    private function readPortA(bool $clearHandshake): int
    {
        if ($clearHandshake) {
            // Clear CA1 and CA2 interrupt flags on read
            $this->ifr &= ~(self::IFR_CA1 | self::IFR_CA2);
        }

        // Return output bits where DDRA=1, input bits where DDRA=0
        // For now, input bits read as 0 (no external input connected)
        return $this->ora & $this->ddra;
    }

    /**
     * Writes to Port B.
     */
    private function writePortB(int $value): void
    {
        // Clear CB1 and CB2 interrupt flags on write
        $this->ifr &= ~(self::IFR_CB1 | self::IFR_CB2);

        // Only update bits configured as outputs
        $this->orb = ($this->orb & ~$this->ddrb) | ($value & $this->ddrb);
    }

    /**
     * Writes to Port A.
     *
     * @param int $value Value to write
     * @param bool $clearHandshake Whether to clear CA1/CA2 interrupt flags
     */
    private function writePortA(int $value, bool $clearHandshake): void
    {
        if ($clearHandshake) {
            // Clear CA1 and CA2 interrupt flags on write
            $this->ifr &= ~(self::IFR_CA1 | self::IFR_CA2);
        }

        // Only update bits configured as outputs
        $this->ora = ($this->ora & ~$this->ddra) | ($value & $this->ddra);
    }

    /**
     * Reads Timer 1 Counter Low Byte and clears T1 interrupt flag.
     */
    private function readT1CL(): int
    {
        $this->t1Interrupt = false;
        $this->ifr &= ~self::IFR_T1;
        return $this->t1Counter & 0xFF;
    }

    /**
     * Reads Timer 1 Counter High Byte.
     */
    private function readT1CH(): int
    {
        return ($this->t1Counter >> 8) & 0xFF;
    }

    /**
     * Writes Timer 1 Counter Low Byte (to latch).
     */
    private function writeT1CL(int $value): void
    {
        $this->t1lL = $value;
    }

    /**
     * Writes Timer 1 Counter High Byte and initiates countdown.
     */
    private function writeT1CH(int $value): void
    {
        $this->t1lH = $value;
        $this->t1Counter = ($this->t1lH << 8) | $this->t1lL;
        $this->t1Interrupt = false;
        $this->ifr &= ~self::IFR_T1;
    }

    /**
     * Writes Timer 1 Latch High Byte.
     */
    private function writeT1LH(int $value): void
    {
        $this->t1lH = $value;
        $this->t1Interrupt = false;
        $this->ifr &= ~self::IFR_T1;
    }

    /**
     * Reads Timer 2 Counter Low Byte and clears T2 interrupt flag.
     */
    private function readT2CL(): int
    {
        $this->t2Interrupt = false;
        $this->ifr &= ~self::IFR_T2;
        return $this->t2Counter & 0xFF;
    }

    /**
     * Reads Timer 2 Counter High Byte.
     */
    private function readT2CH(): int
    {
        return ($this->t2Counter >> 8) & 0xFF;
    }

    /**
     * Writes Timer 2 Counter High Byte and initiates countdown.
     */
    private function writeT2CH(int $value): void
    {
        $this->t2cH = $value;
        $this->t2Counter = ($this->t2cH << 8) | $this->t2cL;
        $this->t2Interrupt = false;
        $this->ifr &= ~self::IFR_T2;
    }

    /**
     * Reads Interrupt Flag Register with IRQ summary bit.
     */
    private function readIFR(): int
    {
        return $this->ifr;
    }

    /**
     * Writes to Interrupt Flag Register to clear specific flags.
     */
    private function writeIFR(int $value): void
    {
        // Writing 1 to a bit clears that interrupt flag
        $this->ifr &= ~($value & 0x7F);
        $this->updateIFR();
    }

    /**
     * Writes to Interrupt Enable Register.
     */
    private function writeIER(int $value): void
    {
        if (($value & 0x80) !== 0) {
            // Bit 7 = 1: Set enabled interrupts
            $this->ier |= ($value & 0x7F);
        } else {
            // Bit 7 = 0: Clear enabled interrupts
            $this->ier &= ~($value & 0x7F);
        }
        $this->updateIFR();
    }

    /**
     * Updates Timer 1 on each CPU cycle.
     */
    private function tickTimer1(): void
    {
        if ($this->t1Counter > 0) {
            $this->t1Counter = ($this->t1Counter - 1) & 0xFFFF;

            if ($this->t1Counter === 0) {
                // Timer underflowed
                $this->t1Interrupt = true;

                // Check if free-run mode (ACR bit 6 set)
                if (($this->acr & 0x40) !== 0) {
                    // Free-run mode: reload from latch
                    $this->t1Counter = ($this->t1lH << 8) | $this->t1lL;
                }
            }
        }
    }

    /**
     * Updates Timer 2 on each CPU cycle.
     */
    private function tickTimer2(): void
    {
        // Check if Timer 2 is in interval timer mode (ACR bit 5 = 0)
        if (($this->acr & self::ACR_T2_CONTROL) === 0) {
            if ($this->t2Counter > 0) {
                $this->t2Counter = ($this->t2Counter - 1) & 0xFFFF;

                if ($this->t2Counter === 0) {
                    // Timer underflowed (one-shot mode only)
                    $this->t2Interrupt = true;
                }
            }
        }
        // Pulse counting mode (ACR bit 5 = 1) not implemented yet
    }

    /**
     * Updates the Interrupt Flag Register IRQ summary bit.
     */
    private function updateIFR(): void
    {
        // Set individual timer interrupt flags
        if ($this->t1Interrupt) {
            $this->ifr |= self::IFR_T1;
        }
        if ($this->t2Interrupt) {
            $this->ifr |= self::IFR_T2;
        }

        // Calculate IRQ summary bit (bit 7)
        // IRQ is set if any enabled interrupt is flagged
        $activeInterrupts = $this->ifr & $this->ier & 0x7F;
        if ($activeInterrupts !== 0) {
            $this->ifr |= self::IFR_IRQ;
        } else {
            $this->ifr &= ~self::IFR_IRQ;
        }
    }

    /**
     * Sets an external input value for Port A.
     *
     * @param int $value Input value (0x00-0xFF)
     */
    public function setPortAInput(int $value): void
    {
        $value = $value & 0xFF;
        // Update input bits (where DDRA=0), preserve output bits (where DDRA=1)
        $this->ora = ($this->ora & $this->ddra) | ($value & ~$this->ddra);
    }

    /**
     * Sets an external input value for Port B.
     *
     * @param int $value Input value (0x00-0xFF)
     */
    public function setPortBInput(int $value): void
    {
        $value = $value & 0xFF;
        // Update input bits (where DDRB=0), preserve output bits (where DDRB=1)
        $this->orb = ($this->orb & $this->ddrb) | ($value & ~$this->ddrb);
    }

    /**
     * Gets the current output value of Port A.
     */
    public function getPortAOutput(): int
    {
        return $this->ora & $this->ddra;
    }

    /**
     * Gets the current output value of Port B.
     */
    public function getPortBOutput(): int
    {
        return $this->orb & $this->ddrb;
    }
}
