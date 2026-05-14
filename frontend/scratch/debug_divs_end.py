import re
import sys

# Set encoding for stdout
if sys.stdout.encoding != 'utf-8':
    import io
    sys.stdout = io.TextIOWrapper(sys.stdout.buffer, encoding='utf-8')

with open(r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\views\AdminDashboard.vue', 'r', encoding='utf-8') as f:
    lines = f.readlines()

count = 0
for i, line in enumerate(lines):
    line_num = i + 1
    opens = line.count('<div')
    closes = line.count('</div')
    count += opens
    count -= closes
    if line_num > 2970:
        print(f"Line {line_num}: count={count} ({line.strip()})")
