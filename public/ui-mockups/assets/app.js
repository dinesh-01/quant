/* Quanta static mockups — shared behaviour + icon sprite.
   No framework. Icons are Lucide-style stroke paths injected once as an
   SVG <symbol> sprite so every page can do <svg class="ico"><use href="#i-x"/></svg>. */

const ICONS = {
  'dashboard': '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
  'folder': '<path d="M3 7a2 2 0 0 1 2-2h4l2 2h8a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>',
  'cases': '<path d="M8 3H6a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V5a2 2 0 0 0-2-2h-2"/><rect x="8" y="2" width="8" height="4" rx="1"/><path d="M9 12l2 2 4-4"/>',
  'requirements': '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="M8 11h8M8 15h5"/>',
  'plan': '<path d="M12 2 2 7l10 5 10-5-10-5Z"/><path d="m2 17 10 5 10-5M2 12l10 5 10-5"/>',
  'play': '<circle cx="12" cy="12" r="9"/><path d="M10 8.5 15.5 12 10 15.5z" fill="currentColor" stroke="none"/>',
  'chart': '<path d="M3 3v18h18"/><path d="M7 15v3M12 10v8M17 6v12"/>',
  'git': '<circle cx="6" cy="6" r="2.5"/><circle cx="6" cy="18" r="2.5"/><circle cx="18" cy="9" r="2.5"/><path d="M6 8.5v7M18 11.5c0 3-4 3-6 3.5"/>',
  'bug': '<path d="M8 6a4 4 0 0 1 8 0"/><rect x="6" y="8" width="12" height="10" rx="6"/><path d="M4 12h2M18 12h2M4 17h3M17 17h3M4 7h2M18 7h2M12 8v10"/>',
  'settings': '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-2.82 1.17V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 8 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 3.6 15H3.5a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 5 8.6l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 3.6V3.5a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 2.82 1.17l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06A1.65 1.65 0 0 0 20.4 9h.1a2 2 0 0 1 0 4h-.1a1.65 1.65 0 0 0-1 2z"/>',
  'users': '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.5 5.5 0 0 1 11 0"/><path d="M16 5.2a3 3 0 0 1 0 5.6M21 20a5 5 0 0 0-4-4.9"/>',
  'shield': '<path d="M12 3 5 6v5c0 4.5 3 7.7 7 9 4-1.3 7-4.5 7-9V6z"/><path d="m9.5 12 1.8 1.8 3.5-3.6"/>',
  'search': '<circle cx="11" cy="11" r="7"/><path d="m21 21-4.3-4.3"/>',
  'bell': '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.7 21a2 2 0 0 1-3.4 0"/>',
  'plus': '<path d="M12 5v14M5 12h14"/>',
  'filter': '<path d="M3 5h18l-7 8v5l-4 2v-7z"/>',
  'chev-down': '<path d="m6 9 6 6 6-6"/>',
  'chev-right': '<path d="m9 6 6 6-6 6"/>',
  'check': '<path d="M20 6 9 17l-5-5"/>',
  'x': '<path d="M18 6 6 18M6 6l12 12"/>',
  'more': '<circle cx="5" cy="12" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="19" cy="12" r="1.6"/>',
  'download': '<path d="M12 3v12m0 0 4-4m-4 4-4-4"/><path d="M4 17v2a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-2"/>',
  'paperclip': '<path d="M21 8.5 12 17.5a4 4 0 0 1-6-5.3l8-8a2.7 2.7 0 0 1 4 3.6l-8 8a1.3 1.3 0 0 1-2-1.7l7-7"/>',
  'link': '<path d="M10 13a5 5 0 0 0 7 0l2-2a5 5 0 0 0-7-7l-1 1"/><path d="M14 11a5 5 0 0 0-7 0l-2 2a5 5 0 0 0 7 7l1-1"/>',
  'calendar': '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 9h18M8 3v4M16 3v4"/>',
  'clock': '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3 2"/>',
  'tag': '<path d="M20 12 12 20l-8-8V4h8z"/><circle cx="8" cy="8" r="1.4" fill="currentColor"/>',
  'edit': '<path d="M12 20h9"/><path d="M16.5 3.5a2 2 0 0 1 3 3L7 19l-4 1 1-4z"/>',
  'copy': '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
  'lock': '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/>',
  'arrow-right': '<path d="M5 12h14M13 6l6 6-6 6"/>',
  'flag': '<path d="M4 21V4M4 4h13l-2 4 2 4H4"/>',
  'trend-up': '<path d="M3 17 9 11l4 4 8-8"/><path d="M17 7h4v4"/>',
  'zap': '<path d="M13 2 4 14h7l-1 8 9-12h-7z"/>',
  'target': '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="5"/><circle cx="12" cy="12" r="1.4" fill="currentColor"/>',
  'sparkles': '<path d="M12 3l1.7 4.3L18 9l-4.3 1.7L12 15l-1.7-4.3L6 9l4.3-1.7z"/><path d="M18 14l.9 2.1L21 17l-2.1.9L18 20l-.9-2.1L15 17l2.1-.9z"/>',
  'send': '<path d="M22 2 11 13"/><path d="M22 2 15 22l-4-9-9-4z"/>',
  'bookmark': '<path d="M6 3h12a1 1 0 0 1 1 1v17l-7-4-7 4V4a1 1 0 0 1 1-1z"/>',
};

