<script setup>
import { ref, reactive, onMounted } from 'vue'
import { api } from '../services/api'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'
import AppCard from '../components/ui/AppCard.vue'
import AppAvatar from '../components/ui/AppAvatar.vue'
import Icon from '../components/ui/Icon.vue'
import JalaliDatePicker from '../components/ui/JalaliDatePicker.vue'

const auth = useAuthStore()
const toast = useToastStore()

const me = ref(null)
const photoUrl = ref(null)
const signatureUrl = ref(null)
const saving = ref(false)
const uploading = ref(false)
const uploadingSig = ref(false)
const fileInput = ref(null)
const sigInput = ref(null)

const form = reactive({
  email: '', mobile: '', phone: '', birth_date: '', address: '', description: '',
})

async function load() {
  const data = await api.get('/profile')
  me.value = data
  photoUrl.value = data.photo_url
  signatureUrl.value = data.signature_url
  form.email = data.email || ''
  const p = data.profile || {}
  form.mobile = p.mobile || ''
  form.phone = p.phone || ''
  form.birth_date = p.birth_date || ''
  form.address = p.address || ''
  form.description = p.description || ''
}

const pw = reactive({ current: '', next: '', confirm: '' })
const savingPw = ref(false)
async function changePassword() {
  if (!pw.current || !pw.next) { toast.show('رمز فعلی و جدید را وارد کنید', 'danger'); return }
  if (pw.next !== pw.confirm) { toast.show('تکرار رمز جدید مطابقت ندارد', 'danger'); return }
  if (pw.next.length < 6) { toast.show('رمز جدید باید حداقل ۶ نویسه باشد', 'danger'); return }
  savingPw.value = true
  try {
    await api.post('/profile/password', { current_password: pw.current, new_password: pw.next })
    toast.show('رمز عبور با موفقیت تغییر کرد')
    pw.current = pw.next = pw.confirm = ''
  } catch (e) {
    toast.show(e.message, 'danger')
  } finally {
    savingPw.value = false
  }
}

async function save() {
  saving.value = true
  try {
    const data = await api.put('/profile', { ...form })
    me.value = data
    toast.show('پروفایل به‌روزرسانی شد')
    // keep header/name fresh
    auth.refresh()
  } catch (e) {
    toast.show(e.message, 'danger')
  } finally {
    saving.value = false
  }
}

function pickPhoto() { fileInput.value?.click() }

async function onPhoto(e) {
  const file = e.target.files?.[0]
  if (!file) return
  uploading.value = true
  try {
    const data = await api.upload('/profile/photo', file)
    me.value = data
    photoUrl.value = data.photo_url
    toast.show('تصویر پروفایل به‌روزرسانی شد')
    auth.refresh()
  } catch (err) {
    toast.show(err.message, 'danger')
  } finally {
    uploading.value = false
    e.target.value = ''
  }
}

function pickSig() { sigInput.value?.click() }

async function onSignature(e) {
  const file = e.target.files?.[0]
  if (!file) return
  uploadingSig.value = true
  try {
    const data = await api.upload('/profile/signature', file)
    me.value = data
    signatureUrl.value = data.signature_url
    toast.show('تصویر امضا به‌روزرسانی شد')
    auth.refresh()
  } catch (err) {
    toast.show(err.message, 'danger')
  } finally {
    uploadingSig.value = false
    e.target.value = ''
  }
}

onMounted(load)
</script>

