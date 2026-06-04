/**
 * N3XT Logger — Centralized logging utility.
 *
 * - Suppresses debug logs (log, info) in production
 * - Adds [N3XT] prefix for easy filtering
 * - Preserves stack traces for warnings and errors
 */

const isDev: boolean = import.meta.env.DEV || window.location.hostname === 'localhost' || window.location.hostname === '127.0.0.1'

const noop = (..._args: any[]): void => {}

interface Logger {
  log: (...args: any[]) => void
  info: (...args: any[]) => void
  debug: (...args: any[]) => void
  warn: (...args: any[]) => void
  error: (...args: any[]) => void
}

const logger: Logger = {
  log: isDev ? (...args: any[]) => console.log('[N3XT]', ...args) : noop,
  info: isDev ? (...args: any[]) => console.info('[N3XT]', ...args) : noop,
  debug: isDev ? (...args: any[]) => console.debug('[N3XT]', ...args) : noop,
  warn: (...args: any[]) => console.warn('[N3XT]', ...args),
  error: (...args: any[]) => console.error('[N3XT]', ...args),
}

export default logger
