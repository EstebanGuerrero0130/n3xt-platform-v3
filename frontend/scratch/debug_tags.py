with open('src/views/AdminDashboard.vue', 'r', encoding='utf-8') as f:
    lines = f.readlines()

level = 0
for i, line in enumerate(lines[1830:2160], 1831):
    stripped = line.strip()
    if not stripped: continue
    
    # Very crude tag counter
    opens = stripped.count('<') - stripped.count('</') - stripped.count('/>')
    closes = stripped.count('</')
    
    print(f"{i:4} | {'  ' * level}{stripped}")
    level += opens
    level -= closes
