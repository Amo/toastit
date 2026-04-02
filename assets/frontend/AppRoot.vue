<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { authState, authStore } from './authStore';
import { ToastitApiClient } from './api/ToastitApiClient';
import { AuthApi } from './api/auth';
import AppShell from './components/AppShell.vue';
import AuthMagicPage from './components/AuthMagicPage.vue';
import LoginPage from './components/LoginPage.vue';
import AuthVerifyPage from './components/AuthVerifyPage.vue';
import PinSetupPage from './components/PinSetupPage.vue';
import PinUnlockPage from './components/PinUnlockPage.vue';
import DashboardPage from './components/DashboardPage.vue';
import WorkspacePage from './components/WorkspacePage.vue';
import ProfilePage from './components/ProfilePage.vue';
import { useSpaContext } from './spaContext';

const props = defineProps({
  bootstrap: { type: Object, required: true },
});

const router = useRouter();
const route = useRoute();
const spa = useSpaContext();
const routeName = computed(() => route.name);
let pinLockTimerId = null;
let accessRefreshTimerId = null;
const authRefreshPending = ref(false);
const authApi = new AuthApi(new ToastitApiClient(''));

const protectedRouteNames = ['dashboard', 'workspace', 'toast', 'profile'];
const accessTokenExpired = computed(() => {
  const expiresAt = authStore.getAccessTokenExpiresAt();

  if (!expiresAt) {
    return false;
  }

  return (expiresAt * 1000) <= Date.now();
});

const clearPinLockTimer = () => {
  if (null !== pinLockTimerId) {
    window.clearTimeout(pinLockTimerId);
    pinLockTimerId = null;
  }
};

const clearAccessRefreshTimer = () => {
  if (null !== accessRefreshTimerId) {
    window.clearTimeout(accessRefreshTimerId);
    accessRefreshTimerId = null;
  }
};

const handleManualLock = () => {
  authStore.setReturnToPath(route.fullPath);
  authStore.lock();
  router.replace('/pin/unlock');
};

const handleApiActivity = () => {
  if (!authStore.isAuthenticated) {
    return;
  }

  authStore.bumpPinLock();
};

const redirectToPinUnlock = () => {
  if (!protectedRouteNames.includes(String(routeName.value))) {
    return;
  }

  authStore.lock();
  router.replace('/pin/unlock');
};

const syncPinLock = () => {
  clearPinLockTimer();

  if (!authState.user || !protectedRouteNames.includes(String(routeName.value)) || !authState.pinLockExpiresAt) {
    return;
  }

  const remainingMs = (Number(authState.pinLockExpiresAt) * 1000) - Date.now();

  if (remainingMs <= 0) {
    redirectToPinUnlock();
    return;
  }

  pinLockTimerId = window.setTimeout(() => {
    redirectToPinUnlock();
  }, remainingMs);
};

const syncProtectedRoutes = () => {
  if (routeName.value === 'home' && !authState.accessToken && authState.refreshToken && authState.user) {
    router.replace('/pin/unlock');
    return;
  }

  if (routeName.value === 'pin-unlock' && !authState.pendingPinUnlockToken && (!authState.refreshToken || !authState.user)) {
    router.replace('/');
    return;
  }

  if (routeName.value === 'pin-setup' && !authState.pendingPinSetupToken) {
    router.replace('/');
    return;
  }

  if (!protectedRouteNames.includes(String(routeName.value))) {
    return;
  }

  if (!authState.accessToken && authState.refreshToken && authState.user) {
    authStore.setReturnToPath(route.fullPath);
    router.replace('/pin/unlock');
    return;
  }

  if (!authStore.isAuthenticated) {
    authStore.setReturnToPath(route.fullPath);
    router.replace('/');
  }
};

const dismissFlash = ({ type, index }) => {
  spa.removeFlash(type, index);
};

