(() => {
  'use strict';
  document.querySelector('.github-connect')?.remove();
  const topActions = document.querySelector('.top-actions');
  if (topActions && !topActions.querySelector('.github-connect')) {
    const github = document.createElement('a');
    github.className = 'github-connect';
    github.href = 'https://github.com/';
    github.target = '_blank';
    github.rel = 'noopener';
    github.setAttribute('aria-label', 'Connect GitHub account');
    github.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-3.16 19.49c.5.09.68-.22.68-.48v-1.68c-2.78.6-3.37-1.18-3.37-1.18-.46-1.16-1.11-1.47-1.11-1.47-.91-.62.07-.61.07-.61 1 .07 1.53 1.03 1.53 1.03.9 1.53 2.35 1.09 2.92.83.09-.65.35-1.09.63-1.34-2.22-.25-4.56-1.11-4.56-4.94 0-1.09.39-1.98 1.03-2.68-.1-.25-.45-1.27.1-2.65 0 0 .84-.27 2.75 1.02A9.6 9.6 0 0 1 12 7.02a9.6 9.6 0 0 1 2.5.34c1.91-1.29 2.75-1.02 2.75-1.02.55 1.38.2 2.4.1 2.65.64.7 1.03 1.59 1.03 2.68 0 3.84-2.34 4.68-4.57 4.93.36.31.68.92.68 1.85v2.74c0 .27.18.58.69.48A10 10 0 0 0 12 2Z"/></svg><span>Connect GitHub</span>';
    const before = topActions.querySelector('.today-label');
    topActions.insertBefore(github, before || topActions.firstChild);
  }
  const githubUpdater = document.querySelector('[data-github-updater]');
  githubUpdater?.querySelector('[data-github-check]')?.addEventListener('click', async event => {
    const button=event.currentTarget, status=githubUpdater.querySelector('[data-github-status]'), result=githubUpdater.querySelector('[data-github-result]');
    button.disabled=true; status.textContent='Checking GitHub…'; result.hidden=false; result.textContent='Contacting MrMilar12/daloy on GitHub.';
    try { const response=await fetch('api.php?action=github_updates',{credentials:'same-origin',headers:{Accept:'application/json'}}); const data=await response.json(); if(!response.ok||data.error) throw new Error(data.error||'Unable to check GitHub updates.'); status.textContent=data.available?'Update available':'Up to date'; result.innerHTML=`<div class="github-update-summary"><span class="github-update-icon">${data.available?'↑':'✓'}</span><div><strong>${data.available?'New system update found':'Your system is up to date'}</strong><small>${data.available?'A verified VRS update is ready to install.':'No newer commit is available on the configured branch.'}</small></div></div><div class="github-update-commit"><span>Latest commit</span><code>${data.latest.slice(0,12)}</code><p>${data.message||'No commit message available.'}</p></div>${data.available?'<button type="button" class="primary github-install" data-github-install>Install update</button>':''}`; result.classList.toggle('has-update',data.available); result.querySelector('[data-github-install]')?.addEventListener('click',async()=>{const password=await new Promise(resolve=>{const dialog=document.createElement('dialog');dialog.className='update-password-dialog';dialog.innerHTML='<form method=dialog><h3>Authorize system update</h3><p>Enter your administrator password to continue.</p><input type=password name=password autocomplete=current-password required placeholder=Password><div class=dialog-actions><button value=cancel>Cancel</button><button class=primary value=confirm>Install update</button></div></form>';document.body.appendChild(dialog);dialog.addEventListener('close',()=>{const value=dialog.returnValue==='confirm'?dialog.querySelector('input').value:'';dialog.remove();resolve(value);},{once:true});dialog.showModal();});if(!password)return;const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';const form=new URLSearchParams({csrf,password,latest:data.latest});const install=await fetch('api.php?action=github_update_apply',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:form});const answer=await install.json();if(!install.ok||answer.error){alert(answer.error||'Update failed.');return;}alert(answer.message+' The system will reload after maintenance.');location.reload();}); } catch(error) { status.textContent='Check failed'; result.textContent=error.message; } finally { button.disabled=false; }
  });
  const updateProgress = document.querySelector('[data-update-progress]');
  const updateBar = document.querySelector('[data-update-progress-bar]');
  const updateLabel = document.querySelector('[data-update-progress-label]');
  const updatePercent = document.querySelector('[data-update-progress-percent]');
  document.addEventListener('click', event => {
    if (!event.target.closest('[data-github-install]') || !updateProgress) return;
    updateProgress.hidden = false;
    const stages = [[12,'Preparing update…'],[30,'Enabling maintenance mode…'],[56,'Downloading DALOY files…'],[78,'Installing system files…'],[94,'Finalizing update…']];
    let index = 0;
    const advance = () => { const [percent,label]=stages[Math.min(index++,stages.length-1)]; updateBar.style.width=percent+'%'; updatePercent.textContent=percent+'%'; updateLabel.textContent=label; };
    advance();
    const timer=setInterval(() => { if(index<stages.length) advance(); else clearInterval(timer); }, 900);
  });
  const displayChoices = [...document.querySelectorAll('[data-display-choice]')];
  const setDisplay = display => {
    document.body.dataset.display = display;
    displayChoices.forEach(choice => choice.setAttribute('aria-pressed', String(choice.dataset.displayChoice === display)));
    document.querySelectorAll('form[method="get"] input[name="display"]').forEach(input => { input.value = display; });
    document.querySelectorAll('#main-content a[href^="?"]').forEach(link => {
      if (link.hasAttribute('data-display-choice')) return;
      const target = new URL(link.href, location.href);
      if (target.searchParams.get('page') === document.body.dataset.page) {
        target.searchParams.set('display', display);
        link.setAttribute('href', target.search + target.hash);
      }
    });
  };
  displayChoices.forEach(choice => choice.addEventListener('click', event => {
    event.preventDefault();
    const display = choice.dataset.displayChoice;
    setDisplay(display);
    const url = new URL(location.href);
    url.searchParams.set('display', display);
    history.replaceState(null, '', url);
  }));
  if (displayChoices.length) setDisplay(document.body.dataset.display || 'card');
  document.querySelectorAll('.school-assignments').forEach(picker => {
    const search = picker.querySelector('.school-assignment-search');
    const options = [...picker.querySelectorAll('.school-assignment-option')];
    const update = () => {
      const query = search.value.trim().toLocaleLowerCase();
      options.forEach(option => { option.hidden = !option.textContent.toLocaleLowerCase().includes(query); });
      const selected = options.filter(option => option.querySelector('input').checked);
      const count = selected.length;
      const visible = options.filter(option => !option.hidden).length;
      picker.querySelector('.school-selection-badge').textContent = `${count} selected`;
      picker.querySelector('.school-result-count').textContent = `${visible} school${visible === 1 ? '' : 's'}`;
      picker.querySelector('.school-picker-empty').hidden = visible !== 0;
      const chips = picker.querySelector('.school-selected-chips');
      chips.replaceChildren();
      chips.hidden = count === 0;
      selected.forEach(option => {
        const name = option.querySelector('strong').textContent;
        const button = document.createElement('button');
        button.type = 'button';
        button.className = 'school-selected-chip';
        button.setAttribute('aria-label', `Remove ${name}`);
        const label = document.createElement('span');
        label.textContent = name;
        const close = document.createElement('span');
        close.textContent = '×';
        close.setAttribute('aria-hidden', 'true');
        button.append(label, close);
        button.addEventListener('click', () => {
          option.querySelector('input').checked = false;
          update();
          search.focus();
        });
        chips.append(button);
      });
    };
    search.addEventListener('input', update);
    picker.addEventListener('change', update);
    update();
  });
  const root = document.documentElement;
  try { root.dataset.theme = localStorage.getItem('daloy-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); } catch (_) {}
  document.querySelectorAll('.theme-toggle').forEach(button => button.addEventListener('click', () => {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('daloy-theme', root.dataset.theme); } catch (_) {}
  }));
  document.querySelector('.menu-toggle')?.addEventListener('click', event => {
    const open = document.body.classList.toggle('nav-open');
    event.currentTarget.setAttribute('aria-expanded', String(open));
  });
  document.addEventListener('keydown', event => { if (event.key === 'Escape') { document.body.classList.remove('nav-open'); document.querySelector('.menu-toggle')?.setAttribute('aria-expanded', 'false'); } });
  document.querySelector('.print-button')?.addEventListener('click', () => window.print());
  document.querySelector('[data-focus-error]')?.focus();
  document.querySelectorAll('[data-password-toggle]').forEach(button => button.addEventListener('click', () => {
    const show = button.getAttribute('aria-pressed') !== 'true';
    button.form.querySelectorAll('input[name$="password"]').forEach(input => { input.type = show ? 'text' : 'password'; });
    button.setAttribute('aria-pressed', String(show));
    button.textContent = show ? 'Hide passwords' : 'Show passwords';
  }));
  document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => { if (!confirm(form.dataset.confirm)) event.preventDefault(); }));
  document.querySelectorAll('form[data-validate]').forEach(form => {
    const start = form.elements.starts_at, end = form.elements.ends_at;
    const validate = () => {
      if (start && end) end.setCustomValidity(start.value && end.value && end.value <= start.value ? 'End must be after start. For overnight duty, use the next date.' : '');
      if (form.elements.confirm_password && form.elements.new_password) form.elements.confirm_password.setCustomValidity(form.elements.confirm_password.value !== form.elements.new_password.value ? 'Passwords do not match.' : '');
    };
    form.addEventListener('input', validate); form.addEventListener('submit', event => { validate(); if (!form.reportValidity()) event.preventDefault(); });
    const preview = form.querySelector('.conflict-preview');
    if (!preview) return;
    let controller, timer;
    const check = async () => {
      if (!start.value || !end.value || !form.elements.nurse_id.value || end.value <= start.value) return;
      controller?.abort(); controller = new AbortController();
      const params = new URLSearchParams({ action: 'conflicts', nurse_id: form.elements.nurse_id.value, starts_at: start.value, ends_at: end.value, exclude_id: form.elements.id?.value || '0' });
      preview.className = 'span-2 conflict-preview loading'; preview.textContent = 'Checking recorded commitments…';
      try {
        const response = await fetch('api.php?' + params, { signal: controller.signal, credentials: 'same-origin', headers: { Accept: 'application/json' } });
        const data = await response.json();
        if (!response.ok) throw new Error(data.error || 'Unable to check conflicts.');
        preview.className = 'span-2 conflict-preview alert ' + (data.conflicts.length ? 'danger' : 'success');
        preview.textContent = data.conflicts.length ? data.conflicts.map(c => `${c.label}: ${c.starts_at} to ${c.ends_at}`).join('; ') : 'No recorded conflicts for this interval. Availability will be checked again when saving.';
      } catch (error) { if (error.name !== 'AbortError') { preview.className = 'span-2 conflict-preview alert notice'; preview.textContent = error.message + ' The server will validate when you save.'; } }
    };
    form.addEventListener('change', () => { clearTimeout(timer); timer = setTimeout(check, 200); });
    document.querySelector('#shift-preset')?.addEventListener('change', event => {
      if (!event.target.value) return;
      const [a, b] = event.target.value.split('|'), day = start.value.slice(0, 10) || new Date().toLocaleDateString('en-CA');
      start.value = day + 'T' + a;
      const endDate = new Date(day + 'T12:00:00'); if (b <= a) endDate.setDate(endDate.getDate() + 1);
      const localDay = `${endDate.getFullYear()}-${String(endDate.getMonth() + 1).padStart(2, '0')}-${String(endDate.getDate()).padStart(2, '0')}`;
      end.value = localDay + 'T' + b; validate(); check();
    });
  });
})();
