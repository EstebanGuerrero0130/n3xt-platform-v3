/**
 * Trabajos de ejemplo (imágenes del proyecto) que se muestran mientras el panel
 * no tenga galería. `sample: true` evita enlazar a /galeria/:titulo, que solo
 * existe para trabajos reales guardados en el panel.
 */
export interface SampleWork {
  image: string
  title: string
  category: string
  technology: string
  subtitle?: string
  featured?: boolean
  sample: true
}

export const sampleGallery: SampleWork[] = [
  { image: '/assets/n3xt_social_solo_leveling_1778543107506.webp', title: 'Sung Jinwoo pintado a mano', category: 'Figura coleccionable', technology: 'Resina 8K', subtitle: 'Impresa en resina y pintada con aerógrafo.', sample: true },
  { image: '/assets/img/gallery2.webp', title: 'Maqueta arquitectónica', category: 'Arquitectura', technology: 'Resina', subtitle: 'Fachadas curvas impresas en una sola pieza.', sample: true },
  { image: '/assets/img/gallery3.webp', title: 'Celosía flexible', category: 'Prototipo', technology: 'TPU', subtitle: 'Estructura que se dobla sin romperse.', sample: true },
  { image: '/assets/n3xt_social_overwatch_prop_1778543121526.webp', title: 'Prop de cosplay', category: 'Cosplay', technology: 'PLA + aerografía', subtitle: 'Blaster a escala real en piezas encajables.', sample: true },
  { image: '/assets/img/gallery1.webp', title: 'Engranaje de repuesto', category: 'Repuesto', technology: 'PETG', subtitle: 'Repuesto funcional para maquinaria.', sample: true },
  { image: '/assets/img/hero_maker.webp', title: 'Figura en resina', category: 'Figura coleccionable', technology: 'Resina 8K', subtitle: 'Recién salida de la impresora, antes del curado.', sample: true },
  { image: '/assets/n3xt_social_eco_material_1778543149399.webp', title: 'Mecanismo de engranajes', category: 'Prototipo', technology: 'PLA reciclado', subtitle: 'Prueba de resistencia en material reciclado.', sample: true },
]
