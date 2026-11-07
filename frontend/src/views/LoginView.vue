<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { useToastStore } from '../stores/toast'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

const username = ref('test')
const password = ref('password')
const remember = ref(true)
const loading = ref(false)

async function submit() {
  if (loading.value) return
  loading.value = true
  try {
    await auth.login(username.value, password.value, remember.value)
    router.push('/dashboard')
  } catch (e) {
    toast.show(e.message || 'ورود ناموفق بود.', 'danger')
  } finally {
    loading.value = false
  }
}
</script>

<template>
  <div class="relative flex min-h-screen flex-col overflow-hidden" style="background: var(--grad-brand)">
    <div class="pointer-events-none absolute -top-32 -right-20 h-[460px] w-[460px] rounded-full"
      style="background: radial-gradient(circle, rgba(255,255,255,0.22), transparent 65%)" />
    <div class="pointer-events-none absolute -bottom-40 left-28 h-[520px] w-[520px] rounded-full"
      style="background: radial-gradient(circle, rgba(0,174,195,0.28), transparent 65%)" />

    <div class="relative z-10 grid flex-1 items-center gap-10 px-8 py-12 md:grid-cols-2 md:px-[72px]">
      <div class="flex justify-center">
        <form
          @submit.prevent="submit"
          class="w-full max-w-[430px] rounded-[28px] border p-9 shadow-pop"
          style="background: var(--glass-bg); border-color: var(--glass-border); backdrop-filter: blur(16px) saturate(1.3)"
        >
          <div class="mb-7 text-center">
            <h1 class="m-0 text-3xl font-bold text-white">ورود کاربران</h1>
            <p class="mt-2 text-sm text-white/80">سامانه یکپارچه اتوماسیون اداری</p>
          </div>
          <div class="flex flex-col gap-4">
            <input v-model="username" placeholder="نام کاربری"
              class="h-[46px] w-full rounded-xl border px-3.5 text-sm text-white placeholder:text-white/60"
              style="border-color: var(--glass-border); background: rgba(255,255,255,0.16)" />
            <input v-model="password" type="password" placeholder="رمز عبور"
              class="h-[46px] w-full rounded-xl border px-3.5 text-sm text-white placeholder:text-white/60"
              style="border-color: var(--glass-border); background: rgba(255,255,255,0.16)" />
            <div class="flex items-center justify-between text-[13px] text-white/85">
              <label class="flex cursor-pointer items-center gap-1.5">
                <input type="checkbox" v-model="remember" /> مرا به خاطر بسپار
              </label>
              <a href="#" class="font-medium text-gold-300 no-underline">فراموشی رمز؟</a>
            </div>
            <button type="submit" :disabled="loading"
              class="flex h-[50px] w-full items-center justify-center gap-2 rounded-xl text-[15px] font-bold shadow-gold disabled:opacity-70"
              style="background: var(--grad-gold); color: #3a2a02">
              {{ loading ? 'در حال ورود…' : 'ورود به سامانه ‹' }}
            </button>
            <p class="text-center text-[11.5px] text-white/70">
              حساب تست مدیرعامل: <span class="num">test</span> / <span class="num">password</span>
            </p>
          </div>
        </form>
      </div>
      <div class="text-center">
        <img src="/assets/logo-full-white.png" alt="گروه متحد 7" class="mx-auto w-[min(440px,82%)]" />
      </div>
    </div>

    <div class="relative z-10 flex items-center justify-between border-t px-10 py-3.5"
      style="background: rgba(9,24,58,0.34); border-color: rgba(255,255,255,0.14); backdrop-filter: blur(6px)">
      <span class="text-[13px] text-white/70">© ۱۴۰۳ گروه متحد 7 · آرتا قلب بهسان طب</span>
      <span class="inline-flex items-center gap-2 text-[13px] text-white">🌐 فارسی</span>
    </div>
  </div>
</template>
