import { inject } from 'vue';

export const spaContextKey = Symbol('spa-context');

export const createSpaContext = (bootstrap) => ({
  bootstrap,
  get accessToken() {
    return this.bootstrap.accessToken ?? '';
  },
  get user() {
    return this.bootstrap.user ?? null;
  },
  get flashes() {
    return this.bootstrap.flashes ?? { success: [], error: [] };
  },
  get urls() {
    return {
      loginAction: this.bootstrap.loginAction ?? '/connexion',
      verifyAction: this.bootstrap.verifyAction ?? '/connexion/verifier',
      setupAction: this.bootstrap.setupAction ?? '/pin/setup',
      unlockAction: this.bootstrap.unlockAction ?? '/pin/unlock',
      forgotPinAction: this.bootstrap.forgotPinAction ?? '/pin/forgot',
      dashboardUrl: '/app',
      profileUrl: '/app/profile',
      logoutUrl: this.bootstrap.logoutUrl ?? '/logout',
    };
  },
});

export const useSpaContext = () => {
  const context = inject(spaContextKey);

  if (!context) {
    throw new Error('SPA context missing');
  }

  return context;
};
