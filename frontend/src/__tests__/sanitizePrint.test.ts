import { describe, it, expect } from 'vitest'
import { sanitizePrintDocument } from '../utils/sanitize'

// Un nombre de cliente malicioso interpolado en un rótulo o cotización
const evilName = '<img src=x onerror="fetch(\'/api/admin/purge-all\')"><script>alert(1)</script>'
const doc = (body: string) => `<html><head><title>Rótulo</title><style>.a{color:red}</style></head><body>${body}</body></html>`

describe('sanitizePrintDocument', () => {
  it('quita scripts y atributos on* que vengan en los datos', () => {
    const out = sanitizePrintDocument(doc(`<div class="dest-name">${evilName}</div>`))
    expect(out).not.toMatch(/<script/i)
    expect(out).not.toMatch(/onerror/i)
    expect(out).toContain('class="dest-name"')
  })

  it('quita URLs javascript: aunque traigan espacios o mayúsculas', () => {
    const out = sanitizePrintDocument(doc('<a href=" JaVa\tScript:alert(1)">x</a><iframe src="https://evil"></iframe>'))
    expect(out).not.toMatch(/javascript/i)
    expect(out).not.toMatch(/<iframe/i)
  })

  it('conserva estilos, imágenes y texto del documento', () => {
    const out = sanitizePrintDocument(doc('<img src="https://cdn.example.com/logo.png" alt="Logo"><p>Pedido #12 · $120.000</p>'))
    expect(out).toContain('.a{color:red}')
    expect(out).toContain('src="https://cdn.example.com/logo.png"')
    expect(out).toContain('Pedido #12 · $120.000')
  })

  it('añade una CSP que bloquea cualquier script restante', () => {
    const out = sanitizePrintDocument(doc('<p>ok</p>'))
    expect(out).toMatch(/http-equiv="Content-Security-Policy" content="script-src 'none'/)
    expect(out.startsWith('<!DOCTYPE html>')).toBe(true)
  })
})