<template>
  <div class="flex flex-col gap-[18px]" style="max-width: 900px">
    <div>
      <h1 class="m-0 text-3xl font-bold text-ink-900">پروفایل من</h1>
      <p class="mt-1.5 text-ink-600">اطلاعات شخصی و تصویر حساب کاربری</p>
    </div>

    <div v-if="me" class="grid grid-cols-1 gap-4 md:grid-cols-[280px_1fr]">
      <!-- Identity + photo -->
      <AppCard>
        <div class="flex flex-col items-center gap-3 text-center">
          <div class="relative">
            <AppAvatar :name="me.name" :photo="photoUrl" :size="104" />
            <button @click="pickPhoto" :disabled="uploading"
              class="absolute -bottom-1 -left-1 flex h-9 w-9 items-center justify-center rounded-full border-2 border-white text-white shadow-brand disabled:opacity-60"
              style="background: var(--grad-brand)" title="تغییر تصویر">
              <Icon name="camera" :size="16" />
            </button>
            <input ref="fileInput" type="file" accept="image/*" class="hidden" @change="onPhoto" />
          </div>
          <div>
            <div class="text-[16px] font-bold text-ink-900">{{ me.name }}</div>
            <div class="mt-0.5 text-[12.5px] text-ink-500">{{ me.role?.name }} · {{ me.role?.level_label }}</div>
            <div class="mt-0.5 text-[12.5px] text-ink-500">{{ me.department?.name }}</div>
          </div>
          <div class="num text-[12px] text-ink-400" dir="ltr">@{{ me.username }}</div>
        </div>
      </AppCard>

      <!-- Editable fields -->
      <AppCard title="اطلاعات تماس">
        <div class="grid grid-cols-1 gap-3.5 sm:grid-cols-2">
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">ایمیل</span>
            <input v-model="form.email" class="af-inp" dir="ltr" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">موبایل</span>
            <input v-model="form.mobile" class="af-inp" dir="ltr" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">تلفن</span>
            <input v-model="form.phone" class="af-inp" dir="ltr" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">تاریخ تولد</span>
            <JalaliDatePicker v-model="form.birth_date" placeholder="۱۳۷۰/۰۱/۰۱" />
          </label>
          <label class="col-span-2 flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">نشانی</span>
            <input v-model="form.address" class="af-inp" />
          </label>
          <label class="col-span-2 flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">دربارهٔ من</span>
            <textarea v-model="form.description" rows="3" class="af-inp" style="height:auto; padding:9px 11px"></textarea>
          </label>
        </div>
        <div class="mt-4 flex justify-end">
          <button @click="save" :disabled="saving"
            class="rounded-[10px] px-6 py-2.5 text-[13px] font-semibold text-white shadow-brand disabled:opacity-60" style="background: var(--grad-brand)">
            {{ saving ? 'در حال ذخیره…' : 'ذخیرهٔ تغییرات' }}
          </button>
        </div>
      </AppCard>

      <!-- signature -->
      <AppCard title="امضای من" subtitle="تصویر امضا در پایین نامه‌های شما درج می‌شود" style="grid-column: 1 / -1">
        <div class="flex items-center gap-5">
          <div class="flex h-[90px] w-[220px] items-center justify-center rounded-xl border border-dashed border-ink-300 bg-ink-50">
            <img v-if="signatureUrl" :src="signatureUrl" alt="امضا" class="max-h-[74px] max-w-[200px]" />
            <span v-else class="text-[12.5px] text-ink-400">امضایی بارگذاری نشده است</span>
          </div>
          <div>
            <input ref="sigInput" type="file" accept="image/*" class="hidden" @change="onSignature" />
            <button @click="pickSig" :disabled="uploadingSig"
              class="rounded-[10px] border border-blue-300 bg-blue-50 px-4 py-2.5 text-[13px] font-semibold text-brand-strong disabled:opacity-60">
              {{ uploadingSig ? 'در حال بارگذاری…' : (signatureUrl ? 'تغییر امضا' : 'بارگذاری امضا') }}
            </button>
            <p class="mt-2 text-[11.5px] text-ink-500">تصویر PNG با پس‌زمینهٔ شفاف پیشنهاد می‌شود.</p>
          </div>
        </div>
      </AppCard>

      <!-- Change password -->
      <AppCard style="grid-column: 1 / -1">
        <div class="mb-4 flex items-center gap-2 text-[15px] font-bold text-ink-900">🔒 تغییر رمز عبور</div>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">رمز عبور فعلی</span>
            <input v-model="pw.current" type="password" class="af-inp" autocomplete="current-password" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">رمز عبور جدید</span>
            <input v-model="pw.next" type="password" class="af-inp" autocomplete="new-password" />
          </label>
          <label class="flex flex-col gap-1">
            <span class="text-[12px] text-ink-600">تکرار رمز جدید</span>
            <input v-model="pw.confirm" type="password" class="af-inp" autocomplete="new-password" />
          </label>
        </div>
        <div class="mt-4">
          <button @click="changePassword" :disabled="savingPw"
            class="rounded-[10px] px-5 py-2.5 text-[13px] font-semibold text-white shadow-brand disabled:opacity-60" style="background: var(--grad-brand)">
            {{ savingPw ? 'در حال ذخیره…' : 'تغییر رمز عبور' }}
          </button>
        </div>
      </AppCard>
    </div>
  </div>
</template>

<style scoped>
.af-inp { height: 40px; border: 1px solid var(--color-ink-200); border-radius: 10px; padding: 0 11px; font-size: 13.5px; background: #fff; color: var(--color-ink-800); }
</style>
