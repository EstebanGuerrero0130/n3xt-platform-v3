<script setup>
import { ref, onMounted, onBeforeUnmount, shallowRef } from 'vue'
import * as THREE from 'three'
import { STLLoader } from 'three/examples/jsm/loaders/STLLoader.js'
import { OBJLoader } from 'three/examples/jsm/loaders/OBJLoader.js'
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js'
import { TransformControls } from 'three/examples/jsm/controls/TransformControls.js'

const emit = defineEmits(['model-loaded', 'loading', 'error', 'model-transformed', 'file-ready'])

const container = ref(null)
const isDragging = ref(false)
const isLoading = ref(false)
const hasModel = ref(false)
const transformMode = ref('translate')
const captchaUnlocked = ref(false)
const captchaA = ref(0)
const captchaB = ref(0)
const captchaResult = ref(0)
const captchaAnswer = ref('')

const generateCaptcha = () => {
  captchaA.value = Math.floor(Math.random() * 10) + 1
  captchaB.value = Math.floor(Math.random() * 10) + 1
  captchaResult.value = captchaA.value + captchaB.value
  captchaAnswer.value = ''
}

const verifyCaptcha = () => {
  if (parseInt(captchaAnswer.value) === captchaResult.value) {
    captchaUnlocked.value = true
  } else {
    generateCaptcha()
  }
}

// Three.js instances (Raw variables to avoid Vue Proxy issues)
let scene = null
let camera = null
let renderer = null
let orbitControls = null
let transformControls = null
let currentGroup = null
let boxHelper = null

let needsUpdate = true; // Bandera para renderizado por demanda

let animationFrameId = null
let baseVolume = 0
let resizeObserver = null

onMounted(() => {
  generateCaptcha()
  initThree()
  if (container.value) {
    resizeObserver = new ResizeObserver(handleResize)
    resizeObserver.observe(container.value)
  }
})

