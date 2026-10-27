import { describe, it, expect } from 'vitest'
import { fitsIn, fitNotice, toBuildMm, partialBuildVolume } from '../utils/buildVolume'

const capacity = { FDM: { x: 256, y: 256, z: 256 }, SLA: { x: 218, y: 123, z: 250 } }

describe('fitsIn', () => {
  it('acepta la pieza girada', () => {
    // 240 × 100 × 120 no cabe tal cual en 218 × 123 × 250, pero sí acostada
    expect(fitsIn({ x: 240, y: 100, z: 120 }, capacity.SLA)).toBe(true)
  })
  it('rechaza si algún lado no cabe en ninguna orientación', () => {
    expect(fitsIn({ x: 130, y: 130, z: 130 }, capacity.SLA)).toBe(false)
    expect(fitsIn({ x: 300, y: 10, z: 10 }, capacity.FDM)).toBe(false)
  })
  it('el borde exacto cabe', () => {
    expect(fitsIn({ x: 256, y: 256, z: 256 }, capacity.FDM)).toBe(true)
  })
})

describe('fitNotice', () => {
  it('avisa en resina con la opción de filamento', () => {
    expect(fitNotice({ x: 300.4, y: 120, z: 80 }, 'SLA', capacity))
      .toBe('Esta pieza mide 300 × 120 × 80 mm y no cabe en nuestras máquinas de resina; la imprimiremos en partes o con filamento')
  })
  it('en filamento solo ofrece partes', () => {
    expect(fitNotice({ x: 1200, y: 10, z: 10 }, 'fdm', capacity)).toBe('Esta pieza mide 1.200 × 10 × 10 mm y no cabe en nuestras máquinas de filamento; la imprimiremos en partes')
  })
  it('sin aviso si cabe, sin medidas o sin capacidad', () => {
    expect(fitNotice({ x: 100, y: 100, z: 100 }, 'SLA', capacity)).toBeNull()
    expect(fitNotice({ x: 0, y: 0, z: 0 }, 'SLA', capacity)).toBeNull()
    expect(fitNotice({ x: 900, y: 900, z: 900 }, 'SLA', null)).toBeNull()
  })
})

describe('toBuildMm', () => {
  it('vacío es null y el resto se acota a enteros válidos', () => {
    expect(toBuildMm('')).toBeNull()
    expect(toBuildMm(null)).toBeNull()
    expect(toBuildMm('220.4')).toBe(220)
    expect(toBuildMm(0)).toBeNull()
    expect(toBuildMm(2001)).toBeNull()
  })
})

describe('partialBuildVolume', () => {
  it('avisa solo con una o dos medidas', () => {
    expect(partialBuildVolume('', '', '')).toBe(false)
    expect(partialBuildVolume(256, '', '')).toBe(true)
    expect(partialBuildVolume(256, 256, null)).toBe(true)
    expect(partialBuildVolume(256, 256, 256)).toBe(false)
  })
})
