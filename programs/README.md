# 6502 Assembly Programs

This directory contains 6502 assembly source code for the Ben Eater breadboard computer emulator.

## Directory Structure

```
programs/       - Assembly source files (.s, .asm)
roms/          - Compiled binary files (.bin, .out)
runbin.php     - Emulator runner (in project root)
```

## Building Programs

Use the included `vasm6502_oldstyle` assembler to compile programs:

```bash
./vasm6502_oldstyle -Fbin -dotdir programs/your_program.s -o roms/your_program.bin
```

### Example:
```bash
./vasm6502_oldstyle -Fbin -dotdir programs/blink.s -o roms/blink.bin
```

## Running Programs

Use the `runbin.php` script from the project root:

```bash
./runbin.php roms/blink.bin [clock_hz] [max_cycles]
```

### Clock Speed Control

The second parameter controls the emulation speed:

```bash
# Unlimited speed (as fast as possible)
./runbin.php roms/blink.bin

# 1 Hz - 1 instruction per second (very slow, great for demos)
./runbin.php roms/blink.bin 1

# 10 Hz - good for watching individual instructions
./runbin.php roms/blink.bin 10

# 100 Hz - medium speed demo
./runbin.php roms/blink.bin 100

# 1000 Hz (1 KHz) - fast but still visible
./runbin.php roms/blink.bin 1000

# 1 MHz - original 6502 speed
./runbin.php roms/blink.bin 1000000

# With max cycles limit (runs 5000 cycles at 100 Hz)
./runbin.php roms/blink.bin 100 5000
```

## Current Programs

### blink.s
Simple LED pattern that rotates bits on Port B.

**What it does:**
- Configures Port B as all outputs (DDRB = $FF)
- Loads pattern $50 (01010000)
- Continuously rotates right (ROR) and displays on Port B

**Expected output:**
```
  ○    ●    ○    ●    ○    ○    ○    ○    [0x50]
  ○    ○    ●    ○    ●    ○    ○    ○    [0x28]
  ○    ○    ○    ●    ○    ●    ○    ○    [0x14]
  ○    ○    ○    ○    ●    ○    ●    ○    [0x0A]
... and so on
```

## Writing New Programs

### Memory Map
- **RAM**: `$0000-$3FFF` (16KB)
- **VIA**: `$6000-$600F` (16 registers)
- **ROM**: `$8000-$FFFF` (32KB)

### VIA Registers (at $6000)
- `$6000` - Port B Output/Input Register
- `$6001` - Port A Output/Input Register
- `$6002` - Port B Data Direction Register (0=input, 1=output)
- `$6003` - Port A Data Direction Register
- `$6004` - Timer 1 Counter Low
- `$6005` - Timer 1 Counter High
- `$600B` - Auxiliary Control Register
- `$600D` - Interrupt Flag Register
- `$600E` - Interrupt Enable Register

### Basic Template

```assembly
; Your program description
  .org $8000

reset:
  ; Initialize stack
  ldx #$ff
  tsx

  ; Your initialization code here
  lda #$ff
  sta $6002      ; Set Port B as outputs

  ; Your main program
main_loop:
  ; Your code here
  jmp main_loop

; Reset and interrupt vectors
  .org $fffc
  .word reset    ; Reset vector
  .word $0000    ; IRQ vector (if using interrupts)
```

### Tips

1. **Always set data direction**: Write `$FF` to DDRB ($6002) before using Port B
2. **Use lowercase for vasm**: Instructions should be lowercase (lda, sta, etc.)
3. **Org is required**: Always use `.org $8000` at the start
4. **Set vectors**: Always include the reset vector at `.org $fffc`
5. **Test incrementally**: Start simple and add features gradually

## vasm Syntax

The included vasm uses "oldstyle" syntax:

- Instructions in lowercase: `lda`, `sta`, `jmp`
- Immediate values with `#`: `lda #$ff`
- Hexadecimal with `$`: `$6000`
- Decimal without prefix: `255`
- Binary with `%`: `%11111111`
- Directives with `.`: `.org`, `.word`, `.byte`

## Next Steps

Create more interesting programs:
1. Timer-based animations using Timer 1
2. Interrupt-driven programs
3. Binary counter displays
4. Pattern generators
5. Simple games or demos
