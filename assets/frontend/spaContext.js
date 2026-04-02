import { inject, reactive } from 'vue';

export const spaContextKey = Symbol('spa-context');

export const createSpaContext = (bootstrap) => {
  const state = reactive({
    flashes: {
      success: [...(bootstrap.flashes?.success ?? [])],
      error: [...(bootstrap.flashes?.error ?? [])],
    },
  });

  return {
    bootstrap,
    state,
    get flashes() {
      return state.flashes;
    },
    removeFlash(type, index) {
      if (!Array.isArray(state.flashes[type])) {
        return;
      }

      state.flashes[type].splice(index, 1);
    },
    clearFlashes() {
      state.flashes.success = [];
      state.flashes.error = [];
    },
    get urls() {
      return {
        dashboardUrl: '/app',
        profileUrl: '/app/profile',
      };
    },
  };
};

export const useSpaContext = () => {
  const context = inject(spaContextKey);

  if (!context) {
    throw new Error('SPA context missing');
  }

  return context;
};
