/**
 * Textos fijos de la Home. Son el punto de partida: el admin los puede cambiar
 * en Sitio web > Inicio (setting web.home) y la Home usa lo que haya guardado.
 */
export interface HomeHighlight { val: string; label: string }
export interface HomeStep { t: string; d: string }
export interface HomePrintLine { t: string; d: string; tags: string[]; i: string; alt: string }
export interface HomeFinishing { n: string; d: string }

export const HERO_PILL = 'Estudio de manufactura digital'

export const DEFAULT_HIGHLIGHTS: HomeHighlight[] = [
  { val: '8K', label: 'Resolución en resina' },
  { val: '24 h', label: 'Prototipado rápido' },
  { val: '100 %', label: 'Acabado a mano' },
]

export const DEFAULT_PROCESS_STEPS: HomeStep[] = [
  { t: 'Envíanos tu idea', d: 'Una imagen, un boceto o un archivo 3D. Si aún no tienes el diseño, te ayudamos a desarrollarlo.' },
  { t: 'Te asesoramos', d: 'Revisamos el proyecto y te recomendamos material, tamaño y tecnología para tu pieza.' },
  { t: 'Recibe tu cotización', d: 'Un precio claro según complejidad, material, acabado y tiempo de fabricación.' },
  { t: 'Fabricamos y entregamos', d: 'Imprimimos con equipos profesionales, damos el acabado y te enviamos la pieza.' },
]

export const DEFAULT_PRINT_LINES: HomePrintLine[] = [
  { t: 'Figuras coleccionables', d: 'Personajes y miniaturas en resina de alta resolución, listos para exhibir o pintados a mano.', tags: ['Resina SLA', 'Aerografía', 'Detalle 8K'], i: '/assets/img/hero_maker.webp', alt: 'Figura recién impresa en una impresora de resina' },
  { t: 'Impresión multimaterial', d: 'Una granja de impresoras de resina y filamento: elegimos la máquina y el material según lo que tu pieza necesita.', tags: ['Bambu Lab', 'Elegoo', 'Creality', 'Anycubic'], i: '/assets/n3xt_social_bambu_fleet_1778543135730.webp', alt: 'Granja de impresoras 3D de filamento trabajando en paralelo' },
  { t: 'Prototipos funcionales', d: 'Carcasas, engranajes, soportes y repuestos para probar un diseño antes de producirlo en serie.', tags: ['Ingeniería', 'Nylon / PETG', 'Pruebas de resistencia'], i: '/assets/n3xt_social_eco_material_1778543149399.webp', alt: 'Mecanismo de engranajes impreso en filamento verde' },
]

export const DEFAULT_FINISHING_STEPS: HomeFinishing[] = [
  { n: 'Curado UV', d: 'La resina termina de endurecer bajo luz ultravioleta: la pieza gana resistencia y pierde el tacto pegajoso.' },
  { n: 'Lijado fino', d: 'Retiramos soportes y marcas de capa a mano, de grano grueso a fino, hasta dejar la superficie lisa.' },
  { n: 'Imprimación', d: 'Una base uniforme que revela los últimos defectos y deja la pieza lista para pintar.' },
  { n: 'Aerografía', d: 'Color, sombras y luces a mano, capa por capa, como en la referencia que nos envíes.' },
]
