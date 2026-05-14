import re

def check_vue_template(filename):
    with open(filename, 'r', encoding='utf-8') as f:
        content = f.read()
    
    # Extract template section
    start_match = re.search(r'<template>', content)
    end_match = re.search(r'</template>\s*(?=<style|<script|$)', content)
    
    if not start_match or not end_match:
        print("Template not found")
        return
    
    template = content[start_match.start():end_match.end()]
    print(f"Checking template of length {len(template)}")
    
    tags = []
    # Simplified regex for tags
    for match in re.finditer(r'<(/?)([a-zA-Z0-9:-]+)(\s+[^>]*?)?(/?)(?=>)', template):
        is_closing = match.group(1) == '/'
        tag_name = match.group(2)
        is_self_closing = match.group(4) == '/'
        
        if tag_name in ['img', 'br', 'hr', 'input', 'link', 'meta', 'path', 'svg', 'circle', 'rect', 'line', 'polyline', 'polygon', 'ellipse']:
            continue
            
        if is_closing:
            if not tags:
                print(f"Extra closing tag: </{tag_name}> around position {match.start()}")
            else:
                last_tag = tags.pop()
                if last_tag.lower() != tag_name.lower():
                    print(f"Mismatch: Got </{tag_name}> but expected </{last_tag}> around position {match.start()}")
                    tags.append(last_tag) # push back to continue
        elif not is_self_closing:
            tags.append(tag_name)
            
    if tags:
        print(f"Unclosed tags: {tags}")
    else:
        print("All tags balanced!")

check_vue_template('src/views/AdminDashboard.vue')
