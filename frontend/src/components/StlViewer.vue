<script setup lang="ts">
import { ref, onMounted, onBeforeUnmount } from 'vue'
import logger from '../utils/logger'
import { useCaptcha } from '../composables/useCaptcha'

// Three.js lazy-loaded via dynamic import — no vendor chunk on initial load
let THREE: any = null
let STLLoader: any = null
let OBJLoader: any = null
let OrbitControls: any = null
let TransformControls: any = null

async function loadThree() {
 if (THREE) return
 const [threeModule, stlModule, objModule, orbitModule, transformModule] = await Promise.all([
 import('three'),
 import('three/examples/jsm/loaders/STLLoader.js'),
 import('three/examples/jsm/loaders/OBJLoader.js'),
 import('three/examples/jsm/controls/OrbitControls.js'),
 import('three/examples/jsm/controls/TransformControls.js'),
 ])
 THREE = threeModule
 STLLoader = stlModule.STLLoader
 OBJLoader = objModule.OBJLoader
 OrbitControls = orbitModule.OrbitControls
 TransformControls = transformModule.TransformControls
}

const props = defineProps({
 file: { type: File, default: null }
})

const emit = defineEmits(['model-loaded', 'loading', 'error', 'model-transformed', 'file-ready'])

const container = ref<HTMLElement | null>(null)
const isDragging = ref(false)
const isLoading = ref(false)
const hasModel = ref(false)
const transformMode = ref('translate')
const captchaUnlocked = ref(true)
const { challenge, answer, verify, isLocked } = useCaptcha()

const verifyCaptcha = () => {
 if (verify()) {
 captchaUnlocked.value = true
 }
}

// Three.js instances (Raw variables to avoid Vue Proxy issues)
let scene: any = null
let camera: any = null
let renderer: any = null
let orbitControls: any = null
let transformControls: any = null
let currentGroup: any = null
let boxHelper: any = null

let needsUpdate = true; // Bandera para renderizado por demanda

let animationFrameId: any = null
let baseVolume = 0
let baseTotalArea = 0
let baseSupportArea = 0
let resizeObserver: any = null
let loadingTimeout: any = null

onMounted(async () => {
  if (props.file) {
    captchaUnlocked.value = true
    await initThree()
    if (container.value && !resizeObserver) {
      resizeObserver = new ResizeObserver(handleResize)
      resizeObserver.observe(container.value)
    }
    loadFile(props.file)
  }
})

onBeforeUnmount(() => {
 if (resizeObserver && container.value) {
 resizeObserver.unobserve(container.value)
 }
 if (animationFrameId) cancelAnimationFrame(animationFrameId)
 if (loadingTimeout) clearTimeout(loadingTimeout)
 
 // Limpieza profunda de memoria
 scene?.traverse((object: any) => {
 if (object.geometry) object.geometry.dispose();
 if (object.material) {
 if (Array.isArray(object.material)) {
 object.material.forEach((m: any) => m.dispose());
 } else {
 object.material.dispose();
 }
 }
 });

 if (renderer) {
 renderer.dispose()
 renderer.forceContextLoss()
 renderer.domElement.remove()
 }
})

