import { createRouter, createWebHistory } from 'vue-router';

export const createSpaRouter = () => createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', name: 'home' },
    { path: '/connexion/verifier', name: 'auth-verify' },
    { path: '/pin/setup', name: 'pin-setup' },
    { path: '/pin/unlock', name: 'pin-unlock' },
    { path: '/app', name: 'dashboard' },
    { path: '/app/teams/:id', name: 'team' },
    { path: '/app/teams/:id/archives', name: 'team-archives' },
    { path: '/app/meetings/:id', name: 'meeting' },
    { path: '/app/profile', name: 'profile' },
  ],
});
