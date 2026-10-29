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

  /* دراپ‌داون منو (کلیک — موبایل و دسکتاپ) */
  var toggles = document.querySelectorAll('.dd-toggle');
  Array.prototype.forEach.call(toggles, function (t) {
    t.addEventListener('click', function (e) {
      e.preventDefault();
      var li = t.closest('.has-sub');
      if (!li) return;
      var sub = li.querySelector('.subnav');
      var wasOpen = sub && sub.classList.contains('open');
      Array.prototype.forEach.call(document.querySelectorAll('.subnav.open'), function (s) { s.classList.remove('open'); });
      if (sub && !wasOpen) sub.classList.add('open');
    });
  });
  document.addEventListener('click', function (e) {
    if (!e.target.closest || !e.target.closest('.has-sub')) {
      Array.prototype.forEach.call(document.querySelectorAll('.subnav.open'), function (s) { s.classList.remove('open'); });
      var act = document.activeElement;
      if (act && act.classList && act.classList.contains('dd-toggle')) act.blur();
    }
  });

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