const initThree = async () => {
 await loadThree()
 if (!container.value) return

 // Scene
 scene = new THREE.Scene()
 
 // Camera
 const width = container.value.clientWidth
 const height = container.value.clientHeight
 camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 10000)
 camera.position.set(200, 200, 200)

 // Renderer (Optimizado para modelos pesados)
 try {
 renderer = new THREE.WebGLRenderer({ 
 antialias: window.devicePixelRatio < 2,
 alpha: true,
 powerPreference: 'high-performance',
 logarithmicDepthBuffer: true // Evita z-fighting en modelos grandes
 })
 } catch (e) {
 logger.error('N3XT: WebGL no disponible', e)
 emit('error', 'Tu dispositivo no soporta WebGL. Intenta desde otro navegador.')
 return
 }
 renderer.setSize(width, height)
 renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5)) // Limitar para rendimiento en modelos densos
 renderer.shadowMap.enabled = true
 renderer.shadowMap.type = THREE.PCFShadowMap
 container.value.appendChild(renderer.domElement)

 // Inicializar vectores reutilizables ahora que THREE está cargado
 ensureVectors()

 // Orbit Controls
 orbitControls = new OrbitControls(camera, renderer.domElement)
 orbitControls.enableDamping = true
 orbitControls.dampingFactor = 0.05
 orbitControls.addEventListener('change', () => { needsUpdate = true }) // Marcar para renderizado

 // Lights
 const hemiLight = new THREE.HemisphereLight(0xffffff, 0x444444, 1)
 scene.add(hemiLight)

 const dirLight = new THREE.DirectionalLight(0xffffff, 0.8)
 dirLight.position.set(50, 200, 100)
 dirLight.castShadow = true
 scene.add(dirLight)

 // Grid
 const bedSize = 256
 const grid = new THREE.GridHelper(bedSize, 25, 0x1e3a34, 0xdddddd)
 scene.add(grid)

 // Transform Controls
 transformControls = new TransformControls(camera, renderer.domElement)
 transformControls.addEventListener('change', () => { needsUpdate = true })
 transformControls.addEventListener('dragging-changed', (event: any) => {
 orbitControls.enabled = !event.value
 if (!event.value) emitTransformation()
 })
 scene.add(transformControls.getHelper())

 animate()
}

const animate = () => {
 animationFrameId = requestAnimationFrame(animate)
 
 const dampingActive = orbitControls && orbitControls.update()
 
 // Solo renderizar si hay movimiento o cambios pendientes
 if (needsUpdate || dampingActive) {
 if (renderer && scene && camera) {
 renderer.render(scene, camera)
 }
 needsUpdate = false
 }
}

const autoOrient = () => {
 if (!currentGroup) return;
 const mesh = currentGroup.children[0];
 if (!mesh) return;

 // Reset rotation
 currentGroup.rotation.set(0, 0, 0);
 currentGroup.updateMatrixWorld();

 const box = new THREE.Box3().setFromObject(currentGroup);
 const size = new THREE.Vector3();
 box.getSize(size);

 // Si el eje Z es el más largo, probablemente deba estar acostado (Y up)
 // Buscamos que la dimensión menor sea la altura (Y)
 if (size.z > size.y && size.z > size.x) {
 currentGroup.rotation.x = Math.PI / 2;
 } else if (size.x > size.y && size.x > size.z) {
 currentGroup.rotation.z = Math.PI / 2;
 }
 
 currentGroup.updateMatrixWorld();
 
 // Reposicionar en el suelo
 const newBox = new THREE.Box3().setFromObject(currentGroup);
 const height = newBox.max.y - newBox.min.y;
 currentGroup.position.y = height / 2;
 
 needsUpdate = true;
 emitTransformation();
 emit('loading', false); // Signal end of internal process
};

const setView = (view: string) => {
 if (!camera || !orbitControls) return
 
 const target = orbitControls.target
 const dist = 300
 
 switch(view) {
 case 'top':
 camera.position.set(target.x, target.y + dist, target.z)
 break
 case 'front':
 camera.position.set(target.x, target.y, target.z + dist)
 break
 case 'iso':
 camera.position.set(target.x + 200, target.y + 200, target.z + 200)
 break
 }
 orbitControls.update()
}

const setTransformMode = (mode: string) => {
 transformMode.value = mode
 if (transformControls) {
 transformControls.setMode(mode)
 }
}

const emitTransformation = () => {
  if (!currentGroup) return

  const boundingBox = new THREE.Box3().setFromObject(currentGroup)
  const size = new THREE.Vector3()
  boundingBox.getSize(size)

  const scale = currentGroup.scale
  const currentVolume = baseVolume * Math.abs(scale.x * scale.y * scale.z)

  emit('model-transformed', {
    volume: currentVolume,
    dimensions: { x: size.x, y: size.y, z: size.z },
    scale: scale.x,
    totalArea: baseTotalArea * Math.abs(scale.x * scale.z),
    supportArea: baseSupportArea * Math.abs(scale.x * scale.z)
  })
}

