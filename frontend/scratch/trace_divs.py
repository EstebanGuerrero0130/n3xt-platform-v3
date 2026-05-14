import re

with open(r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\views\AdminDashboard.vue', 'r', encoding='utf-8') as f:
    lines = f.readlines()

count = 0
for i, line in enumerate(lines):
    line_num = i + 1
    opens = line.count('<div')
    closes = line.count('</div')
    if opens != 0 or closes != 0:
        count += opens
        count -= closes
        print(f"Line {line_num}: opens={opens}, closes={closes}, total={count}")
    if count < 0:
        print(f"!!! UNDERFLOW AT LINE {line_num} !!!")
        count = 0
