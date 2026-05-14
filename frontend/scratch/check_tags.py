import sys

def check_tags(filename):
    with open(filename, 'r', encoding='utf-8') as f:
        content = f.read()
    
    tags = []
    i = 0
    while i < len(content):
        if content[i] == '<':
            if content[i+1] == '/':
                # Closing tag
                end = content.find('>', i)
                tag = content[i+2:end].split()[0]
                if tags and tags[-1] == tag:
                    tags.pop()
                else:
                    print(f"Mismatch: Closing {tag} but expected {tags[-1] if tags else 'None'} at {i}")
                    # return False
                i = end + 1
            elif content[i+1] == '!':
                # Comment or doctype
                if content[i:i+4] == '<!--':
                    i = content.find('-->', i) + 3
                else:
                    i = content.find('>', i) + 1
            else:
                # Opening tag
                end = content.find('>', i)
                if content[end-1] == '/':
                    # Self-closing
                    pass
                else:
                    tag = content[i+1:end].split()[0]
                    if tag not in ['img', 'br', 'hr', 'input', 'link', 'meta']:
                        tags.append(tag)
                i = end + 1
        else:
            i += 1
    
    if tags:
        print(f"Unclosed tags: {tags}")
        return False
    return True

check_tags(sys.argv[1])
