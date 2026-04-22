<script setup>
import { computed, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { authState, authStore } from './authStore';
import { ToastitApiClient } from './api/ToastitApiClient';
import { AuthApi } from './api/auth';
import AppShell from './components/AppShell.vue';
import AuthMagicPage from './components/AuthMagicPage.vue';
import EmailActionConfirmPage from './components/EmailActionConfirmPage.vue';
import HomePage from './components/HomePage.vue';
import LoginModalForm from './components/LoginModalForm.vue';
import ModalDialog from './components/ModalDialog.vue';
import ModalHeader from './components/ModalHeader.vue';
import AuthVerifyPage from './components/AuthVerifyPage.vue';
import PinSetupPage from './components/PinSetupPage.vue';
import PinUnlockPage from './components/PinUnlockPage.vue';
import DashboardPage from './components/DashboardPage.vue';
import WorkspacePage from './components/WorkspacePage.vue';
import ProfilePage from './components/ProfilePage.vue';
import AdminDashboardPage from './components/AdminDashboardPage.vue';
import AdminUsersPage from './components/AdminUsersPage.vue';
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
const loginModalOpen = ref(false);
const authApi = new AuthApi(new ToastitApiClient(''));

const protectedRouteNames = ['dashboard', 'inbox', 'inbox-create-toast', 'workspace', 'workspace-create-toast', 'toast', 'profile', 'admin-dashboard', 'admin-users'];
const rootRouteNames = ['admin-dashboard'];
const routeRouteNames = ['admin-users'];
const authEntryRouteNames = ['home', 'auth-verify', 'auth-magic', 'pin-setup', 'pin-unlock'];
const accessTokenExpired = computed(() => {
  const expiresAt = authStore.getAccessTokenExpiresAt();

  if (!expiresAt) {
    return false;
  }

  return (expiresAt * 1000) <= Date.now();
});

const dashboardToastReturnTo = computed(() => {
  const rawReturnTo = Array.isArray(route.query.returnTo) ? route.query.returnTo[0] : route.query.returnTo;
  const normalizedReturnTo = typeof rawReturnTo === 'string' ? rawReturnTo : '';

  if (!normalizedReturnTo.startsWith('/app')) {
    return '';
  }

  if (normalizedReturnTo.startsWith('/app/workspaces/') || normalizedReturnTo.startsWith('/app/inbox')) {
    return '';
  }

  return normalizedReturnTo;
});

const isDashboardToastRoute = computed(() => routeName.value === 'toast' && dashboardToastReturnTo.value !== '');
const dashboardToastOverlayId = computed(() => {
  if (routeName.value !== 'dashboard') {
    return null;
  }

  const rawToastId = Array.isArray(route.query.toastId) ? route.query.toastId[0] : route.query.toastId;
  const toastId = Number(rawToastId ?? 0);

  return Number.isFinite(toastId) && toastId > 0 ? toastId : null;
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

  if (rootRouteNames.includes(String(routeName.value)) && !authState.user?.isRoot) {
    router.replace('/app');
    return;
  }

  if (routeRouteNames.includes(String(routeName.value)) && !authState.user?.isRoot && !authState.user?.isRoute) {
    router.replace('/app');
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
  if (authStore.isAuthenticated && authEntryRouteNames.includes(String(routeName.value))) {
    router.replace('/app');
    return;
  }

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

const openLoginModal = () => {
  loginModalOpen.value = true;
};

const redirectFromNotFound = () => {
  window.location.href = authStore.isAuthenticated ? spa.urls.dashboardUrl : '/';
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
  loginModalOpen.value = false;
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
    public-cta-label="login"
    @public-cta-click="openLoginModal"
    content-html=""
  >
    <HomePage @open-login="openLoginModal" />
    <ModalDialog v-if="loginModalOpen" max-width-class="max-w-xl" @close="loginModalOpen = false">
      <ModalHeader
        eyebrow="Login"
        title="Continue with email"
        @close="loginModalOpen = false"
      />
      <LoginModalForm :email="bootstrap.email || authState.pendingEmail" />
    </ModalDialog>
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
    <AuthMagicPage :recaptcha-site-key="bootstrap.recaptchaSiteKey || ''" />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'email-action-confirm'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="null"
    :show-app-navigation="false"
    content-html=""
  >
    <EmailActionConfirmPage :token="String(route.params.token ?? '')" />
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
    v-else-if="routeName === 'admin-dashboard'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="authState.user"
    content-html=""
  >
    <AdminDashboardPage :access-token="authState.accessToken" />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'admin-users'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="authState.user"
    content-html=""
  >
    <AdminUsersPage :access-token="authState.accessToken" />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'dashboard' || isDashboardToastRoute"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="authState.user"
    content-html=""
  >
    <DashboardPage api-url="/api/dashboard" :access-token="authState.accessToken" />
    <WorkspacePage
      v-if="dashboardToastOverlayId"
      :api-url="`/api/toasts/${dashboardToastOverlayId}`"
      :dashboard-url="spa.urls.dashboardUrl"
      :access-token="authState.accessToken"
      :standalone-toast-id="dashboardToastOverlayId"
    />
    <WorkspacePage
      v-if="isDashboardToastRoute"
      :api-url="`/api/toasts/${route.params.id}`"
      :dashboard-url="spa.urls.dashboardUrl"
      :access-token="authState.accessToken"
      :standalone-toast-id="route.params.id"
    />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'inbox'"
    current-section="inbox"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="authState.user"
    content-html=""
  >
    <WorkspacePage api-url="/api/inbox/workspace" :dashboard-url="spa.urls.dashboardUrl" :access-token="authState.accessToken" />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'inbox-create-toast'"
    current-section="inbox"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="authState.user"
    content-html=""
  >
    <WorkspacePage
      api-url="/api/inbox/workspace"
      :dashboard-url="spa.urls.dashboardUrl"
      :access-token="authState.accessToken"
      :create-only-mode="true"
    />
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
    v-else-if="routeName === 'workspace-create-toast'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="authState.user"
    content-html=""
  >
    <WorkspacePage
      :api-url="`/api/workspaces/${route.params.id}`"
      :dashboard-url="spa.urls.dashboardUrl"
      :access-token="authState.accessToken"
      :create-only-mode="true"
    />
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
    <ProfilePage
      api-url="/api/profile"
      update-url="/api/profile"
      delete-url="/api/profile"
      :access-token="authState.accessToken"
      :public-api-doc-url="spa.urls.publicApiDocUrl"
    />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'not-found'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :user="authState.user"
    :show-app-navigation="authStore.isAuthenticated"
    content-html=""
  >
    <section class="mx-auto w-full max-w-xl rounded-3xl border border-stone-200 bg-white p-8 shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Error</p>
      <h1 class="mt-3 text-3xl font-semibold text-stone-950">404 - Page not found</h1>
      <p class="mt-3 text-sm text-stone-600">
        The requested path does not exist.
      </p>
      <p class="mt-2 rounded-xl bg-stone-50 px-3 py-2 text-xs text-stone-500">
        {{ route.fullPath }}
      </p>
      <button
        type="button"
        class="mt-6 inline-flex items-center rounded-full bg-amber-500 px-5 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
        @click="redirectFromNotFound"
      >
        Go back
      </button>
    </section>
  </AppShell>
</template>
