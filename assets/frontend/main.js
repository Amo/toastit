import { createApp } from 'vue';
import { createSpaRouter } from './router';
import { createSpaContext, spaContextKey } from './spaContext';
import 'flowbite';
import './styles/app.css';
import AppRoot from './AppRoot.vue';

document.querySelectorAll('[data-vue-root]').forEach((root) => {
  const bootstrap = root.dataset.props ? JSON.parse(root.dataset.props) : {};
  const router = createSpaRouter();
  const app = createApp(AppRoot, {
    bootstrap,
  });

  app.provide(spaContextKey, createSpaContext(bootstrap));
  app.use(router);

  router.isReady().then(() => {
    app.mount(root);
  });
});
