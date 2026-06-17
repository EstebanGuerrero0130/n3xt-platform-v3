import sys

file_path = r"c:\Users\migel\.gemini\antigravity\scratch\n3xt-3d-system\frontend\src\components\admin\WebManager.vue"
with open(file_path, "r", encoding="utf-8") as f:
    content = f.read()

# Fix Inputs in General Tab
content = content.replace(
    'class="w-full bg-[#0d1117] dark:bg-[#151a22]/5 border-none rounded-[24px]',
    'class="w-full bg-[#151a22] dark:bg-[#090d0a]/50 border border-[#21262d] dark:border-[#21262d] rounded-[16px]'
)

# Fix grids in General Tab
content = content.replace(
    '<div class="grid grid-cols-2 gap-4">',
    '<div class="grid grid-cols-1 xl:grid-cols-2 gap-6">'
)
content = content.replace(
    '<div class="grid grid-cols-2 gap-8">',
    '<div class="grid grid-cols-1 xl:grid-cols-2 gap-8">'
)

# Fix grids in Noticias Tab
content = content.replace(
    '<div class="grid grid-cols-12 gap-4 mb-4">',
    '<div class="grid grid-cols-1 xl:grid-cols-12 gap-6 mb-6">'
)
content = content.replace(
    '<div class="col-span-12 sm:col-span-4">',
    '<div class="col-span-1 xl:col-span-4">'
)
content = content.replace(
    '<div class="col-span-6 sm:col-span-2">',
    '<div class="col-span-1 xl:col-span-2">'
)
content = content.replace(
    '<div class="col-span-6 sm:col-span-3">',
    '<div class="col-span-1 xl:col-span-3">'
)
content = content.replace(
    '<div class="col-span-12 sm:col-span-3">',
    '<div class="col-span-1 xl:col-span-3">'
)

# Fix Social Tab grid
content = content.replace(
    '<div class="grid grid-cols-12 gap-4 pl-11">',
    '<div class="grid grid-cols-1 xl:grid-cols-12 gap-6 xl:pl-11">'
)
content = content.replace(
    '<div class="col-span-12 sm:col-span-5">',
    '<div class="col-span-1 xl:col-span-5">'
)
content = content.replace(
    '<div class="col-span-12 sm:col-span-3 flex items-end gap-2">',
    '<div class="col-span-1 xl:col-span-3 flex items-end gap-2">'
)

with open(file_path, "w", encoding="utf-8") as f:
    f.write(content)

print("Grid and input classes updated successfully.")