onBeforeUnmount(() => {
  if (resizeObserver && container.value) {
    resizeObserver.unobserve(container.value)
  }
  if (animationFrameId) cancelAnimationFrame(animationFrameId)
  
  // Limpieza profunda de memoria
  scene?.traverse((object) => {
    if (object.geometry) object.geometry.dispose();
    if (object.material) {
      if (Array.isArray(object.material)) {
        object.material.forEach(m => m.dispose());
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

const initThree = () => {
  if (!container.value) return

  // Scene
  scene = new THREE.Scene()
  
  // Camera
  const width = container.value.clientWidth
  const height = container.value.clientHeight
  camera = new THREE.PerspectiveCamera(45, width / height, 0.1, 10000)
  camera.position.set(200, 200, 200)

  // Renderer (Optimizado para modelos pesados)
  renderer = new THREE.WebGLRenderer({ 
    antialias: window.devicePixelRatio < 2,
    alpha: true,
    powerPreference: 'high-performance',
    logarithmicDepthBuffer: true // Evita z-fighting en modelos grandes
  })
  renderer.setSize(width, height)
  renderer.setPixelRatio(Math.min(window.devicePixelRatio, 1.5)) // Limitar para rendimiento en modelos densos
  renderer.shadowMap.enabled = true
  renderer.shadowMap.type = THREE.PCFSoftShadowMap
  container.value.appendChild(renderer.domElement)

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
  transformControls.addEventListener('dragging-changed', (event) => {
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

const setView = (view) => {
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

const setTransformMode = (mode) => {
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

  // CALCULAR ÁREA TOTAL PARA CURAENGINE
  const totalArea = calculateTotalArea(currentGroup.children[0].geometry)
  const supportArea = calculateSupportArea(currentGroup.children[0].geometry)
  
  emit('model-transformed', {
    volume: currentVolume,
    dimensions: { x: size.x, y: size.y, z: size.z },
    scale: scale.x,
    totalArea: totalArea * Math.abs(scale.x * scale.z), // Approximation for area scale
    supportArea: supportArea * Math.abs(scale.x * scale.z)
  })
}

// Vectores reutilizables para cálculos pesados (evita GC thrashing)
const _vA = new THREE.Vector3()
const _vB = new THREE.Vector3()
const _vC = new THREE.Vector3()
const _edge1 = new THREE.Vector3()
const _edge2 = new THREE.Vector3()
const _cross = new THREE.Vector3()

const calculateTotalArea = (geometry) => {
  if (!geometry.attributes.position) return 0
  const position = geometry.attributes.position
  let area = 0
  for (let i = 0; i < position.count; i += 3) {
    _vA.fromBufferAttribute(position, i)
    _vB.fromBufferAttribute(position, i + 1)
    _vC.fromBufferAttribute(position, i + 2)
    _edge1.subVectors(_vB, _vA)
    _edge2.subVectors(_vC, _vA)
    area += _cross.crossVectors(_edge1, _edge2).length() / 2
  }
  return area
}


const handleResize = () => {
  if (!container.value || !camera || !renderer) return
  const width = container.value.clientWidth
  const height = container.value.clientHeight
  camera.aspect = width / height
  camera.updateProjectionMatrix()
  renderer.setSize(width, height)
}

const calculateVolume = (geometry) => {
  if (!geometry.isBufferGeometry) return 0
  const position = geometry.attributes.position
  const faces = position.count / 3
  let sum = 0
  // Reutilizar vectores para modelos con millones de triángulos
  const p1 = new THREE.Vector3()
  const p2 = new THREE.Vector3()
  const p3 = new THREE.Vector3()
  const crossResult = new THREE.Vector3()
  for (let i = 0; i < faces; i++) {
    p1.fromBufferAttribute(position, i * 3 + 0)
    p2.fromBufferAttribute(position, i * 3 + 1)
    p3.fromBufferAttribute(position, i * 3 + 2)
    crossResult.crossVectors(p2, p3)
    sum += p1.dot(crossResult) / 6.0
  }
  
  let vol = Math.abs(sum);
  
  // N3XT SCALE INTELLIGENCE: Detectar si el archivo está en Metros o Pulgadas
  if (vol > 0 && vol < 5) {
      console.log("N3XT: Posible escala en Metros detectada. Normalizando a mm...");
      vol = vol * 1000000000;
  }
  
  return vol;
}

const calculateSupportArea = (geometry) => {
  if (!geometry.attributes.position || !geometry.attributes.normal) return 0
  
  const position = geometry.attributes.position
  const normal = geometry.attributes.normal
  let supportArea = 0
  const faceNormal = new THREE.Vector3()
  
  for (let i = 0; i < position.count; i += 3) {
    faceNormal.fromBufferAttribute(normal, i)
    
    // Threshold: Y < -0.5 (approx 120 degrees from up = overhang)
    if (faceNormal.y < -0.5) {
      _vA.fromBufferAttribute(position, i)
      _vB.fromBufferAttribute(position, i + 1)
      _vC.fromBufferAttribute(position, i + 2)
      _edge1.subVectors(_vB, _vA)
      _edge2.subVectors(_vC, _vA)
      supportArea += _cross.crossVectors(_edge1, _edge2).length() / 2
    }
  }
  return supportArea
}
const handleDrop = (e) => {
  e.preventDefault()
  isDragging.value = false
  const file = e.dataTransfer.files[0]
  if (file) {
    window.currentUploadedFile = file
    loadFile(file)
  }
}

const handleFileSelect = (e) => {
  const file = e.target.files[0]
  if (file) {
    window.currentUploadedFile = file
    loadFile(file)
  }
}

const processGeometry = (geometry, file) => {
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
  baseVolume = calculateVolume(geometry)
  
  const material = new THREE.MeshStandardMaterial({ 
    vertexColors: true,
    roughness: 0.3,
    metalness: 0.2,
    flatShading: false
  })
  
  // Analizar voladizos y pintar de rojo (Vertex Colors)
  const position = geometry.attributes.position
  const normal = geometry.attributes.normal
  const colors = new Float32Array(position.count * 3)
  
  const faceNormal = new THREE.Vector3()
  for (let i = 0; i < position.count; i += 3) {
    faceNormal.fromBufferAttribute(normal, i)
    
    // Si es un voladizo crítico, pintar de rojo suave
    const isOverhang = faceNormal.y < -0.5
    for (let j = 0; j < 3; j++) {
      const idx = (i + j) * 3
      if (isOverhang) {
        colors[idx] = 1.0     // R
        colors[idx + 1] = 0.2 // G
        colors[idx + 2] = 0.2 // B
      } else {
        colors[idx] = 0.12    // R (Verde Pino Base)
        colors[idx + 1] = 0.23 // G
        colors[idx + 2] = 0.20 // B
      }
    }
  }
  geometry.setAttribute('color', new THREE.BufferAttribute(colors, 3))
  
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

const loadFile = (file) => {
  const name = file.name.toLowerCase()
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

  const reader = new FileReader()
  reader.onload = (event) => {
    const contents = event.target.result
    
    // Clear old model
    if (currentGroup) {
      transformControls.detach()
      scene.remove(currentGroup)
      if (boxHelper) scene.remove(boxHelper)
      
      currentGroup.children.forEach(child => {
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
        textReader.onload = (textEvent) => {
          const loader = new OBJLoader()
          const object = loader.parse(textEvent.target.result)
          
          // Merge all geometries into one for simpler volume calc
          const geometries = []
          object.traverse((child) => {
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
      console.error(err)
      isLoading.value = false
      emit('loading', false)
      emit('error', 'Error al procesar el archivo 3D')
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
    <div v-if="hasModel" class="absolute bottom-8 left-1/2 -translate-x-1/2 z-10 flex gap-4 bg-gray-900/90 backdrop-blur-xl p-3 rounded-2xl shadow-2xl border border-white/10 transition-all duration-500 hover:scale-105">
      <div class="flex gap-2 border-r pr-4 border-white/10">
        <button @click="setView('top')" class="w-10 h-10 flex items-center justify-center text-[10px] font-black text-white/60 hover:text-white hover:bg-white/10 rounded-xl transition-all" title="Vista Superior">TOP</button>
        <button @click="setView('front')" class="w-10 h-10 flex items-center justify-center text-[10px] font-black text-white/60 hover:text-white hover:bg-white/10 rounded-xl transition-all" title="Vista Frontal">FRON</button>
        <button @click="setView('iso')" class="w-10 h-10 flex items-center justify-center text-[10px] font-black text-white/60 hover:text-white hover:bg-white/10 rounded-xl transition-all" title="Vista Isométrica">ISO</button>
      </div>
      <div class="flex items-center px-2">
          <button @click="autoOrient" class="px-6 py-3 bg-primary text-white text-[10px] font-black uppercase tracking-widest rounded-xl hover:scale-105 transition-all shadow-xl shadow-primary/20 flex items-center gap-2">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M19 13l-7 7-7-7m14-8l-7 7-7-7" /></svg>
            APLANAR
          </button>
      </div>
      <div class="w-px h-10 bg-white/10 mx-2"></div>
      <div class="flex gap-2">
        <button @click="setTransformMode('translate')" :class="transformMode === 'translate' ? 'bg-primary text-white scale-110 shadow-lg shadow-primary/20' : 'text-white/60 hover:text-white'" class="w-10 h-10 flex items-center justify-center rounded-xl transition-all" title="Mover">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11l5-5m0 0l5 5m-5-5v12" /></svg>
        </button>
        <button @click="setTransformMode('rotate')" :class="transformMode === 'rotate' ? 'bg-primary text-white scale-110 shadow-lg shadow-primary/20' : 'text-white/60 hover:text-white'" class="w-10 h-10 flex items-center justify-center rounded-xl transition-all" title="Rotar">
          <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" /></svg>
        </button>
        <button @click="setTransformMode('scale')" :class="transformMode === 'scale' ? 'bg-primary text-white scale-110 shadow-lg shadow-primary/20' : 'text-white/60 hover:text-white'" class="w-10 h-10 flex items-center justify-center rounded-xl transition-all" title="Escalar">
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
        <div class="text-center p-12 rounded-[40px] border-2 border-dashed transition-all duration-500" 
             :class="isDragging ? 'border-primary bg-primary/5 scale-105' : 'border-gray-200 bg-white/40 shadow-2xl'">
          <div class="w-32 h-32 mx-auto mb-8 bg-white rounded-[32px] shadow-xl flex items-center justify-center transition-transform group-hover/viewer:rotate-3"
               :class="isDragging ? 'text-primary scale-110' : 'text-gray-400'">
            <svg v-if="!isLoading" xmlns="http://www.w3.org/2000/svg" class="h-12 w-12" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
            </svg>
            <svg v-else class="animate-spin h-12 w-12 text-primary" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
              <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
              <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
            </svg>
          </div>
          
          <h2 class="text-3xl font-black text-gray-900 mb-4 tracking-tighter leading-tight">
            {{ isDragging ? '¡Suéltalo para analizar!' : (isLoading ? 'INGENIERÍA EN PROCESO...' : 'TU PROYECTO 3D COMIENZA AQUÍ') }}
          </h2>
          
          <div v-if="isLoading" class="mb-10">
            <div class="w-full max-w-[200px] h-1.5 bg-gray-100 rounded-full mx-auto overflow-hidden">
              <div class="h-full bg-primary animate-progress"></div>
            </div>
            <p class="text-[10px] font-black text-primary uppercase mt-4 tracking-[0.3em]">Escaneando Geometría...</p>
          </div>

          <p v-if="!isLoading" class="text-sm text-gray-500 font-medium mb-10 max-w-[280px] mx-auto leading-relaxed">
            {{ captchaUnlocked ? 'Sube tu archivo STL / OBJ para un análisis de laminado instantáneo.' : 'Completa el protocolo de seguridad para habilitar la carga de geometría.' }}
          </p>
          
          <div v-if="!isLoading && !captchaUnlocked" class="space-y-6">
            <div class="flex items-center gap-4 bg-gray-900 p-6 rounded-[2rem] text-white border-2 border-primary/20">
              <div class="text-left flex-1">
                <p class="text-[8px] font-black text-primary uppercase tracking-[0.4em] mb-1">Data Shield Active</p>
                <p class="text-xs font-black italic uppercase">Resuelve: {{ captchaA }} + {{ captchaB }} = ?</p>
              </div>
              <input type="number" v-model="captchaAnswer" @keyup.enter="verifyCaptcha" class="w-20 bg-white/10 border border-white/20 rounded-xl p-3 text-center text-white font-black outline-none focus:border-primary">
            </div>
            <button @click="verifyCaptcha" class="w-full bg-primary hover:bg-gray-900 text-white py-4 rounded-2xl font-black text-[10px] uppercase tracking-widest transition-all shadow-xl active:scale-95">
              Verificar Identidad
            </button>
          </div>

          <label v-else-if="!isLoading && captchaUnlocked" class="cursor-pointer bg-gray-900 hover:bg-primary text-white px-10 py-5 rounded-2xl font-black text-[10px] tracking-widest uppercase transition-all shadow-2xl shadow-primary/20 inline-block animate-in zoom-in duration-500 active:scale-95">
            Explorar Archivo 3D
            <input type="file" accept=".stl,.obj,.3mf" class="hidden" @change="handleFileSelect" />
          </label>
        </div>
      </div>
    </div>
  </div>
</template>
