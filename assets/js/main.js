/* خبررسانی منطقه آزاد — اسکریپت عمومی */
(function () {
  'use strict';

  /* تم روز/شب — data-theme روی <html> */
  var btn = document.getElementById('themeBtn');
  if (btn) {
    var cur = function () {
      return localStorage.getItem('theme') ||
        (matchMedia('(prefers-color-scheme:dark)').matches ? 'dark' : 'auto');
    };
    var icon = function () {
      var dark = document.documentElement.dataset.theme === 'dark';
      btn.textContent = dark ? '☀️' : '🌒';
    };
    icon();
    btn.addEventListener('click', function () {
      var dark = document.documentElement.dataset.theme === 'dark';
      var next = dark ? 'light' : 'dark';
      localStorage.setItem('theme', next);
      if (next === 'dark') document.documentElement.dataset.theme = 'dark';
      else delete document.documentElement.dataset.theme;
      icon();
    });
  }

  /* منوی موبایل */
  var menuBtn = document.getElementById('menuBtn');
  if (menuBtn) {
    menuBtn.addEventListener('click', function () {
      var nav = document.querySelector('.mainnav');
      if (nav) nav.classList.toggle('open');
    });
  }

  /* کپی لینک مقاله */
  window.copyLink = function () {
    var u = location.href;
    if (navigator.clipboard) {
      navigator.clipboard.writeText(u).then(function () {
        var b = document.getElementById('copyBtn');
        if (b) { var t = b.textContent; b.textContent = '✅'; setTimeout(function () { b.textContent = t; }, 1500); }
      });
    } else {
      var i = document.createElement('input');
      i.value = u; document.body.appendChild(i); i.select(); document.execCommand('copy'); i.remove();
    }
  };
})();