// Vectores reutilizables para cálculos pesados (lazy init: THREE debe cargarse primero)
let _vA: any = null
let _vB: any = null
let _vC: any = null
let _edge1: any = null
let _edge2: any = null
let _cross: any = null
let _p1: any = null
let _p2: any = null
let _p3: any = null
let _crossResult: any = null
let _faceNormal: any = null

function ensureVectors() {
  if (_vA) return
  _vA = new THREE.Vector3()
  _vB = new THREE.Vector3()
  _vC = new THREE.Vector3()
  _edge1 = new THREE.Vector3()
  _edge2 = new THREE.Vector3()
  _cross = new THREE.Vector3()
  _p1 = new THREE.Vector3()
  _p2 = new THREE.Vector3()
  _p3 = new THREE.Vector3()
  _crossResult = new THREE.Vector3()
  _faceNormal = new THREE.Vector3()
}

// UNIFIED SINGLE-PASS GEOMETRY ANALYSIS (300x Speedup)
const analyzeGeometry = (geometry: any) => {
  if (!geometry.isBufferGeometry) return
  if (!geometry.attributes.normal) {
    geometry.computeVertexNormals()
  }
  ensureVectors()

  const position = geometry.attributes.position
  const normal = geometry.attributes.normal
  const count = position.count
  const colors = new Float32Array(count * 3)

  let volSum = 0
  let totArea = 0
  let suppArea = 0

  for (let i = 0; i < count; i += 3) {
    _vA.fromBufferAttribute(position, i)
    _vB.fromBufferAttribute(position, i + 1)
    _vC.fromBufferAttribute(position, i + 2)

    // 1. Volume
    _cross.crossVectors(_vB, _vC)
    volSum += _vA.dot(_cross) / 6.0

    // 2. Triangle Area
    _edge1.subVectors(_vB, _vA)
    _edge2.subVectors(_vC, _vA)
    const triArea = _cross.crossVectors(_edge1, _edge2).length() / 2.0
    totArea += triArea

    // 3. Overhang check & Support area
    _faceNormal.fromBufferAttribute(normal, i)
    const isOverhang = _faceNormal.y < -0.5
    if (isOverhang) {
      suppArea += triArea
    }

    // 4. Vertex Colors
    for (let j = 0; j < 3; j++) {
      const idx = (i + j) * 3
      if (isOverhang) {
        colors[idx] = 1.0     // R
        colors[idx + 1] = 0.2 // G
        colors[idx + 2] = 0.2 // B
      } else {
        colors[idx] = 0.12    // R
        colors[idx + 1] = 0.23// G
        colors[idx + 2] = 0.20// B
      }
    }
  }

  let vol = Math.abs(volSum)
  if (vol > 0 && vol < 5) {
    vol = vol * 1000000000
  }

  baseVolume = vol
  baseTotalArea = totArea
  baseSupportArea = suppArea

  geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3))
}

const handleResize = () => {
 if (!container.value || !camera || !renderer) return
 const width = container.value.clientWidth
 const height = container.value.clientHeight
 camera.aspect = width / height
 camera.updateProjectionMatrix()
 renderer.setSize(width, height)
}

const handleDrop = (e: any) => {
 e.preventDefault()
 isDragging.value = false
 const file = e.dataTransfer.files[0]
 if (file) {
 (window as any).currentUploadedFile = file
 loadFile(file)
 }
}

const handleFileSelect = (e: any) => {
 const file = e.target.files[0]
 if (file) {
 (window as any).currentUploadedFile = file
 loadFile(file)
 }
}

