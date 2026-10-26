<script setup lang="ts">
/**
 * Equipo del taller (solo el dueño). El personal entra con su correo y ve solo
 * las secciones marcadas; el servidor exige lo mismo (EnsureSection).
 */
import { ref, reactive, onMounted } from 'vue'
import { api } from '../../services/api'

interface Member { id: number; name: string; email: string; role: 'owner' | 'staff'; permissions: string[]; is_me: boolean }

const props = defineProps<{
  showNotify: (msg: string, type?: string) => void
  askConfirm: (title: string, message: string, icon: string, onConfirm: () => void) => void
}>()

const SECTIONS = [
  { id: 'kanban', label: 'Producción', hint: 'Pedidos, estados, pagos y calendario' },
  { id: 'history', label: 'Historial', hint: 'Pedidos terminados' },
  { id: 'inventory', label: 'Inventario', hint: 'Materiales y stock' },
  { id: 'machines', label: 'Maquinaria', hint: 'Máquinas y mantenimiento' },
  { id: 'purchases', label: 'Compras', hint: 'Compras y gastos' },
  { id: 'contacts', label: 'Contactos', hint: 'Clientes y proveedores' },
  { id: 'accounting', label: 'Contabilidad', hint: 'Ventas, costos y márgenes' },
  { id: 'discounts', label: 'Descuentos', hint: 'Cupones' },
  { id: 'web', label: 'Sitio web', hint: 'Textos, catálogo, galería y reseñas' },
  { id: 'settings', label: 'Parámetros', hint: 'Costos, empresa y marca' },
]
const labelOf = (id: string) => SECTIONS.find(s => s.id === id)?.label ?? id

const members = ref<Member[]>([])
const loading = ref(true)
const saving = ref(false)
const formError = ref('')
const editingId = ref<number | null>(null)
const showForm = ref(false)
const emptyForm = () => ({ name: '', email: '', password: '', role: 'staff' as 'owner' | 'staff', permissions: ['kanban'] as string[] })
const form = reactive(emptyForm())

const load = async () => {
  loading.value = true
  try {
    const data = await api.get('/admin/team', true)
    members.value = Array.isArray(data) ? data : []
  } catch (err) {
    props.showNotify(err instanceof Error ? err.message : 'No se pudo cargar el equipo', 'error')
  } finally {
    loading.value = false
  }
}
onMounted(load)

const openNew = () => {
  Object.assign(form, emptyForm())
  editingId.value = null
  formError.value = ''
  showForm.value = true
}

const openEdit = (m: Member) => {
  Object.assign(form, { name: m.name, email: m.email, password: '', role: m.role, permissions: [...m.permissions] })
  editingId.value = m.id
  formError.value = ''
  showForm.value = true
}

const submit = async () => {
  formError.value = ''
  if (!form.name.trim()) { formError.value = 'Escribe el nombre.'; return }
  if (!editingId.value && !/^\S+@\S+\.\S+$/.test(form.email)) { formError.value = 'Escribe un correo válido.'; return }
  if ((!editingId.value || form.password) && form.password.length < 10) { formError.value = 'La contraseña lleva al menos 10 caracteres.'; return }
  if (form.role === 'staff' && !form.permissions.length) { formError.value = 'Marca al menos una sección.'; return }

  saving.value = true
  try {
    const body: Record<string, unknown> = { name: form.name.trim(), role: form.role, permissions: form.permissions }
    if (form.password) body.password = form.password
    if (editingId.value) {
      await api.patch(`/admin/team/${editingId.value}`, body)
      props.showNotify('Usuario actualizado', 'success')
    } else {
      await api.post('/admin/team', { ...body, email: form.email.trim() })
      props.showNotify('Usuario creado. Ya puede entrar con su correo.', 'success')
    }
    showForm.value = false
    await load()
  } catch (err) {
    formError.value = err instanceof Error ? err.message : 'No se pudo guardar.'
  } finally {
    saving.value = false
  }
}

const remove = (m: Member) => {
  props.askConfirm('Eliminar usuario', `¿Quitar a ${m.name} del equipo? Ya no podrá entrar al panel.`, '', async () => {
    try {
      await api.delete(`/admin/team/${m.id}`)
      props.showNotify('Usuario eliminado', 'success')
      await load()
    } catch (err) {
      props.showNotify(err instanceof Error ? err.message : 'No se pudo eliminar', 'error')
    }
  })
}
</script>

