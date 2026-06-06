import { ref, onMounted, onUnmounted } from 'vue'
import gsap from 'gsap'

export function useParticles(options: {
  count?: number
  colors?: string[]
  minSize?: number
  maxSize?: number
  zIndex?: number
} = {}) {
  const {
    count = 60,
    colors = [
      'rgba(16, 185, 129, 0.6)',   // emerald
      'rgba(20, 184, 166, 0.5)',   // teal
      'rgba(6, 182, 212, 0.4)',    // cyan
      'rgba(255, 255, 255, 0.5)',  // white
      'rgba(251, 191, 36, 0.4)',   // amber/gold
      'rgba(52, 211, 153, 0.5)',   // emerald light
    ],
    minSize = 3,
    maxSize = 12,
  } = options

  const particlesRef = ref<HTMLElement | null>(null)
  let gsapTweens: gsap.core.Tween[] = []
  let observers: IntersectionObserver[] = []

  const createParticles = () => {
    if (!particlesRef.value) return
    const container = particlesRef.value

    // Crear partículas normales (80%) + estrellas brillantes (20%)
    const normalCount = Math.floor(count * 0.75)
    const starCount = count - normalCount

    // --- Partículas normales flotantes ---
    for (let i = 0; i < normalCount; i++) {
      const particle = document.createElement('div')
      const size = gsap.utils.random(minSize, maxSize)
      const color = colors[Math.floor(Math.random() * colors.length)]

      particle.style.cssText = `
        position: absolute;
        width: ${size}px;
        height: ${size}px;
        left: ${gsap.utils.random(0, 100)}%;
        top: ${gsap.utils.random(0, 100)}%;
        border-radius: ${gsap.utils.random(0, 1) > 0.5 ? '50%' : '2px'};
        background: ${color};
        box-shadow: 0 0 ${gsap.utils.random(4, 12)}px ${color};
        opacity: ${gsap.utils.random(0.3, 0.8)};
        pointer-events: none;
        will-change: transform, opacity;
      `
      container.appendChild(particle)

      // Movimiento de deriva lenta con flotación orgánica
      const xDrift = gsap.utils.random(-60, 60)
      const yDrift = gsap.utils.random(-80, -20)
      const duration = gsap.utils.random(4, 10)
      const delay = gsap.utils.random(0, 5)

      const tween = gsap.to(particle, {
        x: xDrift,
        y: yDrift,
        rotation: gsap.utils.random(-180, 180),
        scale: gsap.utils.random(0.5, 1.5),
        opacity: 0,
        duration: duration,
        delay: delay,
        repeat: -1,
        ease: 'sine.inOut',
        onRepeat: () => {
          gsap.set(particle, {
            x: 0,
            y: 0,
            rotation: 0,
            scale: 1,
            left: gsap.utils.random(0, 100) + '%',
            top: gsap.utils.random(0, 100) + '%',
            opacity: gsap.utils.random(0.3, 0.8),
          })
        },
      })
      gsapTweens.push(tween)
    }

    // --- Estrellas brillantes (más grandes, con pulse) ---
    for (let i = 0; i < starCount; i++) {
      const star = document.createElement('div')
      const size = gsap.utils.random(8, 20)
      const color = colors[Math.floor(Math.random() * colors.length)]

      // Forma de estrella: rotada 45° o diamante
      star.innerHTML = `
        <svg viewBox="0 0 24 24" fill="none" style="width:${size}px;height:${size}px;display:block;">
          <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" 
                fill="${color.replace('0.4', '0.6').replace('0.5', '0.7').replace('0.6', '0.8')}" 
                stroke="${color}" 
                stroke-width="0.5"
                opacity="${gsap.utils.random(0.5, 0.9)}"/>
        </svg>
      `
      star.style.cssText = `
        position: absolute;
        left: ${gsap.utils.random(0, 100)}%;
        top: ${gsap.utils.random(0, 100)}%;
        pointer-events: none;
        will-change: transform, opacity;
        filter: drop-shadow(0 0 ${gsap.utils.random(4, 15)}px ${color});
      `
      container.appendChild(star)

      // Animación: pulse + rotación lenta + deriva
      const pulseDuration = gsap.utils.random(1.5, 3)
      const driftDuration = gsap.utils.random(6, 12)
      const delay = gsap.utils.random(0, 4)

      // Pulse (brillo)
      const pulse = gsap.to(star, {
        scale: gsap.utils.random(1.1, 1.4),
        opacity: gsap.utils.random(0.3, 0.6),
        duration: pulseDuration,
        delay: delay,
        repeat: -1,
        yoyo: true,
        ease: 'sine.inOut',
      })
      gsapTweens.push(pulse)

      // Deriva lenta
      const drift = gsap.to(star, {
        x: gsap.utils.random(-40, 40),
        y: gsap.utils.random(-60, -15),
        rotation: gsap.utils.random(-90, 90),
        duration: driftDuration,
        delay: delay,
        repeat: -1,
        ease: 'sine.inOut',
        onRepeat: () => {
          gsap.set(star, {
            x: 0,
            y: 0,
            rotation: 0,
            left: gsap.utils.random(0, 100) + '%',
            top: gsap.utils.random(0, 100) + '%',
          })
        },
      })
      gsapTweens.push(drift)
    }
  }

  const destroyParticles = () => {
    gsapTweens.forEach(t => t.kill())
    gsapTweens = []
    if (particlesRef.value) {
      particlesRef.value.innerHTML = ''
    }
  }

  // Pausar partículas cuando no son visibles (ahorra CPU)
  const observeVisibility = () => {
    if (!particlesRef.value) return
    const observer = new IntersectionObserver(
      ([entry]) => {
        gsapTweens.forEach(t => {
          t.paused(!entry.isIntersecting)
        })
      },
      { threshold: 0 }
    )
    observer.observe(particlesRef.value)
    observers.push(observer)
  }

  // Cleanup on unmount
  onMounted(() => {
    createParticles()
    observeVisibility()
  })

  onUnmounted(() => {
    destroyParticles()
    observers.forEach(o => o.disconnect())
    observers = []
  })

  return {
    particlesRef,
  }
}
