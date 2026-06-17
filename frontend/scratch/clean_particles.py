import os
import re

dir_path = r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\views'
files_to_check = ['CatalogView.vue', 'GalleryView.vue', 'HomeView.vue', 'ProjectInitView.vue']

for fname in files_to_check:
    path = os.path.join(dir_path, fname)
    try:
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
            
        # Remove import
        content = re.sub(r"import\s+\{\s*useParticles\s*\}\s+from\s+['\"].*?useParticles['\"]\n?", '', content)
        
        # Remove variable declaration block
        content = re.sub(r'const\s+\{\s*particlesRef[^}]+\}\s*=\s*useParticles\(\{[^\}]+\}\)\n?', '', content)
        
        # Remove div tags
        content = re.sub(r'<div[^>]*ref=\"(?:heroParticlesRef|particlesRef)\"[^>]*></div>\n?', '', content)
        
        # Remove CSS
        content = re.sub(r'\.[a-zA-Z0-9_-]*particles\s*\{[^}]+\}\n?', '', content)
        
        with open(path, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'Cleaned particles in {fname}')
    except Exception as e:
        print(f'Error processing {fname}: {e}')
