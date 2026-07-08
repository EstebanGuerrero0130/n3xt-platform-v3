file_path = "src/views/HomeView.vue"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# 1. Fix posts interface to include c and s fields
old_interface = "posts: { t: string; d: string; l: string; i: string; tag: string; url: string }[]"
new_interface  = "posts: { t: string; d: string; l: string; c?: string; s?: string; i: string; tag: string; url: string }[]"
content = content.replace(old_interface, new_interface)

# 2. Fix hardcoded "24" comentarios -> post.c
old_comments = ">24<"
new_comments  = ">{{ post.c || '24' }}<"
content = content.replace(old_comments, new_comments)

# 3. Fix hardcoded "156" compartidos -> post.s
old_shares = ">156<"
new_shares  = ">{{ post.s || '156' }}<"
content = content.replace(old_shares, new_shares)

# 4. Also fix image field: use post.i with fallback
old_post_img = ':src="post.i" :alt="\'Publicaci'
new_post_img  = ':src="post.i || \'https://images.unsplash.com/photo-1638202993928-7267aad84c31?auto=format&fit=crop&q=80&w=800\'" :alt="\'Publicaci'
content = content.replace(old_post_img, new_post_img)

# 5. Fix post defaults in webSettings to include c and s
old_post_default1 = "l: '1.2K',\n  i: 'https://images.unsplash.com/photo-1560972550-aba3456b5564"
new_post_default1  = "l: '1.2K',\n  c: '24',\n  s: '156',\n  i: 'https://images.unsplash.com/photo-1560972550-aba3456b5564"
content = content.replace(old_post_default1, new_post_default1)

with open(file_path, "w", encoding="utf-8") as f:
    f.write(content)

print("OK: HomeView updated")

with open(file_path, "r", encoding="utf-8") as f:
    check = f.read()

if "post.c || '24'" in check:
    print("OK: comentarios bound to post.c")
if "post.s || '156'" in check:
    print("OK: compartidos bound to post.s")
if "c?: string" in check:
    print("OK: interface updated")
