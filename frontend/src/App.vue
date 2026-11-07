<script setup>
import { onMounted, onUnmounted } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from './stores/auth'
import { useToastStore } from './stores/toast'
import AppToast from './components/ui/AppToast.vue'
import ConfirmModal from './components/ui/ConfirmModal.vue'

const router = useRouter()
const auth = useAuthStore()
const toast = useToastStore()

function onUnauthorized() {
  if (!auth.isAuthenticated) return
  auth.logout()
  toast.show('نشست شما منقضی شد. دوباره وارد شوید.', 'danger')
  router.push('/login')
}

onMounted(() => {
  auth.refresh()
  window.addEventListener('arta:unauthorized', onUnauthorized)
})
onUnmounted(() => window.removeEventListener('arta:unauthorized', onUnauthorized))
</script>

<template>
  <router-view />
  <AppToast />
  <ConfirmModal />
</template>
