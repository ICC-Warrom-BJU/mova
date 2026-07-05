/**
 * MOVA — Searchable Select (vanilla, tanpa dependency).
 * Meng-enhance <select> menjadi dropdown ber-search.
 *
 * Aturan:
 *  - Otomatis pada <select> dengan > 5 opsi (dropdown panjang).
 *  - Lewati: <select multiple>, [data-no-search], atau yang sudah di-enhance.
 *  - Native <select> tetap ada (opacity 0, focusable) sehingga `required`
 *    & submit form tetap tervalidasi browser. Klik diarahkan ke UI custom.
 */
(function () {
  'use strict';

  function enhance(select) {
    if (select.dataset.ssDone || select.multiple || select.hasAttribute('data-no-search')) return;
    if (select.options.length <= 5) return;
    select.dataset.ssDone = '1';

    const wrap = document.createElement('div');
    wrap.className = 'ss';
    select.parentNode.insertBefore(wrap, select);
    wrap.appendChild(select);

    const control = document.createElement('button');
    control.type = 'button';
    control.className = 'ss-control';
    control.innerHTML = '<span class="ss-value"></span><span class="ss-caret" aria-hidden="true">▾</span>';
    wrap.appendChild(control);

    const panel = document.createElement('div');
    panel.className = 'ss-panel';
    panel.hidden = true;
    panel.innerHTML = '<input type="text" class="ss-search" placeholder="Cari..." autocomplete="off"><ul class="ss-list" role="listbox"></ul>';
    wrap.appendChild(panel);

    const search = panel.querySelector('.ss-search');
    const list = panel.querySelector('.ss-list');
    const valueEl = control.querySelector('.ss-value');

    function buildList() {
      list.innerHTML = '';
      Array.from(select.options).forEach(function (opt, i) {
        const li = document.createElement('li');
        li.className = 'ss-option';
        li.textContent = opt.textContent;
        li.dataset.index = i;
        li.setAttribute('role', 'option');
        if (opt.disabled) li.classList.add('is-disabled');
        if (i === select.selectedIndex) li.classList.add('is-selected');
        list.appendChild(li);
      });
    }

    function syncLabel() {
      const opt = select.options[select.selectedIndex];
      const txt = opt ? opt.textContent.trim() : '';
      const isPlaceholder = !opt || opt.value === '';
      valueEl.textContent = txt || 'Pilih...';
      valueEl.classList.toggle('is-placeholder', isPlaceholder);
    }

    function filter(q) {
      q = q.trim().toLowerCase();
      let firstVisible = null;
      list.querySelectorAll('.ss-option').forEach(function (li) {
        const show = li.textContent.toLowerCase().indexOf(q) !== -1;
        li.style.display = show ? '' : 'none';
        if (show && firstVisible === null) firstVisible = li;
      });
      list.querySelectorAll('.ss-option.is-active').forEach(function (li) { li.classList.remove('is-active'); });
      if (firstVisible) firstVisible.classList.add('is-active');
    }

    function open() {
      buildList();
      panel.hidden = false;
      wrap.classList.add('is-open');
      search.value = '';
      filter('');
      const sel = list.querySelector('.ss-option.is-selected');
      if (sel) { list.querySelectorAll('.is-active').forEach(function (x){x.classList.remove('is-active');}); sel.classList.add('is-active'); sel.scrollIntoView({block:'nearest'}); }
      setTimeout(function () { search.focus(); }, 0);
    }

    function close() {
      panel.hidden = true;
      wrap.classList.remove('is-open');
    }

    function choose(index) {
      const opt = select.options[index];
      if (!opt || opt.disabled) return;
      select.selectedIndex = index;
      select.dispatchEvent(new Event('change', { bubbles: true }));
      syncLabel();
      close();
      control.focus();
    }

    control.addEventListener('click', function () { panel.hidden ? open() : close(); });
    control.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowDown' || e.key === 'Enter' || e.key === ' ') { e.preventDefault(); open(); }
    });

    search.addEventListener('input', function () { filter(search.value); });
    search.addEventListener('keydown', function (e) {
      const visible = Array.from(list.querySelectorAll('.ss-option')).filter(function (li) { return li.style.display !== 'none' && !li.classList.contains('is-disabled'); });
      let active = list.querySelector('.ss-option.is-active');
      let idx = visible.indexOf(active);
      if (e.key === 'ArrowDown') { e.preventDefault(); idx = Math.min(idx + 1, visible.length - 1); }
      else if (e.key === 'ArrowUp') { e.preventDefault(); idx = Math.max(idx - 1, 0); }
      else if (e.key === 'Enter') { e.preventDefault(); if (active) choose(parseInt(active.dataset.index, 10)); return; }
      else if (e.key === 'Escape') { e.preventDefault(); close(); control.focus(); return; }
      else return;
      if (active) active.classList.remove('is-active');
      if (visible[idx]) { visible[idx].classList.add('is-active'); visible[idx].scrollIntoView({ block: 'nearest' }); }
    });

    list.addEventListener('click', function (e) {
      const li = e.target.closest('.ss-option');
      if (li && !li.classList.contains('is-disabled')) choose(parseInt(li.dataset.index, 10));
    });
    list.addEventListener('mousemove', function (e) {
      const li = e.target.closest('.ss-option');
      if (!li) return;
      list.querySelectorAll('.is-active').forEach(function (x) { x.classList.remove('is-active'); });
      li.classList.add('is-active');
    });

    document.addEventListener('click', function (e) { if (!wrap.contains(e.target)) close(); });
    // native change (mis. reset / diubah script) → sync label
    select.addEventListener('change', syncLabel);

    syncLabel();
  }

  function init(root) {
    (root || document).querySelectorAll('select.form-control, select[data-search]').forEach(enhance);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () { init(); });
  } else {
    init();
  }
  window.MovaSelectSearch = { init: init, enhance: enhance };
})();
