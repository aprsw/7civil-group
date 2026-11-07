<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useToastStore } from './stores/toast'
import { useBrandingStore } from './stores/branding'
import AppToast from './components/ui/AppToast.vue'
import ConfirmModal from './components/ui/ConfirmModal.vue'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()
const branding = useBrandingStore()

function onUnauthorized() {
  if (!auth.isAuthenticated) return
  auth.logout()
  toast.show('نشست شما منقضی شد. دوباره وارد شوید.', 'danger')
  router.push('/login')
}

onMounted(() => {
  auth.refresh()
  // Public endpoint — the login screen needs it before anyone signs in.
  branding.load()
  window.addEventListener('civil7:unauthorized', onUnauthorized)
})
onUnmounted(() => window.removeEventListener('civil7:unauthorized', onUnauthorized))
</script>

<template>
  <router-view />
  <AppToast />
  <ConfirmModal />
</template>
