import re

with open(r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\views\AdminDashboard.vue', 'r', encoding='utf-8') as f:
    lines = f.readlines()

count = 0
for i, line in enumerate(lines):
    line_num = i + 1
    opens = line.count('<div')
    closes = line.count('</div')
    count += opens
    count -= closes
    if line_num % 100 == 0 or line_num > 3130:
        print(f"Line {line_num}: count={count}")
