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
    if count < 0:
        print(f"Underflow at line {line_num}: count={count}")
        # Reset count to avoid cascade
        count = 0

print(f"Final count: {count}")
