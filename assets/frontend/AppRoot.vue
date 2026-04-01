<script setup>
import { computed } from 'vue';
import { useRoute } from 'vue-router';
import AppShell from './components/AppShell.vue';
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

const route = useRoute();
const spa = useSpaContext();
const routeName = computed(() => route.name);
</script>

<template>
  <LoginPage
    v-if="routeName === 'home'"
    :email="bootstrap.email"
    :login-action="spa.urls.loginAction"
    :dashboard-url="spa.urls.dashboardUrl"
    :logout-url="spa.urls.logoutUrl"
    :is-authenticated="bootstrap.isAuthenticated"
    :flashes="spa.flashes"
  />

  <AuthVerifyPage
    v-else-if="routeName === 'auth-verify'"
    :email="route.query.email ?? ''"
    :purpose="route.query.purpose ?? 'login'"
    :verify-action="spa.urls.verifyAction"
    :flashes="spa.flashes"
  />

  <PinSetupPage
    v-else-if="routeName === 'pin-setup'"
    :setup-action="spa.urls.setupAction"
    :flashes="spa.flashes"
  />

  <PinUnlockPage
    v-else-if="routeName === 'pin-unlock'"
    :email="bootstrap.email ?? ''"
    :unlock-action="spa.urls.unlockAction"
    :forgot-pin-action="spa.urls.forgotPinAction"
    :flashes="spa.flashes"
  />

  <AppShell
    v-else-if="routeName === 'dashboard'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :logout-url="spa.urls.logoutUrl"
    :user="spa.user"
    content-html=""
  >
    <DashboardPage api-url="/api/dashboard" :access-token="spa.accessToken" />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'workspace'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :logout-url="spa.urls.logoutUrl"
    :user="spa.user"
    content-html=""
  >
    <WorkspacePage :api-url="`/api/workspaces/${route.params.id}`" :dashboard-url="spa.urls.dashboardUrl" :access-token="spa.accessToken" />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'toast'"
    current-section="workspace"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :logout-url="spa.urls.logoutUrl"
    :user="spa.user"
    content-html=""
  >
    <WorkspacePage
      :api-url="`/api/toasts/${route.params.id}`"
      :dashboard-url="spa.urls.dashboardUrl"
      :access-token="spa.accessToken"
      :standalone-toast-id="route.params.id"
    />
  </AppShell>

  <AppShell
    v-else-if="routeName === 'profile'"
    current-section="profile"
    :dashboard-url="spa.urls.dashboardUrl"
    :profile-url="spa.urls.profileUrl"
    :logout-url="spa.urls.logoutUrl"
    :user="spa.user"
    content-html=""
  >
    <ProfilePage api-url="/api/profile" update-url="/api/profile" :access-token="spa.accessToken" />
  </AppShell>

  <main v-else class="toastit-shell">
    <section class="tw-toastit-card mx-auto w-full max-w-xl p-8">
      <p class="text-sm text-stone-500">Route non migree : {{ route.fullPath }}</p>
    </section>
  </main>
</template>
