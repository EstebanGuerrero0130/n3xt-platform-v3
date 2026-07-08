file_path = "src/components/admin/WebManager.vue"
with open(file_path, "r", encoding="utf-8") as f:
    lines = f.readlines()

url_block = [
    '  <div class="mb-4">\n',
    '  <label class="text-[8px] font-black text-[#c3c4c5] uppercase tracking-widest block mb-1.5">URL del Articulo (boton Leer)</label>\n',
    '  <input v-model="article.url" placeholder="https://ejemplo.com/noticia" class="w-full bg-[#151a22] dark:bg-[#283041] border border-[#21262d] dark:border-[#21262d] rounded-[6px] px-4 py-2.5 text-xs text-[#a4aea6] dark:text-gray-300 outline-none focus:ring-2 focus:ring-primary/20 transition-all" />\n',
    '  <p class="text-[8px] text-[#a4aea6] mt-1">Enlace externo (abre en pestaña nueva). Si lo dejas vacio usa el slug de la noticia.</p>\n',
    '  </div>\n',
    '\n',
]

# Insert before line index 904 (0-indexed) = line 905 in editor
insert_at = 904
lines = lines[:insert_at] + url_block + lines[insert_at:]

with open(file_path, "w", encoding="utf-8") as f:
    f.writelines(lines)

print("OK: URL field inserted at line 905")

# Verify it's there
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

if "URL del Articulo" in content:
    print("OK: field found in file")
else:
    print("WARN: field NOT found")