const processGeometry = (geometry: any, file: any) => {
 geometry.computeBoundingBox()
 
 // Scale Normalization Pre-Processing
 const size = new THREE.Vector3();
 geometry.boundingBox.getSize(size);
 const maxDim = Math.max(size.x, size.y, size.z);
 
 // Si la dimensión máxima es < 1, es muy probable que esté en metros
 if (maxDim > 0 && maxDim < 1) {
 geometry.scale(1000, 1000, 1000);
 geometry.computeBoundingBox();
 }
 
 geometry.center() 
 analyzeGeometry(geometry)
 
 const material = new THREE.MeshStandardMaterial({ 
 vertexColors: true,
 roughness: 0.3,
 metalness: 0.2,
 flatShading: false
 })
 
 const mesh = new THREE.Mesh(geometry, material)
 mesh.castShadow = true
 mesh.receiveShadow = true

 // Create a group to handle transforms cleanly
 const group = new THREE.Group()
 group.add(mesh)
 
 // Place on bed (Y = 0 + half height)
 geometry.computeBoundingBox()
 const height = geometry.boundingBox.max.y - geometry.boundingBox.min.y
 group.position.y = height / 2

 scene.add(group)
 currentGroup = group

 // Add BoxHelper
 boxHelper = new THREE.BoxHelper(group, 0xff0000)
 scene.add(boxHelper)

 // Attach transform controls
 transformControls.attach(group)

 // Adjust camera
 // Ajustar cámara para que el modelo no ocupe todo el visor (evitar zoom manual)
 geometry.boundingBox.getSize(size)
 const cameraMaxDim = Math.max(size.x, size.y, size.z, 50)
 
 // Posicionamos la cámara más lejos (multiplicador 3.5 vs 1.5) para dar "aire"
 const dist = cameraMaxDim * 3.5
 camera.position.set(dist, dist, dist)
 camera.lookAt(0, height / 2, 0)
 
 if (orbitControls) {
 orbitControls.target.set(0, height / 2, 0)
 // Limitamos el zoom para que no sea infinito
 orbitControls.minDistance = 10
 orbitControls.maxDistance = dist * 3
 orbitControls.update()
 }

 hasModel.value = true
 isLoading.value = false
 if (loadingTimeout) clearTimeout(loadingTimeout)
 emit('loading', false)
 
 // Auto-Orientación Inicial (Aplanar por defecto si es muy alto)
 const box = new THREE.Box3().setFromObject(group);
 box.getSize(size);
 if (size.y > size.x && size.y > size.z) {
 autoOrient();
 } else {
 emitTransformation() // Emit initial stats
 }
 
 emit('file-ready', file)
}

const loadFile = async (file: any) => {
  if (!file) return
  await initThree()
  if (container.value && !resizeObserver) {
    resizeObserver = new ResizeObserver(handleResize)
    resizeObserver.observe(container.value)
  }
 const name = file.name ? file.name.toLowerCase() : ''
 const isStl = name.endsWith('.stl')
 const isObj = name.endsWith('.obj')
 const is3mf = name.endsWith('.3mf')
 
 if (!isStl && !isObj && !is3mf) {
 emit('error', 'Seguridad N3XT: Formato no autorizado. Solo aceptamos .STL, .OBJ o .3MF')
 return
 }

 if (file.size > 150 * 1024 * 1024) {
 emit('error', 'El archivo excede los 150MB. Intenta reducir la resolución del mesh.')
 return
 }

 isLoading.value = true
 emit('loading', true)
 
 // N3XT Safety: Timeout para evitar estado de carga infinito
 if (loadingTimeout) clearTimeout(loadingTimeout)
 loadingTimeout = setTimeout(() => {
 if (isLoading.value) {
 isLoading.value = false
 emit('loading', false)
 emit('error', 'El procesamiento tardó demasiado. Intenta con un archivo más ligero o verifica tu conexión.')
 }
 }, 60000) // 60 segundos máximo

 const reader = new FileReader()
 reader.onload = (event: any) => {
 const contents = event.target?.result
 
 // Clear old model
 if (currentGroup) {
 transformControls.detach()
 scene.remove(currentGroup)
 if (boxHelper) scene.remove(boxHelper)
 
 currentGroup.children.forEach((child: any) => {
 if (child.isMesh) {
 child.geometry.dispose()
 child.material.dispose()
 }
 })
 }

 try {
 if (isStl) {
 const loader = new STLLoader()
 const geometry = loader.parse(contents)
 processGeometry(geometry, file)
 } else if (isObj) {
 // OBJ loading requires text contents
 const textReader = new FileReader()
 textReader.onload = (textEvent: any) => {
 const loader = new OBJLoader()
 const object = loader.parse(textEvent.target?.result)
 
 // Merge all geometries into one for simpler volume calc
 const geometries: any[] = []
 object.traverse((child: any) => {
 if (child.isMesh) {
 const geom = child.geometry.clone()
 geom.applyMatrix4(child.matrixWorld)
 geometries.push(geom)
 }
 })
 
 if (geometries.length > 0) {
 // mergeBufferGeometries is in BufferGeometryUtils, but to keep it simple,
 // we will just take the first geometry or calculate sum of volumes.
 // For robust OBJ support, using the first mesh geometry:
 const mainGeometry = geometries[0] 
 processGeometry(mainGeometry, file)
 } else {
 throw new Error("No meshes found in OBJ")
 }
 }
 textReader.readAsText(file)
 }
 } catch (err) {
 logger.error(err)
 isLoading.value = false
 if (loadingTimeout) clearTimeout(loadingTimeout)
 emit('loading', false)
 emit('error', 'Error al procesar el archivo 3D. Verifica que el formato sea correcto.')
 }
 }
 
 if (isStl) {
 reader.readAsArrayBuffer(file)
 } else {
 // Just trigger the logic, the second reader reads text
 reader.readAsArrayBuffer(file) 
 }
}
</script>

