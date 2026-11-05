(function () {
  const logo = document.querySelector('[data-logos]');
  if (logo) {
    const logos = JSON.parse(logo.dataset.logos);
    logo.onerror = () => {
      logo.onerror = null;
      logo.src = logos[0];
    };
    logo.src = logos[Math.floor(Math.random() * logos.length)];
  }

  const feedSwitch = document.querySelector('.feed-switch');
  if (feedSwitch) {
    document.documentElement.classList.add('enhanced');
    feedSwitch.hidden = false;
    document.querySelector('#feed-replies').hidden = true;
    feedSwitch.addEventListener('click', event => {
      const button = event.target.closest('button[data-feed]');
      if (!button) return;
      for (const tab of feedSwitch.querySelectorAll('button')) {
        const selected = tab === button;
        tab.setAttribute('aria-pressed', String(selected));
        document.getElementById(tab.getAttribute('aria-controls')).hidden = !selected;
      }
    });
  }

  if (navigator.clipboard && window.isSecureContext) {
    for (const button of document.querySelectorAll('[data-copy]')) {
      button.hidden = false;
      button.addEventListener('click', async () => {
        const status = document.querySelector('.copy-status');
        try {
          await navigator.clipboard.writeText(button.dataset.copy);
          button.textContent = 'Скопійовано';
          status.textContent = '';
          setTimeout(() => { button.textContent = 'Копіювати'; }, 1800);
        } catch {
          status.textContent = 'Не вдалося скопіювати. Виділи адресу й скопіюй її вручну.';
        }
      });
    }
  }

  const stats = document.querySelector('.stats');
  if (stats) {
    const toggle = stats.querySelector('.ticker-toggle');
    stats.classList.add('ticker-ready');
    toggle.hidden = false;
    toggle.addEventListener('click', () => {
      const paused = stats.classList.toggle('is-paused');
      const label = paused ? 'Продовжити рух метрик' : 'Призупинити рух метрик';
      toggle.setAttribute('aria-pressed', String(paused));
      toggle.setAttribute('aria-label', label);
      toggle.title = label;
      toggle.firstElementChild.textContent = paused ? '▶' : 'Ⅱ';
    });
  }

})();
