/* Profils entraîneurs : clic, clavier et glissement partagent une seule gestion. */
(function () {
  'use strict';
  const track = document.getElementById('coaches-track');
  const carousel = document.getElementById('coachesCarousel');
  const dialogs = document.getElementById('coaches-modals');
  const coaches = window.ENTRAINEURS || [];
  if (!track || !carousel || !dialogs || !coaches.length) return;
  const escape = value => String(value ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const known = value => value && !/^(?:à définir|a definir|à venir|—|-)$/i.test(String(value).trim());
  const groups = coach => Array.isArray(coach.groupes) ? coach.groupes : [coach.groupes || ''];
  const avatar = (coach, modal) => coach.photo
    ? `<img src="${escape(coach.photo)}" alt="" class="${modal ? 'coach-modal-avatar' : 'coach-avatar'}" loading="lazy" decoding="async">`
    : `<div class="${modal ? 'coach-modal-avatar-initials' : 'coach-avatar-initials'}">${escape(coach.initiale || coach.nom?.[0])}</div>`;
  function card(coach, clone) {
    return `<button type="button" class="coach-card" data-coach="${escape(coach.id)}" tabindex="${clone ? '-1' : '0'}"${clone ? ' aria-hidden="true"' : ''} aria-label="Voir le profil de ${escape(coach.nom)}" aria-haspopup="dialog" aria-controls="modal-coach-${escape(coach.id)}"><span class="coach-avatar-wrap">${avatar(coach, false)}</span><span class="coach-name">${escape(coach.nom)}</span><span class="coach-group-tag">${escape(groups(coach).join(' · '))}</span></button>`;
  }
  track.innerHTML = coaches.map(c => card(c, false)).join('') + coaches.map(c => card(c, true)).join('');
  dialogs.innerHTML = coaches.map(coach => `<div id="modal-coach-${escape(coach.id)}" class="modal-overlay modal-coach-full" role="dialog" aria-modal="true" aria-labelledby="coach-title-${escape(coach.id)}"><div class="modal-box" style="max-width:520px"><button type="button" class="modal-close" aria-label="Fermer le profil de ${escape(coach.nom)}">×</button><div class="modal-header">${avatar(coach, true)}<p class="modal-eyebrow" style="color:var(--tg-red)">${escape(coach.role)}</p><h2 class="modal-title" id="coach-title-${escape(coach.id)}">${escape(coach.nom)}</h2></div><div class="modal-body">${known(coach.bio) ? `<p class="modal-desc" style="white-space:pre-line">${escape(coach.bio)}</p>` : ''}${known(coach.depuis) ? `<div class="coach-modal-meta"><div class="coach-modal-meta-item"><span class="coach-modal-meta-label">Depuis</span><span class="coach-modal-meta-value">${escape(coach.depuis)}</span></div></div>` : ''}<div class="coach-modal-groups">${groups(coach).map(g => `<span class="coach-modal-group-tag">${escape(g)}</span>`).join('')}</div></div></div></div>`).join('');

  let position = 0, frame = null, pointer = null, suppressClickUntil = 0;
  const paused = new Set();
  function span() { return track.children[coaches.length]?.offsetLeft || track.scrollWidth / 2; }
  function move(next) {
    const width = span();
    if (!width) return;
    position = ((next % width) - width) % width;
    track.style.transform = `translateX(${position}px)`;
  }
  function tick() {
    frame = null;
    if (paused.size) return;
    move(position - 0.6);
    frame = requestAnimationFrame(tick);
  }
  function pause(reason) { paused.add(reason); if (frame !== null) cancelAnimationFrame(frame); frame = null; }
  function resume(reason) { paused.delete(reason); if (!paused.size && frame === null) frame = requestAnimationFrame(tick); }
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)');
  function motion() { if (reduced.matches) pause('motion'); else resume('motion'); }
  motion();
  reduced.addEventListener?.('change', motion);
  carousel.addEventListener('mouseenter', () => pause('hover'));
  carousel.addEventListener('mouseleave', () => resume('hover'));
  carousel.addEventListener('focusin', () => pause('focus'));
  carousel.addEventListener('focusout', event => { if (!carousel.contains(event.relatedTarget)) resume('focus'); });

  function open(card) {
    if (!card || !carousel.contains(card)) return;
    const modal = document.getElementById('modal-coach-' + card.dataset.coach);
    if (!modal || modal.classList.contains('active')) return;
    pause('modal');
    card.focus({ preventScroll: true });
    window.openModal(modal.id);
  }
  function close(modal) { window.closeModal(modal); resume('modal'); }
  carousel.style.touchAction = 'pan-y';
  carousel.addEventListener('pointerdown', event => {
    if (event.button !== 0 || !event.isPrimary) return;
    pointer = { id: event.pointerId, x: event.clientX, y: event.clientY, position, card: event.target.closest('.coach-card'), moved: false, horizontal: false };
    pause('pointer');
  });
  document.addEventListener('pointermove', event => {
    if (!pointer || pointer.id !== event.pointerId) return;
    const dx = event.clientX - pointer.x, dy = event.clientY - pointer.y;
    if (Math.max(Math.abs(dx), Math.abs(dy)) <= 8) return;
    pointer.moved = true;
    if (!pointer.horizontal && Math.abs(dx) > Math.abs(dy)) pointer.horizontal = true;
    if (pointer.horizontal) { carousel.classList.add('is-dragging'); move(pointer.position + dx); }
  });
  function release(event, cancelled) {
    if (!pointer || pointer.id !== event.pointerId) return;
    const action = pointer; pointer = null;
    carousel.classList.remove('is-dragging');
    if (action.moved || cancelled) suppressClickUntil = Date.now() + 350;
    if (!action.moved && !cancelled && event.target.closest('.coach-card') === action.card) open(action.card);
    resume('pointer');
  }
  document.addEventListener('pointerup', event => release(event, false));
  document.addEventListener('pointercancel', event => release(event, true));
  window.addEventListener('blur', () => { pointer = null; carousel.classList.remove('is-dragging'); resume('pointer'); });
  carousel.addEventListener('click', event => { if (Date.now() >= suppressClickUntil) open(event.target.closest('.coach-card')); });
  // Les boutons natifs génèrent leur clic avec Entrée et Espace.
  dialogs.addEventListener('click', event => {
    const button = event.target.closest('.modal-close');
    if (button) close(button.closest('.modal-overlay'));
    else if (event.target.classList.contains('modal-overlay')) close(event.target);
  });
  dialogs.addEventListener('keydown', event => {
    const modal = event.target.closest('.modal-overlay.active');
    if (!modal || event.key !== 'Tab') return;
    const items = [...modal.querySelectorAll('button,a[href],input,select,textarea,[tabindex]:not([tabindex="-1"])')].filter(el => !el.disabled && el.getClientRects().length);
    const first = items[0], last = items[items.length - 1];
    if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
    else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
  });
  new MutationObserver(() => { if (!dialogs.querySelector('.modal-overlay.active')) resume('modal'); }).observe(dialogs, { subtree: true, attributes: true, attributeFilter: ['class'] });
})();