const syncAccessRefresh = () => {
  clearAccessRefreshTimer();

  if (!authStore.isAuthenticated || !authState.refreshToken) {
    return;
  }

  const accessExpiresAt = authStore.getAccessTokenExpiresAt();

  if (!accessExpiresAt) {
    authStore.logout();
    router.replace('/');
    return;
  }

  const remainingMs = (accessExpiresAt * 1000) - Date.now() - 60000;

  if (remainingMs <= 0) {
    authRefreshPending.value = true;
    authApi.refresh(authState.refreshToken).then(({ ok, data }) => {
      if (!ok || !data) {
        authStore.logout();
        router.replace('/');
        authRefreshPending.value = false;
        return;
      }

      authStore.setAuthenticated(data);
      authRefreshPending.value = false;
    });
    return;
  }

  accessRefreshTimerId = window.setTimeout(async () => {
    authRefreshPending.value = true;
    const { ok, data } = await authApi.refresh(authState.refreshToken);

    if (!ok || !data) {
      authStore.logout();
      router.replace('/');
      authRefreshPending.value = false;
      return;
    }

    authStore.setAuthenticated(data);
    authRefreshPending.value = false;
  }, remainingMs);
};

onMounted(() => {
  syncProtectedRoutes();
  syncPinLock();
  syncAccessRefresh();
  window.addEventListener('toastit:lock-session', handleManualLock);
  window.addEventListener('toastit:api-activity', handleApiActivity);
});

onUnmounted(() => {
  clearPinLockTimer();
  clearAccessRefreshTimer();
  window.removeEventListener('toastit:lock-session', handleManualLock);
  window.removeEventListener('toastit:api-activity', handleApiActivity);
});

watch(() => routeName.value, syncPinLock);
watch(() => route.fullPath, () => {
  spa.clearFlashes();
  syncProtectedRoutes();
});
watch(() => authState.pinLockExpiresAt, syncPinLock);
watch(() => authState.accessToken, syncProtectedRoutes);
watch(() => authState.accessToken, syncAccessRefresh);
</script>

<template>
  <AppShell
    v-if="routeName === 'home'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="null"
    :show-app-navigation="false"
    content-html=""
  >
    <LoginPage
      :email="bootstrap.email || authState.pendingEmail"
      :dashboard-url="spa.urls.dashboardUrl"
      :is-authenticated="authStore.isAuthenticated"
      :flashes="spa.flashes"
      @dismiss-flash="dismissFlash"
    />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'auth-verify'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="null"
    :show-app-navigation="false"
    content-html=""
  >
    <AuthVerifyPage
      :email="route.query.email ?? ''"
      :purpose="route.query.purpose ?? 'login'"
      :flashes="spa.flashes"
      @dismiss-flash="dismissFlash"
    />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'auth-magic'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="null"
    :show-app-navigation="false"
    content-html=""
  >
    <AuthMagicPage />
  </AppShell>

  <main v-else-if="authRefreshPending || (protectedRouteNames.includes(String(routeName)) && accessTokenExpired)" class="toastit-shell">
    <section class="tw-toastit-card mx-auto w-full max-w-xl p-8">
      <p class="text-sm text-stone-500">Refreshing session...</p>
    </section>
  </main>

  <AppShell
    v-else-if="routeName === 'pin-setup'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="null"
    :show-app-navigation="false"
    content-html=""
  >
    <PinSetupPage :flashes="spa.flashes" @dismiss-flash="dismissFlash" />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'pin-unlock'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="null"
    :show-app-navigation="false"
    content-html=""
  >
    <PinUnlockPage
      :email="authState.pendingEmail || authState.user?.email || ''"
      :flashes="spa.flashes"
      @dismiss-flash="dismissFlash"
    />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'dashboard'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="authState.user"
    content-html=""
  >
    <DashboardPage api-url="/api/dashboard" :access-token="authState.accessToken" />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'workspace'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="authState.user"
    content-html=""
  >
    <WorkspacePage :api-url="`/api/workspaces/${route.params.id}`" :dashboard-url="spa.urls.dashboardUrl" :access-token="authState.accessToken" />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'toast'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="authState.user"
    content-html=""
  >
    <WorkspacePage
      :api-url="`/api/toasts/${route.params.id}`"
      :dashboard-url="spa.urls.dashboardUrl"
      :access-token="authState.accessToken"
      :standalone-toast-id="route.params.id"
    />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'profile'"
    current-section="profile"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="authState.user"
    content-html=""
  >
    <ProfilePage api-url="/api/profile" update-url="/api/profile" delete-url="/api/profile" :access-token="authState.accessToken" />
  </AppShell>

  <main v-else class="toastit-shell">
    <section class="tw-toastit-card mx-auto w-full max-w-xl p-8">
      <p class="text-sm text-stone-500">Route non migree : {{ route.fullPath }}</p>
    </section>
  </main>
</template>
