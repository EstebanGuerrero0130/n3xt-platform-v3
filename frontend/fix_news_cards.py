import re

file_path = "src/views/HomeView.vue"
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix 1: imagen — reemplazar item.i con item.image || item.i || fallback
old_img = ''':src="item.i" '''
new_img = ''':src="item.image || item.i || 'https://images.unsplash.com/photo-1631033031102-f855d4872494?auto=format&fit=crop&q=80&w=800'" '''
content = content.replace(old_img, new_img)

# Fix 2: alt del img de noticias — eliminar null al concatenar
old_alt = ''':alt="'Noticia: ' + item.t"'''
new_alt = ''':alt="'Noticia: ' + (item.t || 'N3XT')"'''
content = content.replace(old_alt, new_alt)

# Fix 3: href del botón LEER — usa url o slug
old_href = """:href="item.url || '#'" target="_blank" """
new_href = """:href="item.url || (item.slug ? '/noticias/' + item.slug : '#')" :target="(item.url && item.url !== '#') ? '_blank' : '_self'" """
content = content.replace(old_href, new_href)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

# Verify
with open(file_path, 'r', encoding='utf-8') as f:
    lines = f.readlines()

for i, line in enumerate(lines[878:912], start=879):
    if 'src' in line or 'href' in line or 'target' in line:
        print(f"Line {i}: {line.rstrip()}")

print("\nDone!")
