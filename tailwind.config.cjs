module.exports = {
  content: [
    './templates/**/*.twig',
    './assets/frontend/**/*.{js,vue}',
  ],
  theme: {
    extend: {
      colors: {
        toastit: {
          ink: '#1f1209',
          sand: '#fff7e7',
          accent: '#d89f10',
          paper: '#fffdf9',
        },
      },
      boxShadow: {
        'toastit-panel': '0 18px 42px rgba(98, 64, 29, 0.08)',
      },
      borderRadius: {
        'toastit-xl': '1.5rem',
      },
    },
  },
  plugins: [
    require('flowbite/plugin'),
  ],
};
