file_path = "src/components/admin/WebManager.vue"
with open(file_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

posts_section = [
    "\n",
    "  <!-- ===== SUBSECCION: NOVEDADES EN TIEMPO REAL ===== -->\n",
    '  <div class="mt-10 p-8 bg-[#0a0f14] rounded-[2.5rem] border border-[#21262d]">\n',
    "  <div class=\"flex items-center justify-between mb-6\">\n",
    "  <div>\n",
    "  <h4 class=\"text-lg font-black text-[#ffffff] uppercase tracking-tight\">Novedades en Tiempo Real</h4>\n",
    "  <p class=\"text-xs text-[#c3c4c5] font-bold uppercase tracking-widest mt-0.5\">{{ (settings?.web?.posts || []).length }} publicaciones en el feed</p>\n",
    "  </div>\n",
    "  </div>\n",
    "\n",
    "  <div v-for=\"(post, idx) in settings?.web?.posts || []\" :key=\"idx\" class=\"p-6 bg-[#151a22] rounded-[2rem] mb-5 border border-[#21262d] hover:border-emerald-500/30 transition-all\">\n",
    "  <div class=\"flex items-start justify-between mb-5\">\n",
    "  <div class=\"flex items-center gap-3 flex-1 min-w-0\">\n",
    "  <div class=\"w-14 h-14 rounded-[16px] overflow-hidden bg-[#283041] shrink-0 border border-[#21262d]\">\n",
    "  <img v-if=\"post.i\" :src=\"post.i\" alt=\"Preview\" class=\"w-full h-full object-cover\" @error=\"(e) => { const t = e.target; if(t) t.style.display = 'none' }\" />\n",
    "  <div v-else class=\"w-full h-full flex items-center justify-center\">\n",
    "  <svg class=\"w-5 h-5 text-[#c3c4c5]\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z\"/></svg>\n",
    "  </div>\n",
    "  </div>\n",
    "  <div class=\"min-w-0 flex-1\">\n",
    "  <input v-model=\"post.t\" placeholder=\"Titulo de la novedad\" class=\"w-full bg-transparent text-sm font-black text-[#ffffff] outline-none placeholder:text-gray-600\" />\n",
    "  <span v-if=\"post.tag\" class=\"inline-block mt-1 px-2 py-0.5 bg-emerald-500/10 text-emerald-400 text-[8px] font-black uppercase tracking-wider rounded-[60px]\">{{ post.tag }}</span>\n",
    "  </div>\n",
    "  </div>\n",
    "  <button class=\"p-2 hover:bg-red-500/10 rounded-[6px] transition-all shrink-0 ml-3\" @click=\"settings.web.posts.splice(idx, 1)\">\n",
    "  <svg class=\"w-4 h-4 text-red-400\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16\"/></svg>\n",
    "  </button>\n",
    "  </div>\n",
    "\n",
    "  <div class=\"grid grid-cols-1 xl:grid-cols-12 gap-4 mb-4\">\n",
    "  <div class=\"col-span-1 xl:col-span-8\">\n",
    "  <label class=\"text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5\">URL de Imagen</label>\n",
    "  <input v-model=\"post.i\" placeholder=\"https://res.cloudinary.com/... o URL directa\" class=\"w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all\" />\n",
    "  </div>\n",
    "  <div class=\"col-span-1 xl:col-span-4\">\n",
    "  <label class=\"text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5\">Tag / Etiqueta</label>\n",
    "  <input v-model=\"post.tag\" placeholder=\"RESINA, FDM, MAKER...\" class=\"w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all\" />\n",
    "  </div>\n",
    "  </div>\n",
    "\n",
    "  <div class=\"mb-4\">\n",
    "  <label class=\"text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5\">Descripcion del post</label>\n",
    "  <input v-model=\"post.d\" placeholder=\"Descripcion breve...\" class=\"w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all\" />\n",
    "  </div>\n",
    "\n",
    "  <div class=\"mb-4\">\n",
    "  <label class=\"text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5\">URL del post (enlace Ver Post)</label>\n",
    "  <input v-model=\"post.url\" placeholder=\"https://www.instagram.com/p/...\" class=\"w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] outline-none focus:ring-2 focus:ring-emerald-500/20 transition-all\" />\n",
    "  </div>\n",
    "\n",
    "  <div class=\"grid grid-cols-3 gap-3\">\n",
    "  <div>\n",
    "  <label class=\"text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5\">Me Gusta</label>\n",
    "  <input v-model=\"post.l\" placeholder=\"1.2K\" class=\"w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-black text-rose-400 outline-none focus:ring-2 focus:ring-rose-500/20 transition-all\" />\n",
    "  </div>\n",
    "  <div>\n",
    "  <label class=\"text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5\">Comentarios</label>\n",
    "  <input v-model=\"post.c\" placeholder=\"24\" class=\"w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-black text-blue-400 outline-none focus:ring-2 focus:ring-blue-500/20 transition-all\" />\n",
    "  </div>\n",
    "  <div>\n",
    "  <label class=\"text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5\">Compartidos</label>\n",
    "  <input v-model=\"post.s\" placeholder=\"156\" class=\"w-full bg-[#283041] border border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs font-black text-purple-400 outline-none focus:ring-2 focus:ring-purple-500/20 transition-all\" />\n",
    "  </div>\n",
    "  </div>\n",
    "  </div>\n",
    "\n",
    "  <div v-if=\"!(settings?.web?.posts?.length)\" class=\"text-center py-10 border border-dashed border-[#21262d] rounded-[2rem] mt-4\">\n",
    "  <p class=\"text-sm font-black text-[#c3c4c5] uppercase tracking-tight\">Sin publicaciones aun</p>\n",
    "  <p class=\"text-xs text-[#a4aea6] mt-1\">Agrega novedades para mostrar en el Social Hub</p>\n",
    "  </div>\n",
    "\n",
    "  <button class=\"mt-4 px-6 py-4 bg-[#08872b]/10 hover:bg-[#08872b]/20 text-[#8dd6ff] rounded-[24px] text-[10px] font-black uppercase tracking-widest transition-all flex items-center gap-2\" @click=\"if(!settings.web.posts) settings.web.posts = []; settings.web.posts.push({ t: '', d: '', l: '', c: '', s: '', i: '', tag: '', url: '' })\">\n",
    "  <svg class=\"w-4 h-4\" fill=\"none\" viewBox=\"0 0 24 24\" stroke=\"currentColor\"><path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2.5\" d=\"M12 6v6m0 0v6m0-6h6m-6 0H6\"/></svg>\n",
    "  Nueva Novedad\n",
    "  </button>\n",
    "  </div>\n",
    "\n",
]

# Insert after line 1050 (0-indexed: 1049), which is the </div> closing the social tab
insert_at = 1050
lines = lines[:insert_at] + posts_section + lines[insert_at:]

with open(file_path, "w", encoding="utf-8") as f:
    f.writelines(lines)

print("OK: posts section inserted after social_links tab")

# Verify
with open(file_path, "r", encoding="utf-8") as f:
    c = f.read()

if "Novedades en Tiempo Real" in c:
    print("OK: posts admin section found in file")
if "post.c" in c:
    print("OK: comentarios field found")
if "post.s" in c:
    print("OK: compartidos field found")
