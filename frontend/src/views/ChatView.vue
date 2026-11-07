<script setup>
import { ref, computed, onMounted, onUnmounted, nextTick } from 'vue'
import { api } from '../services/api'
import { useToastStore } from '../stores/toast'
import { useConfirmStore } from '../stores/confirm'
import AppAvatar from '../components/ui/AppAvatar.vue'
import Icon from '../components/ui/Icon.vue'

const toast = useToastStore()
const confirmStore = useConfirmStore()

const conversations = ref([])
const contacts = ref([])
const canCreateGroup = ref(false)
const retention = ref(60)
const activeId = ref(null)
const messages = ref([])
const draft = ref('')
const pending = ref(null) // pending file attachment
const fileInput = ref(null)
const paneEl = ref(null)
let poll = null

const showNew = ref(false)
const newMode = ref('direct') // direct | group
const groupTitle = ref('')
const groupMembers = ref([])
const pickContact = ref('')

const active = computed(() => conversations.value.find((c) => c.id === activeId.value) || null)

async function loadConversations() {
  const res = await api.get('/chat/conversations')
  conversations.value = res.items || []
  retention.value = res.retention_days || 60
  canCreateGroup.value = !!res.can_create_group
}
async function loadContacts() {
  const res = await api.get('/chat/contacts')
  contacts.value = res.contacts || []
  canCreateGroup.value = !!res.can_create_group
}

async function openConversation(c) {
  activeId.value = c.id
  await loadMessages()
}
async function loadMessages() {
  if (!activeId.value) return
  const res = await api.get(`/chat/conversations/${activeId.value}/messages`)
  const before = messages.value.length
  messages.value = res.messages || []
  if (messages.value.length !== before) await scrollBottom()
}
async function scrollBottom() {
  await nextTick()
  if (paneEl.value) paneEl.value.scrollTop = paneEl.value.scrollHeight
}

async function pickFile(e) {
  const file = e.target.files?.[0]
  if (!file) return
  try {
    pending.value = await api.upload('/attachments', file)
    toast.show('فایل آماده ارسال است')
  } catch (err) {
    toast.show(err.message, 'danger')
  } finally {
    e.target.value = ''
  }
}

async function send() {
  if (!activeId.value) return
  const body = draft.value.trim()
  if (!body && !pending.value) return
  const payload = { body }
  if (pending.value) {
    payload.file_path = pending.value.attachment_path
    payload.file_name = pending.value.attachment
    payload.file_mime = pending.value.attachment_mime
    payload.file_size = pending.value.attachment_size
  }
  try {
    await api.post(`/chat/conversations/${activeId.value}/messages`, payload)
    draft.value = ''
    pending.value = null
    await loadMessages()
    loadConversations()
  } catch (e) {
    toast.show(e.message, 'danger')
  }
}

async function downloadFile(m) {
  try { await api.download(`/chat/messages/${m.id}/download`, m.file_name) }
  catch (e) { toast.show(e.message, 'danger') }
}

function openNew(mode) { newMode.value = mode; groupTitle.value = ''; groupMembers.value = []; pickContact.value = ''; showNew.value = true }

async function startDirect() {
  if (!pickContact.value) return
  try {
    const conv = await api.post('/chat/conversations', { type: 'direct', user_id: Number(pickContact.value) })
    showNew.value = false
    await loadConversations()
    openConversation(conv)
  } catch (e) { toast.show(e.message, 'danger') }
}
function toggleMember(id) {
  const i = groupMembers.value.indexOf(id)
  if (i === -1) groupMembers.value.push(id); else groupMembers.value.splice(i, 1)
}
async function createGroup() {
  if (!groupTitle.value.trim() || !groupMembers.value.length) { toast.show('نام گروه و حداقل یک عضو لازم است', 'danger'); return }
  try {
    const conv = await api.post('/chat/conversations', { type: 'group', title: groupTitle.value, member_ids: groupMembers.value })
    showNew.value = false
    await loadConversations()
    openConversation(conv)
  } catch (e) { toast.show(e.message, 'danger') }
}

async function removeConversation(c) {
  const ok = await confirmStore.ask({ title: 'حذف گفتگو', message: c.is_group ? `گروه «${c.title}» حذف شود؟` : 'این گفتگو حذف شود؟', confirmText: 'حذف', tone: 'danger' })
  if (!ok) return
  try {
    await api.del(`/chat/conversations/${c.id}`)
    if (activeId.value === c.id) { activeId.value = null; messages.value = [] }
    loadConversations()
  } catch (e) { toast.show(e.message, 'danger') }
}

