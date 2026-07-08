import re

file_path = "src/views/HomeView.vue"
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Replace src attribute
content = re.sub(
    r':src="item\.i"\s+',
    ':src="item.image || item.i"\n            ',
    content
)

# Replace alt attribute
content = re.sub(
    r':alt="\'Noticia: \' \+ item\.t"',
    ':alt="\'Noticia: \' + (item.t || \'Nueva Noticia\')"',
    content
)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Done")
