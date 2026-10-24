'use strict';
(() => {
  const element = document.getElementById('dashboard-config');
  if (!element) return;
  const data = JSON.parse(element.textContent);
  for (const key of ['sessionInfo', 'enabledPlugins', 'dashboardStats', 'jsTranslations']) window[key] = data[key];
})();
