// Mock localStorage for tests
const localStorageMock = (() => {
  let store = {}
  return {
    getItem: (key: string) => (store as any)[key] ?? null,
    setItem: (key: string, value: string) => { (store as any)[key] = String(value) },
    removeItem: (key: string) => { delete (store as any)[key] },
    clear: () => { store = {} },
    get length() { return Object.keys(store).length },
    key: (i: number) => Object.keys(store)[i] ?? null,
  }
})()

Object.defineProperty(window, 'localStorage', { value: localStorageMock })
Object.defineProperty(window, 'sessionStorage', { value: localStorageMock })
