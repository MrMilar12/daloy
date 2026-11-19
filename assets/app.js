(() => {
  'use strict';
  const activityModal = document.querySelector('[data-activity-modal]');
  if (activityModal?.showModal) {
    activityModal.showModal();
    activityModal.addEventListener('cancel', event => { event.preventDefault(); location.href='?page=activities'; });
    activityModal.addEventListener('click', event => { if (event.target === activityModal) location.href='?page=activities'; });
    activityModal.querySelector('[name="destination_name"]')?.focus({ preventScroll: true });
  }
  const candidatesModal = document.querySelector('[data-candidates-modal]');
  if (candidatesModal?.showModal) {
    candidatesModal.showModal();
    candidatesModal.addEventListener('cancel', event => { event.preventDefault(); location.href='?page=deployment'; });
    candidatesModal.addEventListener('click', event => { if (event.target === candidatesModal) location.href='?page=deployment'; });
  }
  document.querySelectorAll('[data-record-modal]').forEach(modal => {
    if (!modal.showModal) { modal.setAttribute('open', ''); return; }
    modal.showModal();
    modal.addEventListener('cancel', event => { event.preventDefault(); const close = modal.querySelector('.record-modal-close'); if (close) location.href = close.href; else modal.close(); });
    modal.addEventListener('click', event => { if (event.target === modal) { const close = modal.querySelector('.record-modal-close'); if (close) location.href = close.href; else modal.close(); } });
    modal.querySelector('input:not([type="hidden"]),select,textarea')?.focus({ preventScroll: true });
  });
  const updateProgress = document.querySelector('[data-update-progress]');
  const updateBar = document.querySelector('[data-update-progress-bar]');
  const updateLabel = document.querySelector('[data-update-progress-label]');
  const updatePercent = document.querySelector('[data-update-progress-percent]');
  let updateProgressTimer;
  const startUpdateProgress = () => {
    if (!updateProgress) return;
    clearInterval(updateProgressTimer);
    updateProgress.hidden = false;
    const stages = [[12,'Preparing update'],[30,'Enabling maintenance mode'],[56,'Downloading DALOY files'],[78,'Installing system files'],[94,'Finalizing update']];
    let index = 0;
    const advance = () => { const [percent,label]=stages[Math.min(index++,stages.length-1)]; updateBar.style.width=percent+'%'; updatePercent.textContent=percent+'%'; updateLabel.textContent=label; };
    advance();
    updateProgressTimer=setInterval(() => { if(index<stages.length) advance(); else { clearInterval(updateProgressTimer); updateProgressTimer=null; } }, 900);
  };
  const githubUpdater = document.querySelector('[data-github-updater]');
  const updateDialog = githubUpdater?.querySelector('[data-github-update-dialog]');
  const updateCommit = updateDialog?.querySelector('[data-github-update-commit]');
  const updateMessage = updateDialog?.querySelector('[data-github-update-message]');
  const updateInstallButton = updateDialog?.querySelector('[data-github-install]');
  let latestUpdate = '';
  updateDialog?.querySelectorAll('[data-github-update-close]').forEach(button => button.addEventListener('click', () => updateDialog.close()));
  updateDialog?.addEventListener('click', event => { if (event.target === updateDialog) updateDialog.close(); });
  const requestUpdatePassword = () => new Promise(resolve => {
    const dialog=document.createElement('dialog');
    dialog.className='update-password-dialog';
    dialog.innerHTML='<form method=dialog><h3>Authorize system update</h3><p>Enter your administrator password to continue.</p><input type=password name=password autocomplete=current-password required placeholder=Password><div class=dialog-actions><button type=button data-update-cancel>Cancel</button><button type=submit class=primary value=confirm>Install update</button></div></form>';
    document.body.appendChild(dialog);
    dialog.querySelector('[data-update-cancel]').addEventListener('click', () => dialog.close('cancel'));
    dialog.addEventListener('close', () => {
      const input=dialog.querySelector('input');
      const password=dialog.returnValue==='confirm'&&input?input.value:'';
      dialog.remove();
      resolve(password);
    }, {once:true});
    dialog.showModal();
  });
  const installUpdate = async (latest, button) => {
    button.disabled=true;
    try {
      const password=await requestUpdatePassword();
      if (!password) return;
      startUpdateProgress();
      const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
      const form=new URLSearchParams({csrf,password,latest});
      const response=await fetch('api.php?action=github_update_apply',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json'},body:form});
      const answer=await response.json();
      if (!response.ok||answer.error) throw new Error(answer.error||'Update failed.');
      alert(answer.message+' The system will reload after maintenance.');
      location.reload();
    } catch (error) {
      clearInterval(updateProgressTimer);
      updateProgressTimer=null;
      if (updateProgress) updateProgress.hidden=true;
      alert(error instanceof Error?error.message:'Update failed.');
    } finally {
      button.disabled=false;
    }
  };
  const makeCommitMessage = message => {
    const entities={amp:'&',lt:'<',gt:'>',quot:'"'};
    return (message||'No commit message available.').replace(/&(amp|lt|gt|quot|#0?39);/g, (entity,name) => name[0]==='#'?"'":entities[name]);
  };
  const renderCommitMessage = (container, rawMessage) => {
    container.replaceChildren();
    const appendInlineMarkdown = (element, text) => {
      const tokenPattern=/(\*\*.+?\*\*|`[^`]+`)/g;
      let offset=0;
      let match;
      while ((match=tokenPattern.exec(text))!==null) {
        if (match.index>offset) element.append(document.createTextNode(text.slice(offset,match.index)));
        const token=match[0];
        const formatted=document.createElement(token.startsWith('**')?'strong':'code');
        formatted.textContent=token.startsWith('**')?token.slice(2,-2):token.slice(1,-1);
        element.append(formatted);
        offset=tokenPattern.lastIndex;
      }
      element.append(document.createTextNode(text.slice(offset)));
    };
    const lines=makeCommitMessage(rawMessage).replace(/\r\n?/g,'\n').split('\n');
    let paragraph=[];
    let list=null;
    const flushParagraph = () => {
      if (!paragraph.length) return;
      const block=document.createElement('p');
      appendInlineMarkdown(block,paragraph.join(' '));
      container.append(block);
      paragraph=[];
    };
    const flushList = () => {
      if (!list) return;
      container.append(list);
      list=null;
    };
    lines.forEach(rawLine => {
      const line=rawLine.trim();
      if (!line) {
        flushParagraph();
        flushList();
        return;
      }
      const heading=line.match(/^#{1,6}\s+(.+)$/);
      if (heading) {
        flushParagraph();
        flushList();
        const block=document.createElement('h3');
        appendInlineMarkdown(block,heading[1]);
        container.append(block);
        return;
      }
      const item=line.match(/^[-*]\s+(.+)$/);
      if (item) {
        flushParagraph();
        if (!list) list=document.createElement('ul');
        const listItem=document.createElement('li');
        appendInlineMarkdown(listItem,item[1]);
        list.append(listItem);
        return;
      }
      flushList();
      paragraph.push(line);
    });
    flushParagraph();
    flushList();
  };
  const renderCurrentVersion = (result, data) => {
    result.replaceChildren();
    result.classList.remove('has-update');
    const summary=document.createElement('div');
    summary.className='github-update-summary';
    const heading=document.createElement('strong');
    heading.textContent='Your system is up to date';
    const detail=document.createElement('small');
    detail.textContent='No newer commit is available on the configured branch.';
    const summaryText=document.createElement('div');
    summaryText.append(heading,detail);
    summary.append(summaryText);
    const commit=document.createElement('div');
    commit.className='github-update-commit';
    const commitLabel=document.createElement('span');
    commitLabel.textContent='Latest commit';
    const commitHash=document.createElement('code');
    commitHash.textContent=data.latest.slice(0,12);
    const commitMessage=document.createElement('div');
    commitMessage.className='github-update-commit-message';
    renderCommitMessage(commitMessage,data.message);
    commit.append(commitLabel,commitHash,commitMessage);
    result.append(summary,commit);
    result.hidden=false;
  };
  const githubCheckButton=githubUpdater?.querySelector('[data-github-check]');
  const updateNotificationBadges = () => {
    document.querySelectorAll('.notification-button,.sidebar nav a[href="?page=notifications"]').forEach(link => {
      const topbar=link.matches('.notification-button');
      const badgeClass=topbar?'notification-count':'count';
      let badge=link.querySelector('.'+badgeClass);
      const count=Number(badge?.textContent||0)+1;
      if (!badge) {
        badge=document.createElement('span');
        badge.className=badgeClass;
        link.append(badge);
      }
      badge.textContent=String(count);
      if (topbar) link.setAttribute('aria-label',`Notifications, ${count} unread`);
    });
  };
  const syncNotificationBadges = unreadCount => {
    const count=Math.max(0,Number(unreadCount)||0);
    document.querySelectorAll('.notification-button,.sidebar nav a[href="?page=notifications"]').forEach(link => {
      const topbar=link.matches('.notification-button');
      const badgeClass=topbar?'notification-count':'count';
      let badge=link.querySelector('.'+badgeClass);
      if (!count) {
        badge?.remove();
        if (topbar) link.setAttribute('aria-label','Notifications, 0 unread');
        return;
      }
      if (!badge) {
        badge=document.createElement('span');
        badge.className=badgeClass;
        link.append(badge);
      }
      badge.textContent=String(count);
      if (topbar) link.setAttribute('aria-label',`Notifications, ${count} unread`);
    });
  };
  const notificationMenu=document.querySelector('[data-notification-menu]');
  const notificationToggle=notificationMenu?.querySelector('[data-notification-toggle]');
  const notificationDropdown=notificationMenu?.querySelector('.notification-dropdown');
  const notificationList=notificationMenu?.querySelector('[data-notification-list]');
  const notificationUnread=notificationMenu?.querySelector('[data-notification-unread]');
  const notificationStatus=notificationMenu?.querySelector('[data-notification-status]');
  const csrfToken=document.querySelector('meta[name="csrf-token"]')?.content||'';
  let loadingNotifications=false;
  let openingNotification=false;
  const closeNotificationMenu = (restoreFocus=false) => {
    if (!notificationDropdown||!notificationToggle) return;
    notificationDropdown.hidden=true;
    notificationToggle.setAttribute('aria-expanded','false');
    if (restoreFocus) notificationToggle.focus();
  };
  const renderRecentNotifications = notifications => {
    if (!notificationList) return;
    notificationList.replaceChildren();
    if (!notifications.length) {
      const empty=document.createElement('div');
      empty.className='notification-dropdown-empty';
      const title=document.createElement('strong');
      title.textContent='You’re all caught up';
      const message=document.createElement('span');
      message.textContent='New updates will appear here.';
      empty.append(title,message);
      notificationList.append(empty);
      return;
    }
    notifications.forEach(notification => {
      const link=document.createElement('a');
      link.className='notification-dropdown-item'+(notification.read_at?' is-read':' is-unread');
      link.href='?page=notifications';
      if (typeof notification.link==='string' && notification.link.trim()) {
        try {
          const destination=new URL(notification.link,location.href);
          if (destination.protocol===location.protocol && destination.origin===location.origin) link.href=destination.href;
        } catch {
          // Keep the inbox link if an older notification has an invalid destination.
        }
      }
      link.dataset.notificationId=String(notification.id);
      const marker=document.createElement('span');
      marker.className='notification-dropdown-marker';
      marker.setAttribute('aria-hidden','true');
      const copy=document.createElement('span');
      copy.className='notification-dropdown-copy';
      const title=document.createElement('strong');
      title.textContent=notification.title||'Workspace update';
      if (!notification.read_at) {
        const unreadLabel=document.createElement('span');
        unreadLabel.className='sr-only';
        unreadLabel.textContent=' Unread';
        title.append(unreadLabel);
      }
      const message=document.createElement('span');
      message.textContent=notification.message||'';
      copy.append(title,message);
      const timestamp=document.createElement('time');
      const createdAt=new Date(notification.created_at||'');
      if (!Number.isNaN(createdAt.getTime())) {
        timestamp.dateTime=createdAt.toISOString();
        timestamp.textContent=new Intl.DateTimeFormat(undefined,{month:'short',day:'numeric',hour:'numeric',minute:'2-digit'}).format(createdAt);
        link.append(marker,copy,timestamp);
      } else {
        link.append(marker,copy);
      }
      notificationList.append(link);
    });
  };
  const loadRecentNotifications = async () => {
    if (!notificationList||!notificationUnread||!notificationStatus||loadingNotifications) return;
    loadingNotifications=true;
    notificationList.replaceChildren();
    notificationUnread.textContent='';
    notificationStatus.textContent='Loading notifications…';
    try {
      const response=await fetch('api.php?action=notifications_recent',{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
      const data=await response.json();
      if (!response.ok||!Array.isArray(data.notifications)||!Number.isInteger(data.unread_count)) throw new Error(data.error||'Unable to load notifications.');
      renderRecentNotifications(data.notifications);
      notificationUnread.textContent=data.unread_count?`${data.unread_count} unread`:'All caught up';
      notificationStatus.textContent='';
      syncNotificationBadges(data.unread_count);
    } catch {
      notificationStatus.textContent='Unable to load recent notifications. Open the inbox or try again.';
    } finally {
      loadingNotifications=false;
    }
  };
  const markNotificationRead = async notificationId => {
    const body=new URLSearchParams({csrf:csrfToken,id:notificationId});
    const response=await fetch('api.php?action=notification_read',{method:'POST',credentials:'same-origin',headers:{Accept:'application/json','Content-Type':'application/x-www-form-urlencoded'},body});
    const data=await response.json();
    if (!response.ok||!Number.isInteger(data.unread_count)) throw new Error(data.error||'Unable to mark this notification as read.');
    syncNotificationBadges(data.unread_count);
  };
  notificationToggle?.addEventListener('click',() => {
    if (!notificationDropdown) return;
    const opening=notificationDropdown.hidden;
    notificationDropdown.hidden=!opening;
    notificationToggle.setAttribute('aria-expanded',String(opening));
    if (opening) loadRecentNotifications();
  });
  notificationList?.addEventListener('click',async event => {
    const link=event.target instanceof Element?event.target.closest('a[data-notification-id]'):null;
    if (!link||event.button!==0||event.metaKey||event.ctrlKey||event.shiftKey||event.altKey) return;
    if (!link.classList.contains('is-unread')) return;
    event.preventDefault();
    if (openingNotification) return;
    openingNotification=true;
    try {
      await markNotificationRead(link.dataset.notificationId);
    } catch {
      // The destination remains available if the read update fails.
    } finally {
      openingNotification=false;
    }
    location.assign(link.href);
  });
  const headerDisclosures = document.querySelectorAll('[data-header-disclosure]');
  const headerCalendar = document.querySelector('.header-calendar');
  headerCalendar?.addEventListener('click', event => {
    const day = event.target instanceof Element && event.target.closest('[data-calendar-day]');
    if (!day || event.button!==0 || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
    event.preventDefault();
    headerCalendar.querySelector('[data-calendar-day][data-selected]')?.removeAttribute('data-selected');
    day.setAttribute('data-selected','');
    headerCalendar.querySelectorAll('[data-calendar-agenda]').forEach(agenda => {
      agenda.hidden = agenda.dataset.calendarAgenda !== day.dataset.calendarDay;
    });
  });
  document.addEventListener('click',event => {
    if (notificationDropdown&&!notificationDropdown.hidden&&!notificationMenu?.contains(event.target)) closeNotificationMenu();
    headerDisclosures.forEach(disclosure => {
      if (disclosure.open && !disclosure.contains(event.target)) disclosure.open = false;
    });
  });
  document.addEventListener('keydown',event => {
    if (event.key!=='Escape') return;
    if (notificationDropdown&&!notificationDropdown.hidden) closeNotificationMenu(true);
    headerDisclosures.forEach(disclosure => {
      if (!disclosure.open) return;
      disclosure.open = false;
      disclosure.querySelector('summary').focus();
    });
  });
  const updateNotificationUserId=document.body.dataset.userId;
  if (updateNotificationUserId) {
    const cursorKey=`daloy-system-updates:${updateNotificationUserId}`;
    let updateCursor=0;
    try {
      const savedCursor=localStorage.getItem(cursorKey);
      if (savedCursor && /^\d+$/.test(savedCursor)) updateCursor=Number(savedCursor);
    } catch (error) {
      console.warn('System update notifications could not be remembered in this browser.',error);
    }
    let polling=false;
    const showSystemUpdateToast = notification => {
      const region=document.querySelector('[data-system-update-toasts]');
      if (!region) return;
      const toast=document.createElement('article');
      toast.className='system-update-toast';
      const header=document.createElement('div');
      header.className='system-update-toast-header';
      const icon=document.createElement('span');
      icon.className='system-update-toast-icon';
      icon.setAttribute('aria-hidden','true');
      icon.textContent='✓';
      const heading=document.createElement('div');
      heading.className='system-update-toast-heading';
      const eyebrow=document.createElement('span');
      eyebrow.textContent='UPDATE COMPLETE';
      const title=document.createElement('strong');
      title.textContent=notification.title||'System updated';
      heading.append(eyebrow,title);
      const message=document.createElement('p');
      message.className='system-update-toast-message';
      message.textContent=notification.message||'A DALOY system update was installed.';
      const close=document.createElement('button');
      close.type='button';
      close.className='system-update-toast-close';
      close.setAttribute('aria-label','Dismiss update notification');
      close.textContent='×';
      header.append(icon,heading,close);
      const footer=document.createElement('div');
      footer.className='system-update-toast-footer';
      const link=document.createElement('a');
      link.href='?page=notifications';
      link.textContent='View notification';
      footer.append(link);
      toast.append(header,message,footer);
      region.prepend(toast);
      const timer=window.setTimeout(() => toast.remove(),15000);
      close.addEventListener('click',() => { window.clearTimeout(timer); toast.remove(); });
    };
    const pollSystemUpdateNotifications = async () => {
      if (polling) return;
      polling=true;
      try {
        const params=new URLSearchParams({action:'system_update_notifications',after:String(updateCursor)});
        const response=await fetch('api.php?'+params,{credentials:'same-origin',cache:'no-store',headers:{Accept:'application/json'}});
        const data=await response.json();
        if (!response.ok||!Array.isArray(data.notifications)||!Number.isInteger(data.unread_count)) throw new Error(data.error||'Unable to check system update notifications.');
        syncNotificationBadges(data.unread_count);
        let nextCursor=updateCursor;
        data.notifications.forEach(notification => {
          const id=Number(notification.id);
          if (!Number.isSafeInteger(id)||id<=updateCursor) return;
          showSystemUpdateToast(notification);
          nextCursor=Math.max(nextCursor,id);
        });
        if (nextCursor>updateCursor) {
          updateCursor=nextCursor;
          try { localStorage.setItem(cursorKey,String(updateCursor)); }
          catch (error) { console.warn('System update notifications will repeat after a page reload.',error); }
        }
      } catch (error) {
        console.error(error instanceof Error?error.message:'Unable to check system update notifications.');
      } finally {
        polling=false;
      }
    };
    pollSystemUpdateNotifications();
    window.setInterval(pollSystemUpdateNotifications,30000);
    document.addEventListener('visibilitychange',() => {
      if (document.visibilityState==='visible') pollSystemUpdateNotifications();
    });
  }
  const checkForUpdates = async (automatic=false) => {
    if (!githubUpdater||!githubCheckButton) return;
    const button=githubCheckButton;
    const status=githubUpdater.querySelector('[data-github-status]');
    const result=githubUpdater.querySelector('[data-github-result]');
    button.disabled=true;
    status.textContent='Checking GitHub';
    result.hidden=false;
    result.textContent='Contacting MrMilar12/daloy on GitHub.';
    try {
      const response=await fetch('api.php?action=github_updates',{credentials:'same-origin',headers:{Accept:'application/json'}});
      const data=await response.json();
      if (!response.ok||data.error) throw new Error(data.error||'Unable to check GitHub updates.');
      if (typeof data.latest!=='string'||!data.latest) throw new Error('GitHub returned an invalid update response.');
      latestUpdate=data.latest;
      if (data.available) {
        status.textContent='Update available';
        result.hidden=true;
        updateCommit.textContent=data.latest.slice(0,12);
        renderCommitMessage(updateMessage,data.message);
        if (data.notification_created) updateNotificationBadges();
        if (!automatic||data.notification_created) updateDialog.showModal();
      } else {
        status.textContent='Up to date';
        renderCurrentVersion(result,data);
      }
    } catch (error) {
      status.textContent='Check failed';
      result.hidden=false;
      result.textContent=error instanceof Error?error.message:'Unable to check GitHub updates.';
    } finally {
      button.disabled=false;
    }
  };
  githubCheckButton?.addEventListener('click', () => checkForUpdates());
  if (githubCheckButton) checkForUpdates(true);
  updateInstallButton?.addEventListener('click', () => {
    updateDialog.close();
    installUpdate(latestUpdate,updateInstallButton);
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
        close.textContent = '';
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
  const accountRole = document.querySelector('select[name="role"]');
  const nurseAssignments = document.querySelector('[data-nurse-school-assignments]');
  if (accountRole && nurseAssignments) {
    const updateNurseAssignments = () => {
      const enabled = accountRole.value === 'nurse';
      nurseAssignments.hidden = !enabled;
      nurseAssignments.disabled = !enabled;
    };
    accountRole.addEventListener('change', updateNurseAssignments);
    updateNurseAssignments();
  }
  const root = document.documentElement;
  try { root.dataset.theme = localStorage.getItem('daloy-theme') || (matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'); } catch (_) {}
  const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)');
  let lightReflection;
  document.querySelectorAll('.theme-toggle').forEach(button => button.addEventListener('click', () => {
    root.dataset.theme = root.dataset.theme === 'dark' ? 'light' : 'dark';
    try { localStorage.setItem('daloy-theme', root.dataset.theme); } catch (_) {}
    lightReflection?.remove();
    lightReflection = undefined;
    if (root.dataset.theme !== 'light' || reducedMotion.matches) return;

    const anchor = button.getBoundingClientRect();
    const reflection = document.createElement('div');
    reflection.className = 'theme-light-reflection';
    reflection.setAttribute('aria-hidden', 'true');
    reflection.style.setProperty('--reflection-x', `${anchor.left + anchor.width / 2}px`);
    reflection.style.setProperty('--reflection-y', `${anchor.top + anchor.height / 2}px`);
    lightReflection = reflection;
    document.body.append(reflection);
    reflection.addEventListener('animationend', event => {
      if (event.animationName !== 'theme-light-glow') return;
      reflection.remove();
      if (lightReflection === reflection) lightReflection = undefined;
    });
  }));
  const signInForm = document.querySelector('.auth-page:not(.auth-registration-page) .auth-panel form');
  if (signInForm) {
    const releaseSnow = (x, y, count) => {
      if (reducedMotion.matches) return;

      const fallDistance = Math.max(0, window.innerHeight - y);
      const flakes = document.createDocumentFragment();
      for (let index = 0; index < count; index++) {
        const flake = document.createElement('span');
        flake.className = 'auth-snowflake';
        flake.setAttribute('aria-hidden', 'true');
        flake.textContent = '❄';
        flake.style.left = x + 'px';
        flake.style.top = y + 'px';
        flake.style.fontSize = (10 + Math.random() * 10) + 'px';
        flake.style.setProperty('--snow-drift', (Math.random() * 100 - 50) + 'px');
        flake.style.setProperty('--snow-fall', fallDistance + 'px');
        flake.style.setProperty('--snow-duration', (1.35 + Math.min(fallDistance / 430, 2) + Math.random() * .35) + 's');
        flake.style.setProperty('--snow-spin', (Math.random() * 130 - 65) + 'deg');
        flake.addEventListener('animationend', () => flake.remove(), {once:true});
        flakes.append(flake);
      }
      document.body.append(flakes);
    };

    let lastSnowfall = -Infinity;
    document.addEventListener('pointermove', event => {
      if (event.pointerType !== 'mouse' || event.timeStamp - lastSnowfall < 40) return;
      lastSnowfall = event.timeStamp;
      releaseSnow(event.clientX, event.clientY, 3);
    }, {passive:true});
    let activeTap;
    signInForm.addEventListener('touchstart', event => {
      activeTap = event.touches.length === 1 ? event.changedTouches[0] : undefined;
    }, {passive:true});
    signInForm.addEventListener('touchcancel', () => {
      activeTap = undefined;
    }, {passive:true});
    signInForm.addEventListener('touchend', event => {
      const start = activeTap;
      activeTap = undefined;
      if (!start) return;
      const end = Array.from(event.changedTouches).find(touch => touch.identifier === start.identifier);
      if (end && Math.hypot(end.clientX - start.clientX, end.clientY - start.clientY) <= 12) {
        releaseSnow(end.clientX, end.clientY, 14);
      }
    }, {passive:true});
  }
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
    button.textContent = show ? (button.dataset.hideLabel || 'Hide passwords') : (button.dataset.showLabel || 'Show passwords');
  }));
  document.querySelectorAll('form[data-confirm]').forEach(form => form.addEventListener('submit', event => { if (!confirm(form.dataset.confirm)) event.preventDefault(); }));
  document.querySelectorAll('form[data-validate]').forEach(form => {
    const accountErrors = form.querySelector('[data-account-validation]');
    if (accountErrors) {
      const errorsList = accountErrors.querySelector('ul');
      const showErrors = () => {
        errorsList.replaceChildren();
        Array.from(form.elements).forEach(field => {
          if (!field.willValidate) return;
          if (field.validity.valid) {
            field.removeAttribute('aria-invalid');
            return;
          }
          field.setAttribute('aria-invalid', 'true');
          const label = field.labels?.[0]?.firstChild?.textContent.trim() || field.name;
          const item = document.createElement('li');
          item.textContent = label + ' — ' + field.validationMessage;
          errorsList.append(item);
        });
        accountErrors.hidden = !errorsList.childElementCount;
      };
      let updateQueued = false;
      form.addEventListener('invalid', event => {
        event.preventDefault();
        if (updateQueued) return;
        updateQueued = true;
        queueMicrotask(() => {
          updateQueued = false;
          showErrors();
          if (!accountErrors.hidden) accountErrors.focus();
        });
      }, true);
      form.addEventListener('input', () => {
        if (!accountErrors.hidden) showErrors();
      });
    }
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
      preview.className = 'span-2 conflict-preview loading'; preview.textContent = 'Checking recorded commitments';
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

