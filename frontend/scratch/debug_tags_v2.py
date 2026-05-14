import sys

def check_tags(filename):
    with open(filename, 'r', encoding='utf-8') as f:
        lines = f.readlines()

    level = 0
    for i, line in enumerate(lines[1830:2160], 1831):
        stripped = line.strip()
        if not stripped: continue
        
        # Proper counting
        num_open = stripped.count('<') - stripped.count('</') - stripped.count('/>')
        
        # We want to print with the level BEFORE this line's changes if it's a closing tag, 
        # or after if it's an opening tag? 
        # Usually, closing tags should be at the same level as their opening tags.
        
        # If line starts with </, decrease level before printing
        if stripped.startswith('</'):
            level -= 1
            
        print(f"{i:4} | {'  ' * level}{stripped}")
        
        # If line started with < (not </ and not />), increase level for next line
        if stripped.startswith('<') and not stripped.startswith('</') and not '/>' in stripped:
            if not stripped.endswith('/>') and not any(x in stripped for x in ['<img','<br','<hr','<input']):
                level += 1
        elif stripped.startswith('</'):
            # Already decreased
            pass
        
check_tags('src/views/AdminDashboard.vue')
