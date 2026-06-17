import sys

file_path = r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\components\admin\WebManager.vue'
with open(file_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

if 'Imágenes' in lines[789]:
    new_block = '''  <div class="pt-2 border-t border-[#21262d] dark:border-[#21262d] mt-4">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase mb-2 block">Imagen Principal</label>
  <div class="flex gap-4 items-center mb-4">
  <div class="w-16 h-16 rounded-[6px] object-cover border border-[#21262d] relative group/mainimg shrink-0 bg-[#151a22] overflow-hidden">
  <img v-if="item.image" :src="item.image" class="w-full h-full object-cover" @error="(e: any) => e.target.style.display = 'none'" />
  <div v-else class="w-full h-full flex items-center justify-center">
  <svg class="w-6 h-6 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
  </div>
  <label class="absolute inset-0 bg-black/50 opacity-0 group-hover/mainimg:opacity-100 transition-opacity cursor-pointer flex items-center justify-center">
  <span class="text-[8px] font-black text-white uppercase">Subir</span>
  <input type="file" accept="image/*" class="hidden" @change="(e) => handleGalleryImageUpload(e, item, true)" />
  </label>
  </div>
  <div class="flex-1">
  <input v-model="item.image" placeholder="URL de imagen principal (Cloudinary recomendado)" class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-[10px] font-medium text-[#a4aea6] dark:text-[#c3c4c5] outline-none" />
  </div>
  </div>

  <label class="text-[8px] font-black text-[#c3c4c5] uppercase mb-2 block">Imágenes Adicionales</label>
  <div class="flex flex-wrap gap-2 items-center">
  <div v-for="(addImg, addIdx) in (item.images || [])" :key="addIdx" class="relative group/addimg">
  <img :src="addImg" class="w-12 h-12 rounded-[6px] object-cover border border-[#21262d] dark:border-[#21262d]" />
  <button class="absolute -top-1.5 -right-1.5 w-4 h-4 bg-red-500 text-white rounded-[60px] text-[8px] font-black flex items-center justify-center opacity-0 group-hover/addimg:opacity-100 transition-all " @click="removeCatalogImage(Number(item.originalIndex), Number(addIdx))">✕</button>
  </div>
  <label class="w-12 h-12 rounded-[6px] border-2 border-dashed border-gray-300 dark:border-[#21262d] flex items-center justify-center cursor-pointer hover:border-primary transition-all bg-[#0d1117] dark:bg-transparent shrink-0" title="Subir desde computador">
  <svg class="w-4 h-4 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
  <input type="file" accept="image/*" class="hidden" @change="(e) => handleGalleryImageUpload(e, item, false)" />
  </label>
  <div class="flex items-center gap-1 h-12">
  <input v-model="item._newImgUrl" @keyup.enter="addImageByUrl(item)" placeholder="URL Cloudinary..." class="w-24 bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-2.5 py-2 text-[8px] font-mono text-[#a4aea6] dark:text-[#c3c4c5] outline-none h-full" @click.stop />
  <button class="w-8 h-full rounded-[6px] bg-[#08872b]/10 hover:bg-[#08872b]/20 flex items-center justify-center text-[#8dd6ff] transition-all shrink-0" @click.stop="addImageByUrl(item)" title="Añadir por URL">
  <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/></svg>
  </button>
  </div>
  </div>
  </div>\n'''
    
    lines[788:808] = [new_block]
    
    with open(file_path, 'w', encoding='utf-8') as f:
        f.writelines(lines)
    print('Done replacing lines!')
else:
    print('Unexpected content at line 789')