<template>
  <div class="space-y-8">
    <header class="flex flex-col md:flex-row md:items-end md:justify-between gap-6">
      <div>
        <h1 class="admin-title">Equipo <span class="text-brand">del taller</span></h1>
        <p class="admin-lead">Cada persona entra con su correo y ve solo las secciones que le des.</p>
      </div>
      <button type="button" class="btn-primary btn-lg" @click="openNew">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 5v14m-7-7h14"/></svg>
        Nuevo usuario
      </button>
    </header>

    <form v-if="showForm" class="admin-card space-y-6" novalidate aria-labelledby="team-form-title" @submit.prevent="submit">
      <h2 id="team-form-title" class="admin-h2">{{ editingId ? 'Editar usuario' : 'Nuevo usuario' }}</h2>
      <div class="grid gap-4 md:grid-cols-3">
        <div>
          <label for="team-name" class="admin-label mb-2">Nombre</label>
          <input id="team-name" v-model="form.name" type="text" maxlength="255" class="field" autocomplete="off">
        </div>
        <div>
          <label for="team-email" class="admin-label mb-2">Correo</label>
          <input id="team-email" v-model="form.email" type="email" class="field" :disabled="!!editingId" autocomplete="off">
        </div>
        <div>
          <label for="team-password" class="admin-label mb-2">{{ editingId ? 'Nueva contraseña' : 'Contraseña' }} <span class="opacity-70">{{ editingId ? '(opcional)' : '(mín. 10)' }}</span></label>
          <input id="team-password" v-model="form.password" type="password" class="field" autocomplete="new-password">
        </div>
      </div>

      <fieldset>
        <legend class="admin-label mb-2">Tipo de usuario</legend>
        <div class="flex flex-wrap gap-2">
          <button type="button" class="chip" :aria-pressed="form.role === 'staff'" @click="form.role = 'staff'">Personal</button>
          <button type="button" class="chip" :aria-pressed="form.role === 'owner'" @click="form.role = 'owner'">Dueño (todo)</button>
        </div>
      </fieldset>

      <fieldset v-if="form.role === 'staff'">
        <legend class="admin-label mb-2">Secciones que puede ver</legend>
        <div class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
          <label v-for="s in SECTIONS" :key="s.id" class="flex items-start gap-3 rounded-[var(--radius-control)] border border-line p-3 cursor-pointer hover:bg-surface-2">
            <input v-model="form.permissions" type="checkbox" :value="s.id" class="mt-1 w-4 h-4 accent-[var(--color-primary,#08872b)]">
            <span><span class="font-semibold">{{ s.label }}</span><span class="block text-sm text-muted">{{ s.hint }}</span></span>
          </label>
        </div>
      </fieldset>

      <p v-if="formError" role="alert" class="text-sm text-danger">{{ formError }}</p>
      <div class="flex flex-wrap gap-3">
        <button type="submit" class="btn-primary" :disabled="saving">{{ saving ? 'Guardando…' : (editingId ? 'Guardar cambios' : 'Crear usuario') }}</button>
        <button type="button" class="btn-outlined" @click="showForm = false">Cancelar</button>
      </div>
    </form>

    <section class="admin-card" aria-labelledby="team-title">
      <h2 id="team-title" class="admin-h2 mb-4">Personas con acceso</h2>
      <p v-if="loading" class="py-8 text-center text-muted">Cargando…</p>
      <ul v-else class="divide-y divide-line">
        <li v-for="m in members" :key="m.id" class="py-4 flex flex-wrap items-center gap-x-4 gap-y-2">
          <div class="min-w-0 flex-1">
            <p class="font-semibold">{{ m.name }} <span v-if="m.is_me" class="text-sm font-normal text-muted">(tú)</span></p>
            <p class="text-sm text-muted break-all">{{ m.email }}</p>
            <div class="mt-2 flex flex-wrap gap-1.5">
              <span v-if="m.role === 'owner'" class="badge bg-primary/15 text-brand">Dueño · todo</span>
              <span v-for="p in m.permissions" v-else :key="p" class="badge bg-surface-2 text-muted">{{ labelOf(p) }}</span>
            </div>
          </div>
          <div class="flex gap-2">
            <button type="button" class="btn-outlined btn-sm" :aria-label="`Editar ${m.name}`" @click="openEdit(m)">Editar</button>
            <button v-if="!m.is_me" type="button" class="btn-sm inline-flex items-center rounded-full px-3 text-danger hover:bg-danger/10" :aria-label="`Eliminar ${m.name}`" @click="remove(m)">Eliminar</button>
          </div>
        </li>
      </ul>
    </section>
  </div>
</template>
