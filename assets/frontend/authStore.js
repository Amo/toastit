import { reactive } from 'vue';

const STORAGE_KEY = 'toastit.auth';
const PIN_LOCK_TTL_SECONDS = 15 * 60;

const defaultState = () => ({
  accessToken: '',
  refreshToken: '',
  user: null,
  pinLockExpiresAt: null,
  pendingPinSetupToken: '',
  pendingPinUnlockToken: '',
  pendingEmail: '',
  returnToPath: sessionStorage.getItem('toastit.returnToPath') ?? '',
});

const loadState = () => {
  try {
    const raw = localStorage.getItem(STORAGE_KEY);
    if (!raw) {
      return defaultState();
    }

    const parsed = JSON.parse(raw);
    delete parsed.returnToPath;

    return { ...defaultState(), ...parsed };
  } catch {
    return defaultState();
  }
};

export const authState = reactive(loadState());

const persist = () => {
  localStorage.setItem(STORAGE_KEY, JSON.stringify(authState));
  sessionStorage.setItem('toastit.returnToPath', authState.returnToPath ?? '');
};

export const authStore = {
  get isAuthenticated() {
    return !!authState.accessToken && !!authState.user;
  },
  getAccessTokenExpiresAt() {
    if (!authState.accessToken) {
      return null;
    }

    try {
      const [, payload] = authState.accessToken.split('.');
      const decoded = JSON.parse(atob(payload.replace(/-/g, '+').replace(/_/g, '/')));
      return typeof decoded.exp === 'number' ? decoded.exp : null;
    } catch {
      return null;
    }
  },
  setAuthenticated(payload) {
    authState.accessToken = payload.accessToken ?? '';
    authState.refreshToken = payload.refreshToken ?? '';
    authState.user = payload.user ?? null;
    authState.pinLockExpiresAt = payload.pinLockExpiresAt ?? null;
    authState.pendingPinSetupToken = '';
    authState.pendingPinUnlockToken = '';
    authState.pendingEmail = '';
    persist();
  },
  bumpPinLock() {
    authState.pinLockExpiresAt = Math.floor(Date.now() / 1000) + PIN_LOCK_TTL_SECONDS;
    persist();
  },
  setPendingPinSetup(token, user = null, email = '') {
    authState.accessToken = '';
    authState.refreshToken = '';
    authState.pinLockExpiresAt = null;
    authState.pendingPinSetupToken = token ?? '';
    authState.pendingPinUnlockToken = '';
    authState.pendingEmail = email || user?.email || '';
    authState.user = user ?? authState.user;
    persist();
  },
  setPendingPinUnlock(token, user = null, email = '') {
    authState.accessToken = '';
    authState.refreshToken = '';
    authState.pinLockExpiresAt = null;
    authState.pendingPinUnlockToken = token ?? '';
    authState.pendingPinSetupToken = '';
    authState.pendingEmail = email || user?.email || '';
    authState.user = user ?? authState.user;
    persist();
  },
  setReturnToPath(path) {
    authState.returnToPath = path;
    persist();
  },
  consumeReturnToPath() {
    const path = authState.returnToPath || '/app';
    authState.returnToPath = '';
    persist();
    return path;
  },
  clearSession() {
    authState.accessToken = '';
    authState.refreshToken = '';
    authState.user = null;
    authState.pinLockExpiresAt = null;
    persist();
  },
  lock() {
    authState.accessToken = '';
    authState.pinLockExpiresAt = null;
    authState.pendingPinUnlockToken = '';
    persist();
  },
  logout() {
    Object.assign(authState, defaultState());
    persist();
  },
};
