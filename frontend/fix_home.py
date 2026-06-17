import os
import re

file_path = r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\views\HomeView.vue'

with open(file_path, 'r', encoding='utf-8') as f:
    content = f.read()

# Add main
content = content.replace('<AppNavbar active-tab="home" subtext="Centro de Precisión Industrial" />', '<AppNavbar active-tab="home" subtext="Centro de Precisión Industrial" />\n\n<main id="main-content">')
content = content.replace('<AppFooter />', '</main>\n\n<AppFooter />')

# Convert h3,h4,h5,h6 to h2
content = re.sub(r'<h[3-6]([^>]*)>', r'<h2\1>', content)
content = re.sub(r'</h[3-6]>', r'</h2>', content)

with open(file_path, 'w', encoding='utf-8') as f:
    f.write(content)
print("Updated HomeView structure")
