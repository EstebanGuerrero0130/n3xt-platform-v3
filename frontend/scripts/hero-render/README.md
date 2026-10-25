# Render del hero (esfera voronoi impresa)

Escena three.js que genera `public/assets/hero/n3xt_hero_voronoi*.webp`.

1. Servir esta carpeta con Vite (`npx vite scripts/hero-render`) y abrir
   `index.html?w=2400&h=1500&d=2&j=0.2&r=0.08&c=%231a201e&z=7.6`.
2. Capturar el canvas (captura de pantalla del navegador a ese tamaño) y exportar a WebP.

Parámetros: `d` subdivisión de la icoesfera (tamaño de celda), `j` irregularidad de las celdas,
`r` grosor del filamento, `c` color del material, `z` distancia de cámara, `lf` frecuencia de las
líneas de capa.
