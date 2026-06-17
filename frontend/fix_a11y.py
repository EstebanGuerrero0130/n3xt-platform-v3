import os
import re

dir_path = r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src'

def process_file(path):
    try:
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
    except:
        return

    original = content
    
    # Fix img alt using a function to avoid backreference issues
    def fix_img(m):
        tag = m.group(0)
        if 'alt=' not in tag and ':alt=' not in tag:
            # insert alt="Imagen N3XT" before the closing bracket
            if tag.endswith('/>'):
                return tag[:-2] + ' alt="Imagen N3XT 3D" />'
            else:
                return tag[:-1] + ' alt="Imagen N3XT 3D">'
        return tag

    content = re.sub(r'<img[^>]+>', fix_img, content)
    
    # Fix buttons
    def fix_button(match):
        btn_tag = match.group(1)
        inner = match.group(2)
        if 'aria-label' not in btn_tag:
            # check if inner has letters
            if not re.search(r'[a-zA-Z0-9]', inner):
                btn_tag = btn_tag.replace('>', ' aria-label="Botón interactivo">', 1)
        return btn_tag + inner + '</button>'

    content = re.sub(r'(<button[^>]*>)(.*?)</button>', fix_button, content, flags=re.DOTALL)
    
    # Fix a tags
    def fix_a(match):
        a_tag = match.group(1)
        inner = match.group(2)
        if 'aria-label' not in a_tag:
            if not re.search(r'[a-zA-Z0-9]', inner):
                a_tag = a_tag.replace('>', ' aria-label="Enlace de navegación">', 1)
        return a_tag + inner + '</a>'
        
    content = re.sub(r'(<a\s[^>]*>)(.*?)</a>', fix_a, content, flags=re.DOTALL)
    
    if content != original:
        with open(path, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'Fixed A11Y in: {path}')

for root, _, files in os.walk(dir_path):
    for f in files:
        if f.endswith('.vue'):
            process_file(os.path.join(root, f))
