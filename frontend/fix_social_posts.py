file_path = "src/components/admin/WebManager.vue"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# Find the end of the social tab (where social_links section ends, before the closing div of the tab)
# We insert a NEW "POSTS / NOVEDADES" subsection inside the social tab, right after Anadir Plataforma button + closing div

social_close_marker = '  </div>\n\n  <!-- ====='

# Build the posts admin section to inject
posts_section = '''  </div>

  <!-- ===== SUBSECCION: NOVEDADES / SOCIAL POSTS ===== -->
  <div class="mt-10">
  <div class="flex items-center justify-between mb-6">
  <div>
  <h4 class="text-lg font-black text-[#ffffff] dark:text-white uppercase tracking-tight">Novedades en Tiempo Real</h4>
  <p class="text-xs text-[#c3c4c5] font-bold uppercase tracking-widest mt-0.5">{{ (settings?.web?.posts || []).length }} publicaciones visibles en el feed</p>
  </div>
  </div>

  <div v-for="(post, idx) in settings?.web?.posts || []" :key="idx" class="p-6 bg-[#151a22] dark:bg-[#151a22]/5 rounded-[2rem] mb-5 border border-[#21262d] dark:border-[#21262d] hover:border-emerald-500/30 transition-all">
  <!-- Header del post -->
  <div class="flex items-start justify-between mb-5">
  <div class="flex items-center gap-3 flex-1 min-w-0">
  <!-- Preview imagen -->
  <div class="w-14 h-14 rounded-[16px] overflow-hidden bg-[#283041] shrink-0 border border-[#21262d]">
  <img v-if="post.i" :src="post.i" alt="Preview post" class="w-full h-full object-cover" @error="(e) => { const t = e.target; if(t) t.style.display = \'none\' }" />
  <div v-else class="w-full h-full flex items-center justify-center">
  <svg class="w-5 h-5 text-[#c3c4c5]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
  </div>
  </div>
  <div class="min-w-0 flex-1">
  <input v-model="post.t" placeholder="Titulo de la novedad" class="w-full bg-transparent text-sm font-black text-[#ffffff] dark:text-white outline-none placeholder:text-gray-600" />
  <span v-if="post.tag" class="inline-block mt-1 px-2 py-0.5 bg-emerald-500/10 text-emerald-400 text-[8px] font-black uppercase tracking-wider rounded-[60px]">{{ post.tag }}</span>
  </div>
  </div>
  <button class="p-2 hover:bg-red-500/10 rounded-[6px] transition-all shrink-0 ml-3" title="Eliminar post" @click="settings.web.posts.splice(idx, 1)">
  <svg class="w-4 h-4 text-red-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
  </button>
  </div>

  <!-- Campos del post -->
  <div class="grid grid-cols-1 xl:grid-cols-12 gap-4 mb-4">
  <div class="col-span-1 xl:col-span-8">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL de Imagen (cuadrada recomendada)</label>
  <input v-model="post.i" placeholder="https://res.cloudinary.com/... o URL directa" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
  </div>
  <div class="col-span-1 xl:col-span-4">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Etiqueta / Tag</label>
  <input v-model="post.tag" placeholder="Ej: RESINA, FDM, MAKER..." class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
  </div>
  </div>

  <div class="mb-4">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Descripcion</label>
  <input v-model="post.d" placeholder="Descripcion breve del post..." class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
  </div>

  <div class="mb-4">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL del Post (enlace Ver Post)</label>
  <input v-model="post.url" placeholder="https://www.instagram.com/p/..." class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
  </div>

  <!-- Estadísticas del post -->
  <div class="grid grid-cols-3 gap-3">
  <div>
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Me Gusta (Likes)</label>
  <input v-model="post.l" placeholder="1.2K" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-black text-emerald-400 outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
  </div>
  <div>
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Comentarios</label>
  <input v-model="post.c" placeholder="24" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-black text-blue-400 outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
  </div>
  <div>
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">Compartidos</label>
  <input v-model="post.s" placeholder="156" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-black text-purple-400 outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
  </div>
  </div>
  </div>

  <div v-if="!(settings?.web?.posts?.length)" class="text-center py-10 border border-dashed border-[#21262d] rounded-[2rem] mt-4">
  <p class="text-sm font-black text-[#c3c4c5] uppercase tracking-tight">Sin publicaciones aun</p>
  <p class="text-xs text-[#a4aea6] mt-1">Agrega novedades para mostrar en el Social Hub</p>
  </div>

  <button class="mt-4 px-6 py-4 bg-[#08872b]/10 hover:bg-[#08872b]/20 text-[#8dd6ff] rounded-[24px] text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2" @click="if(!settings.web.posts) settings.web.posts = []; settings.web.posts.push({ t: '', d: '', l: '', c: '', s: '', i: '', tag: '', url: '' })">
  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/></svg>
  Nueva Novedad
  </button>
  </div>

  <!-- ===== '''

# Find the correct insertion point: end of the social tab div (after the Anadir Plataforma button closing div)
# The social tab ends just before the next tab comment
target = '  </div>\n\n  <!-- ===== TAB: ECOSISTEMA'
replacement = posts_section + 'TAB: ECOSISTEMA'

if target in content:
    content = content.replace(target, replacement, 1)
    print("OK: posts section injected before ECOSISTEMA tab")
else:
    # Try finding it differently
    print("WARN: exact marker not found, searching for alternatives...")
    idx = content.find('TAB: ECOSISTEMA')
    if idx > 0:
        print(f"Found ECOSISTEMA at char index {idx}")
        print(repr(content[idx-100:idx+50]))