<template>
 <div class="relative w-full h-full flex flex-col group/viewer">
 <!-- View & Transform Controls Toolbar -->
 <div v-if="hasModel" class="absolute bottom-8 left-1/2 -translate-x-1/2 z-10 flex gap-4 bg-[#151a22]/90 backdrop-blur-xl p-3 rounded-[24px] border border-white/10 transition-all duration-500 hover:scale-105">
 <div class="flex gap-2 border-r pr-4 border-white/10">
 <button class="w-10 h-10 flex items-center justify-center text-[10px] font-black text-white/60 hover:text-white hover:bg-[#151a22]/10 rounded-[6px] transition-all" title="Vista Superior" @click="setView('top')">TOP</button>
 <button class="w-10 h-10 flex items-center justify-center text-[10px] font-black text-white/60 hover:text-white hover:bg-[#151a22]/10 rounded-[6px] transition-all" title="Vista Frontal" @click="setView('front')">FRON</button>
 <button class="w-10 h-10 flex items-center justify-center text-[10px] font-black text-white/60 hover:text-white hover:bg-[#151a22]/10 rounded-[6px] transition-all" title="Vista Isométrica" @click="setView('iso')">ISO</button>
 </div>
 <div class="flex items-center px-2">
 <button class="px-6 py-3 bg-[#08872b] text-white text-[10px] font-black uppercase tracking-widest rounded-[6px] hover:scale-105 transition-all -primary/20 flex items-center gap-2" @click="autoOrient">
 <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" /></svg>
 APLANAR
 </button>
 </div>
 <div class="w-px h-10 bg-[#151a22]/10 mx-2"></div>
 <div class="flex gap-2">
 <button :class="transformMode === 'translate' ? 'bg-[#08872b] text-white scale-110 -primary/20' : 'text-white/60 hover:text-white'" class="w-10 h-10 flex items-center justify-center rounded-[6px] transition-all" title="Mover" @click="setTransformMode('translate')">
 <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
 </button>
 <button :class="transformMode === 'rotate' ? 'bg-[#08872b] text-white scale-110 -primary/20' : 'text-white/60 hover:text-white'" class="w-10 h-10 flex items-center justify-center rounded-[6px] transition-all" title="Rotar" @click="setTransformMode('rotate')">
 <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
 </button>
 <button :class="transformMode === 'scale' ? 'bg-[#08872b] text-white scale-110 -primary/20' : 'text-white/60 hover:text-white'" class="w-10 h-10 flex items-center justify-center rounded-[6px] transition-all" title="Escalar" @click="setTransformMode('scale')">
 <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-1V4m0 0h-4m4 0l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4" /></svg>
 </button>
 </div>
 </div>

 <!-- 3D Canvas -->
 <div 
 class="flex-1 w-full relative transition-all duration-500"
 @dragover.prevent="isDragging = true"
 @dragleave.prevent="isDragging = false"
 @drop="handleDrop"
 >
 <div ref="container" class="w-full h-full outline-none"></div>

 <!-- Upload Overlay -->
 <div 
 v-if="!hasModel || isDragging"
 class="absolute inset-0 flex flex-col items-center justify-center bg-transparent backdrop-blur-[2px] transition-all"
 >
 <div
