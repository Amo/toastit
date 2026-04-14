import { createRouter, createWebHistory } from 'vue-router';

export const createSpaRouter = () => createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home' },
    { path: '/connexion/verifier', name: 'auth-verify' },
    { path: '/connexion/magic/:selector/:token', name: 'auth-magic' },
    { path: '/email/action/:token', name: 'email-action-confirm' },
    { path: '/pin/setup', name: 'pin-setup' },
    { path: '/pin/unlock', name: 'pin-unlock' },
    { path: '/admin', name: 'admin-dashboard' },
    { path: '/admin/users', name: 'admin-users' },
    { path: '/admin/prompts', name: 'admin-prompts' },
    { path: '/app', name: 'dashboard' },
    { path: '/app/inbox', name: 'inbox' },
    { path: '/app/inbox/new-toast', name: 'inbox-create-toast' },
    { path: '/app/workspaces/:id', name: 'workspace' },
    { path: '/app/workspaces/:id/new-toast', name: 'workspace-create-toast' },
    { path: '/app/toasts/:id', name: 'toast' },
    { path: '/app/profile', name: 'profile' },
    { path: '/:pathMatch(.*)*', name: 'not-found' },
  ],
});