onMounted(async () => {
  await loadConversations()
  await loadContacts()
  poll = setInterval(() => { if (activeId.value) loadMessages() }, 4000)
})
onUnmounted(() => { if (poll) clearInterval(poll) })
</script>

<template>
  <div class="flex flex-col gap-3" style="max-width: 1180px; height: calc(100vh - 130px)">
    <div class="flex items-center justify-between gap-4">
      <div>
        <h1 class="m-0 text-3xl font-bold text-ink-900">گفتگو</h1>
        <p class="mt-1 text-ink-600">پیام و ارسال فایل بین همکاران</p>
      </div>
      <div class="flex gap-2">
        <button @click="openNew('direct')" class="flex h-10 items-center gap-2 rounded-xl px-4 text-[13px] font-semibold text-white shadow-brand" style="background: var(--grad-brand)">
          <Icon name="plus" :size="16" /> گفتگوی جدید
        </button>
        <button v-if="canCreateGroup" @click="openNew('group')" class="flex h-10 items-center gap-2 rounded-xl border border-ink-300 bg-white px-4 text-[13px] font-semibold text-ink-800">
          <Icon name="users" :size="16" /> گروه جدید
        </button>
      </div>
    </div>

    <div class="grid min-h-0 flex-1 grid-cols-1 gap-4 rounded-2xl md:grid-cols-[300px_1fr]">
      <!-- conversation list -->
      <div class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-card">
        <div class="af-scroll flex-1 overflow-y-auto">
          <button v-for="c in conversations" :key="c.id" @click="openConversation(c)"
            class="flex w-full items-center gap-3 border-b border-ink-100 px-3.5 py-3 text-right last:border-0"
            :class="activeId === c.id ? 'bg-blue-50' : 'hover:bg-ink-50'">
            <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-full text-white" :style="c.is_group ? 'background: var(--grad-teal)' : 'background: var(--grad-brand-soft)'">
              <Icon v-if="c.is_group" name="users" :size="18" /><template v-else>{{ (c.title || '؟').charAt(0) }}</template>
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-[13.5px] font-semibold text-ink-900">{{ c.title }}</span>
              <span class="block truncate text-[11.5px] text-ink-500">{{ c.last_message || (c.is_group ? c.member_count + ' عضو' : 'شروع گفتگو') }}</span>
            </span>
          </button>
          <div v-if="!conversations.length" class="px-4 py-8 text-center text-[13px] text-ink-500">هنوز گفتگویی ندارید.</div>
        </div>
      </div>

      <!-- message pane -->
      <div class="flex min-h-0 flex-col overflow-hidden rounded-2xl border border-ink-200 bg-white shadow-card">
        <template v-if="active">
          <div class="flex items-center justify-between gap-3 border-b border-ink-200 px-5 py-3">
            <div class="flex items-center gap-2.5">
              <b class="text-[14px] text-ink-900">{{ active.title }}</b>
              <span v-if="active.is_group" class="num text-[11.5px] text-ink-500">{{ active.member_count }} عضو</span>
            </div>
            <button @click="removeConversation(active)" class="text-[12px] text-danger-500">حذف گفتگو</button>
          </div>

          <div class="mx-4 mt-3 rounded-lg bg-warning-50 px-3 py-1.5 text-center text-[11px] text-warning-600">
            پیام‌ها و فایل‌های قدیمی‌تر از {{ retention }} روز به‌صورت خودکار حذف می‌شوند.
          </div>

          <div ref="paneEl" class="af-scroll flex-1 overflow-y-auto p-4">
            <div v-for="m in messages" :key="m.id" class="mb-2.5 flex" :class="m.is_mine ? 'justify-start' : 'justify-end'">
              <div class="max-w-[70%] rounded-2xl px-3.5 py-2" :class="m.is_mine ? 'bg-blue-50 text-ink-900' : 'bg-ink-100 text-ink-900'">
                <div v-if="active.is_group && !m.is_mine" class="mb-0.5 text-[11px] font-semibold text-brand-strong">{{ m.user_name }}</div>
                <div v-if="m.body" class="whitespace-pre-wrap text-[13.5px] leading-6">{{ m.body }}</div>
                <button v-if="m.has_file" @click="downloadFile(m)" class="mt-1 flex items-center gap-1.5 text-[12.5px] font-semibold text-brand-strong">
                  <Icon name="paperclip" :size="13" /> {{ m.file_name }} ⬇
                </button>
                <div class="num mt-1 text-[10.5px] text-ink-400">{{ m.date }}</div>
              </div>
            </div>
            <div v-if="!messages.length" class="py-10 text-center text-[13px] text-ink-500">اولین پیام را بفرستید…</div>
          </div>

          <div class="flex items-center gap-2 border-t border-ink-200 px-3 py-3">
            <input ref="fileInput" type="file" class="hidden" @change="pickFile" />
            <button @click="fileInput.click()" class="flex h-10 w-10 items-center justify-center rounded-xl border border-ink-200 bg-ink-50 text-ink-600 hover:bg-ink-100" title="پیوست فایل">
              <Icon name="paperclip" :size="17" />
            </button>
            <div class="relative flex-1">
              <span v-if="pending" class="absolute -top-6 right-0 flex items-center gap-1 rounded bg-blue-50 px-2 py-0.5 text-[11px] text-brand-strong">
                📎 {{ pending.attachment }} <button @click="pending = null" class="text-danger-500">×</button>
              </span>
              <input v-model="draft" @keyup.enter="send" placeholder="پیام خود را بنویسید…" class="h-10 w-full rounded-xl border border-ink-200 bg-ink-50 px-3.5 text-[13.5px]" />
            </div>
            <button @click="send" class="flex h-10 items-center gap-1.5 rounded-xl px-4 text-[13px] font-semibold text-white shadow-brand" style="background: var(--grad-brand)">
              <Icon name="send" :size="15" /> ارسال
            </button>
          </div>
        </template>
        <div v-else class="flex flex-1 items-center justify-center text-[13.5px] text-ink-500">یک گفتگو را انتخاب کنید یا گفتگوی جدیدی آغاز کنید.</div>
      </div>
    </div>
  </div>

  <!-- new chat / group modal -->
  <div v-if="showNew" class="fixed inset-0 z-[80] flex items-center justify-center p-6" style="background: rgba(13,21,38,0.48); backdrop-filter: blur(3px)" @click.self="showNew = false">
    <div class="w-[min(480px,100%)] overflow-hidden rounded-2xl bg-white shadow-pop" style="animation: afModalIn .22s ease">
      <div class="flex items-center justify-between border-b border-ink-200 px-5 py-4">
        <b class="text-[15px] text-ink-900">{{ newMode === 'group' ? 'گروه جدید' : 'گفتگوی جدید' }}</b>
        <button @click="showNew = false" class="text-[20px] text-ink-500">×</button>
      </div>
      <div class="af-scroll flex max-h-[60vh] flex-col gap-3.5 overflow-y-auto p-5">
        <template v-if="newMode === 'direct'">
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">مخاطب</span>
            <select v-model="pickContact" class="af-inp">
              <option value="">— انتخاب همکار —</option>
              <option v-for="c in contacts" :key="c.id" :value="String(c.id)">{{ c.name }} — {{ c.role }}</option>
            </select>
          </label>
        </template>
        <template v-else>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">نام گروه</span>
            <input v-model="groupTitle" class="af-inp" />
          </label>
          <div class="flex flex-col gap-1.5">
            <span class="text-[12px] text-ink-600">اعضا</span>
            <div class="grid max-h-[240px] grid-cols-1 gap-1 overflow-y-auto af-scroll">
              <label v-for="c in contacts" :key="c.id" class="flex cursor-pointer items-center gap-2 rounded-lg border border-ink-200 bg-ink-50 px-3 py-2 text-[12.5px]"
                :class="groupMembers.includes(c.id) ? 'border-blue-300 bg-blue-50' : ''">
                <input type="checkbox" :checked="groupMembers.includes(c.id)" @change="toggleMember(c.id)" class="h-4 w-4 accent-[var(--color-brand)]" />
                <span class="flex-1">{{ c.name }}</span><span class="text-[11px] text-ink-400">{{ c.role }}</span>
              </label>
            </div>
          </div>
        </template>
      </div>
      <div class="flex justify-end gap-2.5 border-t border-ink-200 px-5 py-4">
        <button @click="showNew = false" class="rounded-[10px] border border-ink-300 bg-white px-4 py-2 text-[13px] text-ink-800">انصراف</button>
        <button v-if="newMode === 'direct'" @click="startDirect" :disabled="!pickContact" class="rounded-[10px] px-5 py-2 text-[13px] font-semibold text-white shadow-brand disabled:opacity-50" style="background: var(--grad-brand)">شروع گفتگو</button>
        <button v-else @click="createGroup" class="rounded-[10px] px-5 py-2 text-[13px] font-semibold text-white shadow-brand" style="background: var(--grad-brand)">ایجاد گروه</button>
      </div>
    </div>
  </div>
</template>

<style scoped>
.af-inp { height: 40px; border: 1px solid var(--color-ink-200); border-radius: 10px; padding: 0 11px; font-size: 13.5px; background: #fff; color: var(--color-ink-800); }
</style>
