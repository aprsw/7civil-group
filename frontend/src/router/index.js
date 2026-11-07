import { createRouter, createWebHistory } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const routes = [
  { path: '/login', name: 'login', component: () => import('../views/LoginView.vue'), meta: { public: true } },
  {
    path: '/',
    component: () => import('../layouts/AppLayout.vue'),
    children: [
      { path: '', redirect: '/dashboard' },
      { path: 'dashboard', name: 'dashboard', component: () => import('../views/DashboardView.vue') },
      { path: 'profile', name: 'profile', component: () => import('../views/ProfileView.vue') },
      { path: 'cartable', name: 'cartable', component: () => import('../views/CartableView.vue'), meta: { permission: 'letters.view' } },
      { path: 'letters/:id', name: 'letter', component: () => import('../views/LetterView.vue'), meta: { permission: 'letters.view' } },
      { path: 'templates', name: 'templates', component: () => import('../views/TemplatesView.vue'), meta: { permission: 'templates.view' } },
      { path: 'office-files', name: 'office-files', component: () => import('../views/OfficeFilesView.vue'), meta: { permission: 'templates.manage' } },
      { path: 'personnel', name: 'personnel', component: () => import('../views/PersonnelView.vue'), meta: { permission: 'users.view' } },
      { path: 'departments', name: 'departments', component: () => import('../views/DepartmentsView.vue'), meta: { permission: 'departments.view' } },
      { path: 'roles', name: 'roles', component: () => import('../views/RolesView.vue'), meta: { permission: 'roles.manage' } },
      { path: 'access-grants', name: 'access-grants', component: () => import('../views/AccessGrantsView.vue'), meta: { permission: 'roles.manage' } },
      { path: 'messaging', name: 'messaging', component: () => import('../views/MessagingView.vue'), meta: { permission: 'messaging.manage' } },
      { path: 'events', name: 'events', component: () => import('../views/EventsView.vue') },
      { path: 'my-notes', name: 'my-notes', component: () => import('../views/UserNotesView.vue') },
      { path: 'chat', name: 'chat', component: () => import('../views/ChatView.vue'), meta: { permission: 'chat.use' } },
      { path: 'secretariat', name: 'secretariat', component: () => import('../views/SecretariatView.vue'), meta: { permission: 'secretariat.use' } },
      { path: 'admin-letters', name: 'admin-letters', component: () => import('../views/AdminLettersView.vue'), meta: { permission: 'departments.view' } },
      { path: 'archive', name: 'archive', component: () => import('../views/ArchiveView.vue'), meta: { permission: 'letters.view' } },
      { path: 'workflows', name: 'workflows', component: () => import('../views/WorkflowsView.vue'), meta: { permission: 'workflow.manage' } },
      { path: 'settings', name: 'settings', component: () => import('../views/SettingsView.vue'), meta: { permission: 'settings.manage' } },
    ],
  },
  // Full-screen editor (its own chrome, outside the app shell).
  { path: '/compose', name: 'compose', component: () => import('../views/ComposeView.vue') },
  { path: '/:pathMatch(.*)*', redirect: '/dashboard' },
]

const router = createRouter({
  history: createWebHistory(),
  routes,
})

router.beforeEach((to) => {
  const auth = useAuthStore()
  if (!to.meta.public && !auth.isAuthenticated) return { name: 'login' }
  if (to.name === 'login' && auth.isAuthenticated) return { name: 'dashboard' }
  // Per-route permission gate — direct-URL access to a view the role lacks
  // redirects to the dashboard (the API also enforces this server-side).
  if (to.meta.permission && !auth.can(to.meta.permission)) return { name: 'dashboard' }
  return true
})

export default router