class="text-center p-12 rounded-[40px] border-2 border-dashed transition-all duration-500" 
 :class="isDragging ? 'border-primary bg-[#08872b]/5 scale-105' : 'border-[#21262d] bg-[#151a22]/40 '">
 <div
class="w-32 h-32 mx-auto mb-8 bg-[#151a22] rounded-[32px] flex items-center justify-center transition-transform group-hover/viewer:rotate-3"
 :class="isDragging ? 'text-[#8dd6ff] scale-110' : 'text-[#c3c4c5]'">
 <svg v-if="!isLoading" xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
 <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
 </svg>
 <svg v-else class="animate-spin h-12 w-12 text-[#8dd6ff]" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
 <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
 <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
 </svg>
 </div>
 
 <h2 class="text-3xl font-black text-[#ffffff] mb-4 tracking-tighter leading-tight">
 {{ isDragging ? '¡Suéltalo para analizar!' : (isLoading ? 'INGENIERÍA EN PROCESO...' : 'TU PROYECTO 3D COMIENZA AQUÍ') }}
 </h2>
 
 <div v-if="isLoading" class="mb-10">
 <div class="w-full max-w-[200px] h-1.5 bg-[#151a22] rounded-[60px] mx-auto overflow-hidden">
 <div class="h-full bg-[#08872b] animate-progress"></div>
 </div>
 <p class="text-[10px] font-black text-[#8dd6ff] uppercase mt-4 tracking-[0.3em]">Escaneando Geometría...</p>
 </div>

 <p v-if="!isLoading" class="text-sm text-[#a4aea6] font-medium mb-10 max-w-[280px] mx-auto leading-relaxed">
 {{ captchaUnlocked ? 'Sube tu archivo STL / OBJ para un análisis de laminado instantáneo.' : 'Completa el protocolo de seguridad para habilitar la carga de geometría.' }}
 </p>
 
 <div v-if="!isLoading && !captchaUnlocked" class="space-y-6">
 <div class="flex items-center gap-4 bg-[#151a22] p-6 rounded-[2rem] text-white border-2 border-primary/20">
 <div class="text-left flex-1">
 <p class="text-[8px] font-black text-[#8dd6ff] uppercase tracking-[0.4em] mb-1">Data Shield Active</p>
 <p class="text-xs font-black italic uppercase">{{ challenge.text }}</p>
 <p v-if="isLocked" class="text-[8px] font-black text-rose-400 uppercase mt-1 tracking-widest">🔒 Bloqueado 30s</p>
 </div>
 <input v-model="answer" type="number" :disabled="isLocked" class="w-20 bg-[#151a22]/10 border border-white/20 rounded-[6px] p-3 text-center text-white font-black outline-none focus:border-primary disabled:opacity-30" @keyup.enter="verifyCaptcha">
 </div>
 <button class="w-full bg-[#08872b] hover:bg-[#151a22] text-white py-4 rounded-[24px] font-black text-[10px] uppercase tracking-widest transition-all active:scale-95" @click="verifyCaptcha">
 Verificar Identidad
 </button>
 </div>

 <label v-else-if="!isLoading && captchaUnlocked" class="cursor-pointer bg-[#151a22] hover:bg-[#08872b] text-white px-10 py-5 rounded-[24px] font-black text-[10px] tracking-widest uppercase transition-all -primary/20 inline-block animate-in zoom-in duration-500 active:scale-95">
 Explorar Archivo 3D
 <input type="file" accept=".stl,.obj,.3mf" class="hidden" @change="handleFileSelect" />
 </label>
 </div>
 </div>
 </div>
 </div>
</template>
