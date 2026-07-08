import re

file_path = "src/views/HomeView.vue"
with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Fix innerHTML
content = re.sub(
    r':innerHTML="sanitizeSVG\(card\.i\)"',
    r'v-html="sanitizeSVG(card.i)"',
    content
)

# Fix NOVEDADES badge in Ecosistema
badge_html = r'<!-- Badge -->\s*<div class="absolute top-4 left-4 px-4 py-1.5 bg-emerald-500 text-white text-\[9px\] font-black rounded-\[60px\] uppercase tracking-\[0.3em\] ">NOVEDADES</div>'
content = re.sub(badge_html, '', content)

# Fix @error to provide fallback image
error_html = r"@error=\"\(e: any\) => e.target.style.display='none'\""
content = re.sub(error_html, r'@error="(e: any) => e.target.src = \'https://images.unsplash.com/photo-1638202993928-7267aad84c31?auto=format&fit=crop&q=80&w=800\'"', content)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)

print("Fixed Ecosistema in HomeView")
