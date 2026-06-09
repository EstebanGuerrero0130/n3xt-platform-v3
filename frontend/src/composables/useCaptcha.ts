/**
 * N3XT useCaptcha Composable
 * Unified CAPTCHA verification with multiple operation types and brute-force protection.
 *
 * Operations: addition (+), subtraction (−), multiplication (×)
 * Lockout: after maxAttempts failures, locks for lockoutDuration ms
 */

import { ref, type Ref } from 'vue'

type Operation = '+' | '−' | '×'

export interface CaptchaChallenge {
  a: number
  b: number
  operation: Operation
  result: number
  text: string
}

export function useCaptcha() {
  const challenge: Ref<CaptchaChallenge> = ref({ a: 0, b: 0, operation: '+', result: 0, text: '' })
  const answer: Ref<number | string> = ref('')
  const attempts = ref(0)
  const isLocked = ref(false)
  const lockedUntil = ref(0)
  const maxAttempts = 3
  const lockoutDuration = 30000 // 30 seconds

  function generateChallenge(): void {
    const operations: Operation[] = ['+', '−', '×']
    const op = operations[Math.floor(Math.random() * operations.length)]

    let a: number, b: number, result: number

    switch (op) {
      case '+': {
        a = Math.floor(Math.random() * 50) + 5
        b = Math.floor(Math.random() * 50) + 5
        result = a + b
        break
      }
      case '−': {
        a = Math.floor(Math.random() * 70) + 15
        b = Math.floor(Math.random() * (a - 1)) + 1
        result = a - b
        break
      }
      case '×': {
        a = Math.floor(Math.random() * 12) + 2
        b = Math.floor(Math.random() * 10) + 2
        result = a * b
        break
      }
    }

    challenge.value = {
      a, b, operation: op, result,
      text: `¿Cuánto es ${a} ${op} ${b}?`
    }
    answer.value = ''
  }

  function verify(): boolean {
    const numAnswer = parseInt(String(answer.value), 10)
    if (isNaN(numAnswer)) {
      attempts.value++
      if (attempts.value >= maxAttempts) lock()
      else generateChallenge()
      return false
    }

    if (numAnswer === challenge.value.result) {
      attempts.value = 0
      return true
    }

    attempts.value++
    if (attempts.value >= maxAttempts) {
      lock()
    } else {
      generateChallenge()
    }
    return false
  }

  function lock(): void {
    isLocked.value = true
    lockedUntil.value = Date.now() + lockoutDuration
    setTimeout(() => {
      isLocked.value = false
      attempts.value = 0
      generateChallenge()
    }, lockoutDuration)
  }

  function reset(): void {
    attempts.value = 0
    isLocked.value = false
    lockedUntil.value = 0
    generateChallenge()
  }

  // Initialize on creation
  generateChallenge()

  return {
    challenge,
    answer,
    attempts,
    maxAttempts,
    isLocked,
    lockedUntil,
    generateChallenge,
    verify,
    reset,
  }
}
