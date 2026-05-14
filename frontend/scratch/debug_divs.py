import re

with open(r'c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\views\AdminDashboard.vue', 'r', encoding='utf-8') as f:
    lines = f.readlines()

stack = []
for i, line in enumerate(lines):
    line_num = i + 1
    # Find all opening and closing tags in the line
    # Simple regex for tags like <div>, <main>, <aside>, <header>, <template>, <Transition>
    tags = re.findall(r'<(div|main|aside|header|template|Transition|KanbanBoard|AccountingDashboard|InventoryManager|PurchaseLog|ContactManager|MachineMonitor|OrderHistory)(?:\s+[^>]*?)?>|</(div|main|aside|header|template|Transition|KanbanBoard|AccountingDashboard|InventoryManager|PurchaseLog|ContactManager|MachineMonitor|OrderHistory)>', line)
    
    for open_tag, close_tag in tags:
        if open_tag:
            stack.append((open_tag, line_num))
        elif close_tag:
            if not stack:
                print(f"Error: Extra closing </{close_tag}> at line {line_num}")
            else:
                top_tag, top_line = stack.pop()
                if top_tag != close_tag:
                    print(f"Error: Tag mismatch. Expected </{top_tag}> (from line {top_line}) but found </{close_tag}> at line {line_num}")

if stack:
    for tag, line in stack:
        print(f"Error: Unclosed <{tag}> at line {line}")
