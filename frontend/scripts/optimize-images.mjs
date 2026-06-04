import { readFile, writeFile, readdir, stat } from 'fs/promises'
import { join, extname, basename } from 'path'
import sharp from 'sharp'

const DIST_DIR = join(process.cwd(), 'dist')
const SUPPORTED_FORMATS = new Set(['.png', '.jpg', '.jpeg'])

let totalOriginalSize = 0
let totalWebpSize = 0
let convertedCount = 0

async function walkDir(dir) {
  const files = []
  try {
    const entries = await readdir(dir, { withFileTypes: true })
    for (const entry of entries) {
      const fullPath = join(dir, entry.name)
      if (entry.isDirectory()) {
        files.push(...await walkDir(fullPath))
      } else if (SUPPORTED_FORMATS.has(extname(entry.name).toLowerCase())) {
        files.push(fullPath)
      }
    }
  } catch {
    // Directory may not exist — skip silently
  }
  return files
}

async function generateWebp(inputPath) {
  const webpPath = inputPath.replace(/\.(png|jpg|jpeg)$/i, '.webp')
  try {
    await stat(webpPath)
    return false // WebP already exists
  } catch {
    // Proceed
  }
  try {
    const buf = await readFile(inputPath)
    const { size: origSize } = await stat(inputPath)
    const webpBuf = await sharp(buf).webp({ quality: 75 }).toBuffer()
    await writeFile(webpPath, webpBuf)
    const savings = ((1 - webpBuf.length / origSize) * 100).toFixed(1)
    console.log(`  OK ${basename(inputPath)} -> ${basename(webpPath)} (${savings}% smaller)`)
    totalOriginalSize += origSize
    totalWebpSize += webpBuf.length
    convertedCount++
    return true
  } catch (err) {
    console.error(`  FAILED ${basename(inputPath)}: ${err.message}`)
    return false
  }
}

async function optimize() {
  console.log('\nN3XT 3D — Asset Optimization')
  console.log('='.repeat(50))

  console.log('\nProcessing dist/ assets...')
  const distFiles = await walkDir(DIST_DIR)
  if (distFiles.length > 0) {
    await Promise.all(distFiles.map(file => generateWebp(file)))
  } else {
    console.log('  (no images found in dist/)')
  }

  if (convertedCount > 0) {
    const totalSavings = ((1 - totalWebpSize / totalOriginalSize) * 100).toFixed(1)
    console.log(`\nDone! Converted ${convertedCount} images. Total savings: ${totalSavings}%`)
  } else {
    console.log('\nDone! No new images needed conversion.')
  }
}

optimize().catch(err => {
  console.error('Optimization failed:', err)
  process.exit(1)
})
