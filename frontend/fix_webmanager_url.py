file_path = "src/components/admin/WebManager.vue"
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Add URL field before slug grid
old_slug_block = '  <div class="grid grid-cols-12 gap-4">\r\n  <div class="col-span-8">\r\n  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL Amigable (Slug)</label>'

new_slug_block = '''  <div class="mb-4">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL del Articulo (Enlace boton Leer)</label>
  <input v-model="article.url" placeholder="https://ejemplo.com/noticia — deja vacio para usar el slug" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all" />
  <p class="text-[8px] text-[#a4aea6] mt-1 font-mono">Pega aqui el enlace externo o interno. Si lo dejas vacio, el boton usa el slug.</p>
  </div>

  <div class="grid grid-cols-12 gap-4">
  <div class="col-span-8">
  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL Amigable (Slug)</label>'''

replaced = content.replace(old_slug_block, new_slug_block)
if replaced == content:
    print("WARN: target not found, trying LF version")
    old_slug_lf = old_slug_block.replace('\r\n', '\n')
    replaced = content.replace(old_slug_lf, new_slug_block)

# Fix push to include url field
old_push = "{ t: '', st: '', image: '', body: '', date: new Date().toISOString().split('T')[0], status: 'draft', category: '', slug: '' }"
new_push  = "{ t: '', st: '', image: '', body: '', url: '', date: new Date().toISOString().split('T')[0], status: 'draft', category: '', slug: '' }"
replaced = replaced.replace(old_push, new_push)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(replaced)

if "URL del Articulo" in replaced:
    print("OK: URL field added to news form")
else:
    print("WARN: URL field not found after replacement")

if "url: ''" in replaced:
    print("OK: nueva noticia includes url field")
