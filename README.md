# Ben Eater 6502 Breadboard Computer Emulator

A PHP-based emulator for the
[Ben Eater 6502 breadboard computer](https://eater.net/6502), built on top of
the [65C02 Emulator](https://github.com/andrewthecodertx/65C02-Emulator)
core.

This project emulates the hardware components of Ben Eater's breadboard design
including RAM, ROM, and the W65C22 VIA (Versatile Interface Adapter) with LED
output visualization.

## Features

- **65C02 CPU Core** - Full 6502 instruction set via the
andrewthecoder/65C02-Emulator package
- **16KB RAM** ($0000-$3FFF)
- **32KB ROM** ($8000-$FFFF)
- **W65C22 VIA** - Two 8-bit I/O ports with LED visualization
- **Live LED Display** - Visual representation of Port A and Port B outputs
- **Adjustable Clock Speed** - Currently runs at 1 KHz (configurable)

## Requirements

- PHP 8.1 or higher
- Composer

## Installation

1. Clone this repository:

```bash
git clone https://github.com/yourusername/eater-emulator.git
cd eater-emulator
```

1. Install dependencies:

```bash
composer install
```

## Quick Start

### Running the Demo

The project includes a simple LED blink program. Run it with:

```bash
php runbin.php roms/blink.bin
```

You'll see the VIA Port B LEDs update in real-time:

```
B:  ○   ●   ○   ●   ○   ○   ○   ○   [0x50]  Cycle: 100
```

Press `Ctrl+C` to stop the emulator.

## Writing Your Own Programs

### Example: Alternating Pattern

Create a new file `programs/pattern.s`:

```assembly
; Alternating LED pattern demo
; Toggles between two patterns on Port B

  .org $8000

reset:
  ; Set Port B data direction to all outputs
  lda #$ff
  sta $6002      ; DDRB at $6002

  ; Initialize pattern
  lda #$AA       ; Pattern: 10101010

loop:
  sta $6000      ; Write to Port B

  ; Toggle pattern (AA <-> 55)
  eor #$FF       ; Flip all bits

  jmp loop

; 6502 Reset vector
  .org $fffc
  .word reset
  .word $0000
```

### Building and Running

1. **Build the program:**

```bash
php buildasm.php programs/pattern.s roms/pattern.bin
```

2. **Run the emulator:**

For **LED programs** (using VIA):
```bash
php runbin.php roms/pattern.bin
```

For **console programs** (using ACIA serial):
```bash
php runconsole.php roms/hello.bin
```

You should see Port B LEDs alternating between patterns `$AA` (10101010) and
`$55` (01010101) when using `runbin.php`, or text output when using `runconsole.php`.

## Hardware Programming Reference

### Complete Memory Map

| Address Range | Device | Description |
|---------------|--------|-------------|
| `$0000-$3FFF` | RAM    | 16KB system RAM |
| `$5000-$5003` | ACIA   | W65C51 Serial interface (RS-232) |
| `$6000-$600F` | VIA    | W65C22 I/O and timers |
| `$8000-$FFFF` | ROM    | 32KB program ROM |

### ACIA (W65C51) - Serial Console at $5000

The ACIA provides RS-232 serial communication for terminal I/O, matching Ben Eater's design.

| Address | Register | Access | Description |
|---------|----------|--------|-------------|
| `$5000` | Data     | R/W    | Receive/Transmit data register |
| `$5001` | Status   | R      | Status register |
| `$5001` | Reset    | W      | Programmed reset |
| `$5002` | Command  | W      | Command register |
| `$5003` | Control  | W      | Control register (baud, format) |

**Status Register Bits:**
- Bit 7: IRQ - Interrupt request
- Bit 4: TDRE - Transmit Data Register Empty (1 = ready to send)
- Bit 3: RDRF - Receive Data Register Full (1 = character received)

**Common ACIA Initialization:**
```assembly
  lda #$00
  sta $5001      ; Reset ACIA
  lda #$0B
  sta $5002      ; No parity, no echo, no interrupts
  lda #$1F
  sta $5003      ; 1 stop bit, 8 data bits, 19200 baud
```

### VIA (W65C22) - I/O and Timers at $6000

| Address | Register | Description |
|---------|----------|-------------|
| `$6000` | ORB/IRB  | Port B Output/Input Register |
| `$6001` | ORA/IRA  | Port A Output/Input Register |
| `$6002` | DDRB     | Port B Data Direction (0=input, 1=output) |
| `$6003` | DDRA     | Port A Data Direction |
| `$6004` | T1C-L    | Timer 1 Counter Low Byte |
| `$6005` | T1C-H    | Timer 1 Counter High Byte |
| `$600B` | ACR      | Auxiliary Control Register |
| `$600D` | IFR      | Interrupt Flag Register |
| `$600E` | IER      | Interrupt Enable Register |

### Basic I/O Pattern

All programs should follow this pattern:

1. **Set data direction** - Write `$FF` to DDRB ($6002) for output
2. **Write data** - Write values to Port B ($6000)
3. **Loop** - Create an infinite loop for continuous operation

## Example Programs

### Serial Console Programs

#### Hello World (using ACIA)

```assembly
ACIA_DATA   = $5000
ACIA_STATUS = $5001

  .org $8000

reset:
  lda #$00
  sta ACIA_STATUS    ; Reset ACIA

  ldx #$00
print:
  lda message,x
  beq done
  jsr print_char
  inx
  jmp print

done:
  jmp done

print_char:
  pha
wait:
  lda ACIA_STATUS
  and #$10           ; Check TX ready
  beq wait
  pla
  sta ACIA_DATA      ; Send character
  rts

message:
  .byte "Hello, World!", $0D, $0A, $00

  .org $fffc
  .word reset
  .word $0000
```

See `programs/hello.s` and `programs/echo.s` for complete examples.

### LED/VIA Programs

#### Counting Binary

```assembly
  .org $8000

reset:
  lda #$ff
  sta $6002      ; Port B = output
  lda #$00       ; Start at 0

loop:
  sta $6000      ; Display on Port B
  clc
  adc #$01       ; Increment
  jmp loop

  .org $fffc
  .word reset
  .word $0000
```

### Walking LED

```assembly
  .org $8000

reset:
  lda #$ff
  sta $6002      ; Port B = output
  lda #$01       ; Start with rightmost LED

loop:
  sta $6000      ; Display pattern
  asl            ; Shift left
  bne loop       ; Continue until overflow
  lda #$01       ; Reset to start
  jmp loop

  .org $fffc
  .word reset
  .word $0000
```

## Project Structure

```
.
├── bin/
│   └── vasm6502_oldstyle    # 6502 assembler
├── programs/
│   ├── blink.s              # LED blink demo (VIA)
│   ├── hello.s              # Hello World (ACIA serial)
│   ├── echo.s               # Echo program (ACIA serial)
│   └── README.md            # Assembly programming guide
├── roms/
│   ├── blink.bin            # Assembled LED demo
│   └── hello.bin            # Assembled Hello World
├── src/
│   ├── RAM.php              # 16KB RAM implementation
│   ├── ROM.php              # 32KB ROM implementation
│   ├── SystemBus.php        # Memory bus coordination
│   └── Peripherals/
│       ├── VIA.php          # W65C22 VIA emulation (I/O, timers)
│       └── ACIA.php         # W65C51 ACIA emulation (serial)
├── buildasm.php             # Assembly build utility
├── runbin.php               # LED emulator runner (VIA output)
├── runconsole.php           # Console emulator runner (ACIA serial)
└── composer.json            # Dependencies
```

## Choosing the Right Runner

- **runbin.php** - For programs using VIA Port A/B LEDs (`$6000-$600F`)
  - Visual LED display
  - Good for debugging hardware I/O
  - Examples: `blink.bin`, LED patterns, counters

- **runconsole.php** - For programs using ACIA serial console (`$5000-$5003`)
  - Text-based I/O via terminal
  - Interactive programs (echo, monitors, etc.)
  - Examples: `hello.bin`, `echo.bin`
  - Matches Ben Eater's RS-232 serial design

## Development

### Build Tool

The `buildasm.php` utility wraps the vasm assembler:

```bash
php buildasm.php <source.s> [output.bin]
```

If no output file is specified, it uses the source filename with `.bin` extension.

### Emulator Options

The emulator currently runs at 1 KHz and stops after 20,000 cycles.
These can be adjusted in `runbin.php`:

```php
$clockHz = 1000;           // Clock speed in Hz
while ($cycleCount < 20000) // Max cycles
```

## Resources

- [Ben Eater's 6502 Video Series](https://eater.net/6502)
- [65C02 Emulator Core](https://github.com/andrewthecodertx/65C02-Emulator)
- [W65C22 VIA Datasheet](https://www.westerndesigncenter.com/wdc/documentation/w65c22.pdf)
- [6502 Instruction Reference](http://www.6502.org/tutorials/6502opcodes.html)

## Credits

- CPU Core: [andrewthecoder/65C02-Emulator](https://github.com/andrewthecodertx/65C02-Emulator)
- Hardware Design: [Ben Eater](https://eater.net)
- Assembler: [vasm 6502 oldstyle](http://sun.hasenbraten.de/vasm/)

## License

MIT License - See LICENSE file for details
