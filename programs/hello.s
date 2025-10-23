; Hello World via 65C51 ACIA
; Demonstrates RS-232 serial output at $5000-$5003

ACIA_DATA    = $5000
ACIA_STATUS  = $5001
ACIA_COMMAND = $5002
ACIA_CONTROL = $5003

  .org $8000

reset:
  ; Initialize ACIA
  lda #$00
  sta ACIA_STATUS    ; Soft reset
  lda #$0B           ; No parity, no echo, no interrupts
  sta ACIA_COMMAND
  lda #$1F           ; 1 stop bit, 8 data bits, 19200 baud
  sta ACIA_CONTROL

  ; Print "Hello, World!" message
  ldx #$00
print_loop:
  lda message,x
  beq done           ; If zero, we're done
  jsr print_char
  inx
  jmp print_loop

done:
  jmp done           ; Infinite loop

; Print character in A register
print_char:
  pha                ; Save A
wait_tx:
  lda ACIA_STATUS
  and #$10           ; Check TDRE (Transmit Data Register Empty)
  beq wait_tx        ; Wait until ready
  pla                ; Restore A
  sta ACIA_DATA      ; Send character
  rts

message:
  .byte "Hello, World!", $0D, $0A, $00

; Reset vector
  .org $fffc
  .word reset
  .word $0000