function injectSprite() {
  const ns = 'http://www.w3.org/2000/svg';
  const svg = document.createElementNS(ns, 'svg');
  svg.setAttribute('style', 'position:absolute;width:0;height:0;overflow:hidden');
  svg.setAttribute('aria-hidden', 'true');
  let defs = '';
  for (const [id, path] of Object.entries(ICONS)) {
    defs += `<symbol id="i-${id}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">${path}</symbol>`;
  }
  svg.innerHTML = defs;
  document.body.prepend(svg);
}

/* small interactions used across pages */
function wireInteractions() {
  // segmented controls & filters: toggle active within their group
  document.querySelectorAll('[data-seg]').forEach(group => {
    group.addEventListener('click', e => {
      const btn = e.target.closest('button');
      if (!btn) return;
      group.querySelectorAll('button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });

  // execution verdict buttons (single choice)
  document.querySelectorAll('[data-verdict-group]').forEach(group => {
    group.addEventListener('click', e => {
      const btn = e.target.closest('[data-verdict]');
      if (!btn) return;
      group.querySelectorAll('[data-verdict]').forEach(b => b.classList.remove('on'));
      btn.classList.add('on');
    });
  });

  // single-select choice pickers (priority, etc.)
  document.querySelectorAll('[data-choice]').forEach(group => {
    group.addEventListener('click', e => {
      const btn = e.target.closest('.opt-btn');
      if (!btn) return;
      group.querySelectorAll('.opt-btn').forEach(b => b.classList.remove('on'));
      btn.classList.add('on');
    });
  });

  // step builder: add + remove + renumber
  function renumber(list) {
    list.querySelectorAll('.builder-step').forEach((s, i) => {
      const h = s.querySelector('.handle'); if (h) h.textContent = i + 1;
    });
  }
  document.querySelectorAll('[data-step-list]').forEach(list => {
    list.addEventListener('click', e => {
      const del = e.target.closest('.del');
      if (del) {
        if (list.querySelectorAll('.builder-step').length > 1) del.closest('.builder-step').remove();
        renumber(list);
      }
    });
  });
  document.querySelectorAll('[data-add-step]').forEach(btn => {
    btn.addEventListener('click', () => {
      const list = document.querySelector(`[data-step-list="${btn.getAttribute('data-add-step')}"]`);
      const first = list.querySelector('.builder-step');
      const clone = first.cloneNode(true);
      clone.querySelectorAll('textarea, input').forEach(f => (f.value = ''));
      list.appendChild(clone);
      renumber(list);
      clone.querySelector('textarea, input')?.focus();
    });
  });

  // toggle switches
  document.querySelectorAll('.switch').forEach(s => s.addEventListener('click', () => s.classList.toggle('on')));

  // docked AI chat: clicking a suggestion chip fills the composer input
  document.querySelectorAll('.chat-chip').forEach(chip => {
    chip.addEventListener('click', () => {
      const dock = chip.closest('.ai-dock');
      const input = dock && dock.querySelector('.ai-inputbar input');
      if (input) { input.value = chip.getAttribute('data-chat-fill') || chip.textContent.trim(); input.focus(); }
    });
  });

  // Code Tracker: client-side filtering of the module table by repo / automation / owner
  const covRows = document.querySelector('[data-cov-rows]');
  if (covRows) {
    const selects = document.querySelectorAll('[data-cov-filter]');
    const countEl = document.querySelector('[data-cov-count]');
    const apply = () => {
      const f = {};
      selects.forEach(s => (f[s.getAttribute('data-cov-filter')] = s.value));
      let shown = 0;
      covRows.querySelectorAll('tr[data-repo]').forEach(tr => {
        const ok = Object.entries(f).every(([k, v]) => v === 'all' || tr.getAttribute('data-' + k) === v);
        tr.style.display = ok ? '' : 'none';
        if (ok) shown++;
      });
      if (countEl) countEl.textContent = shown + ' module' + (shown === 1 ? '' : 's');
    };
    selects.forEach(s => s.addEventListener('change', apply));
    apply();
  }

  // Ask AI: suggestion chips fill the prompt; sending reveals the demo result
  const aiInput = document.querySelector('[data-ai-input]');
  const aiResult = document.querySelector('[data-ai-result]');
  function aiShow() { if (aiResult) { aiResult.style.display = ''; aiResult.scrollIntoView({ behavior: 'smooth', block: 'start' }); } }
  document.querySelectorAll('[data-ai-fill]').forEach(chip => {
    chip.addEventListener('click', () => {
      if (aiInput) { aiInput.value = chip.getAttribute('data-ai-fill'); aiInput.focus(); }
      aiShow();
    });
  });
  document.querySelectorAll('[data-ai-send]').forEach(btn => btn.addEventListener('click', aiShow));
  if (aiInput) aiInput.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); aiShow(); } });

  // generic tab panels: [data-tab] buttons control [data-panel]
  document.querySelectorAll('[data-tabs]').forEach(host => {
    const panelScope = host.getAttribute('data-tabs');
    host.querySelectorAll('[data-tab]').forEach(tab => {
      tab.addEventListener('click', () => {
        const key = tab.getAttribute('data-tab');
        host.querySelectorAll('[data-tab]').forEach(t => t.classList.remove('active'));
        tab.classList.add('active');
        document.querySelectorAll(`[data-panel="${panelScope}"]`).forEach(p => {
          p.style.display = p.getAttribute('data-key') === key ? '' : 'none';
        });
      });
    });
  });
}

document.addEventListener('DOMContentLoaded', () => {
  injectSprite();
  wireInteractions();
});
