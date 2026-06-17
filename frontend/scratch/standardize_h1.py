import sys
import re

def replace_file_content(path, replacements):
    try:
        with open(path, 'r', encoding='utf-8') as f:
            content = f.read()
            
        for old, new in replacements:
            if old in content:
                content = content.replace(old, new)
            else:
                print(f'Warning: Pattern not found in {path}')
                
        with open(path, 'w', encoding='utf-8') as f:
            f.write(content)
        print(f'Successfully updated {path}')
    except Exception as e:
        print(f'Error updating {path}: {e}')

# 1. HomeView
home_path = r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\views\HomeView.vue'
home_old = '''<h1 ref="heroTitleRef" class="text-6xl md:text-[13rem] font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-[0.75] mb-16">
 N3XT<br/>
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-600 via-emerald-400 to-emerald-600 dark:from-primary dark:via-emerald-500 dark:to-primary bg-[length:200%_auto] animate-gradient-shift italic">3D</span>
 </h1>'''
home_new = '''<h1 ref="heroTitleRef" class="text-5xl md:text-7xl lg:text-8xl font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-[0.85] mb-6 animate-fade-in">
 N3XT <br class="md:hidden" />
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 via-primary to-emerald-300 dark:from-emerald-400 dark:via-primary dark:to-emerald-300 drop-shadow-[0_0_15px_rgba(16,185,129,0.3)] italic">3D</span>
 </h1>'''
replace_file_content(home_path, [(home_old, home_new)])

# 2. CatalogView
cat_path = r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\views\CatalogView.vue'
cat_old = '''<h1 class="split-title text-5xl md:text-7xl font-black tracking-tighter uppercase leading-[0.9] mb-6">
 NUESTRO <span class="text-[#8dd6ff]">CATÁLOGO</span>
 </h1>'''
cat_new = '''<h1 class="text-5xl md:text-7xl lg:text-8xl font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-[0.85] mb-6 animate-fade-in">
 NUESTRO <br class="md:hidden" />
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 via-primary to-emerald-300 dark:from-emerald-400 dark:via-primary dark:to-emerald-300 drop-shadow-[0_0_15px_rgba(16,185,129,0.3)] italic">CATÁLOGO</span>
 </h1>'''
replace_file_content(cat_path, [(cat_old, cat_new)])

# 3. GalleryView
gal_path = r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\views\GalleryView.vue'
gal_old = '''<h1 class="split-title text-5xl md:text-7xl font-black tracking-tighter uppercase leading-[0.9] mb-6">
 Nuestros <span class="text-[#8dd6ff]">Trabajos</span>
 </h1>'''
gal_new = '''<h1 class="text-5xl md:text-7xl lg:text-8xl font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-[0.85] mb-6 animate-fade-in">
 NUESTROS <br class="md:hidden" />
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 via-primary to-emerald-300 dark:from-emerald-400 dark:via-primary dark:to-emerald-300 drop-shadow-[0_0_15px_rgba(16,185,129,0.3)] italic">TRABAJOS</span>
 </h1>'''
replace_file_content(gal_path, [(gal_old, gal_new)])

# 4. ProjectInitView
init_path = r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\views\ProjectInitView.vue'
init_old = '''<h1 class="text-5xl md:text-7xl lg:text-8xl font-black tracking-tighter uppercase leading-[0.85] text-slate-900 dark:text-white animate-fade-in">
 INICIAR <br/>
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 via-primary to-emerald-300 dark:from-emerald-400 dark:via-primary dark:to-emerald-300 drop--[0_0_15px_rgba(16,185,129,0.3)]">PROYECTO 3D.</span>
 </h1>'''
init_new = '''<h1 class="text-5xl md:text-7xl lg:text-8xl font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-[0.85] mb-6 animate-fade-in">
 INICIAR <br class="md:hidden" />
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 via-primary to-emerald-300 dark:from-emerald-400 dark:via-primary dark:to-emerald-300 drop-shadow-[0_0_15px_rgba(16,185,129,0.3)] italic">PROYECTO 3D.</span>
 </h1>'''
replace_file_content(init_path, [(init_old, init_new)])

# 5. QuoteView
quote_path = r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\views\QuoteView.vue'
quote_old = '''<h1 class="text-5xl md:text-7xl lg:text-8xl font-black text-[#ffffff] dark:text-white tracking-tighter uppercase leading-[0.85] animate-fade-in">
 COTIZA.<br/>
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-primary via-emerald-500 to-primary-light drop--[0_0_15px_rgba(8,135,43,0.3)]">PRODUCE.</span>
 </h1>'''
quote_new = '''<h1 class="text-5xl md:text-7xl lg:text-8xl font-black text-slate-900 dark:text-white tracking-tighter uppercase leading-[0.85] mb-6 animate-fade-in">
 COTIZA. <br class="md:hidden" />
 <span class="text-transparent bg-clip-text bg-gradient-to-r from-emerald-500 via-primary to-emerald-300 dark:from-emerald-400 dark:via-primary dark:to-emerald-300 drop-shadow-[0_0_15px_rgba(16,185,129,0.3)] italic">PRODUCE.</span>
 </h1>'''
replace_file_content(quote_path, [(quote_old, quote_new)])
