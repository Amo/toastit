import { createRouter, createWebHistory } from 'vue-router';

export const createSpaRouter = () => createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home' },
    { path: '/connexion/verifier', name: 'auth-verify' },
    { path: '/pin/setup', name: 'pin-setup' },
    { path: '/pin/unlock', name: 'pin-unlock' },
    { path: '/app', name: 'dashboard' },
    { path: '/app/workspaces/:id', name: 'workspace' },
    { path: '/app/profile', name: 'profile' },
  ],
});
