import re

with open(r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\components\admin\PurchaseLog.vue', 'r', encoding='utf-8') as f:
    lines = f.readlines()

count = 0
template_started = False
for i, line in enumerate(lines):
    line_num = i + 1
    if '<template>' in line:
        template_started = True
        continue
    if not template_started:
        continue
    if '</template>' in line:
        print(f"Final count before </template>: {count}")
        break
        
    opens = line.count('<div')
    closes = line.count('</div')
    if opens != 0 or closes != 0:
        count += opens
        count -= closes
        print(f"Line {line_num}: opens={opens}, closes={closes}, total={count}")
    if count < 0:
        print(f"!!! UNDERFLOW AT LINE {line_num} !!!")
        count = 0
