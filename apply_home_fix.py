import sys

content = open('frontend/src/views/HomeView.vue', 'r', encoding='utf-8').read()

content = content.replace(
    '<img\n :src="item.i" \n :alt="\'Noticia: \' + item.t"',
    '<img\n :src="item.image || item.i" \n :alt="\'Noticia: \' + (item.t || \'Sin título\')"'
)
content = content.replace(
    '{{ item.tag }}</span>',
    '{{ item.category || item.tag || \'NOTICIA\' }}</span>'
)

old_h2_block = """<h2 class="text-base font-black text-[#ffffff] dark:text-white uppercase tracking-tight leading-tight group-hover:text-emerald-500 transition-colors">{{ item.t }}</h2>
 <p class="text-[10px] text-[#c3c4c5] font-bold leading-relaxed uppercase tracking-tight flex-1">{{ item.d }}</p>
 <!-- CTA rounded-[60px] como referencia -->
 <a
 :href="item.url || '#'" target="_blank" """

new_h2_block = """<h2 class="text-base font-black text-[#ffffff] dark:text-white uppercase tracking-tight leading-tight group-hover:text-emerald-500 transition-colors">{{ item.t || 'Sin Título' }}</h2>
 <p class="text-[10px] text-[#c3c4c5] font-bold leading-relaxed uppercase tracking-tight flex-1">{{ item.st || item.d || item.body || '' }}</p>
 <!-- CTA rounded-[60px] como referencia -->
 <a
 :href="(item.url && item.url !== '#') ? item.url : (item.slug ? '/novedades/' + item.slug : '#')" target="_blank" """

content = content.replace(old_h2_block, new_h2_block)

open('frontend/src/views/HomeView.vue', 'w', encoding='utf-8').write(content)
print("Updated HomeView.vue")
