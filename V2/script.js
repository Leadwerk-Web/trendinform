// ===== Statischer Modus (?static) für Screenshots/PDF-Export =====
const STATIC_MODE = location.search.includes('static');
if (STATIC_MODE) {
  document.body.classList.add('is-static');
  document.querySelectorAll('.reveal').forEach(el => el.classList.add('is-visible'));
}

// ===== Preloader: Formen-Trio, Vorhang hebt sich nach dem Laden =====
(() => {
  const preloader = document.getElementById('preloader');
  if (!preloader) return;
  if (STATIC_MODE) { preloader.remove(); document.body.classList.add('is-ready'); return; }

  const start = performance.now();
  const MIN_SHOW = 1200;   // Mindestanzeige, damit die Animation nicht "zuckt"
  let hidden = false;

  const hide = () => {
    if (hidden) return;
    hidden = true;
    const wait = Math.max(0, MIN_SHOW - (performance.now() - start));
    setTimeout(() => {
      preloader.classList.add('is-done');
      document.body.classList.add('is-ready');
      preloader.addEventListener('transitionend', () => preloader.remove(), { once: true });
    }, wait);
  };

  // Vorhang hebt sich, sobald Struktur und Schriften stehen. Auf Bilder und Video wird nicht
  // gewartet, das Poster ist vorgeladen. Sicherheitsnetz: nie länger als 2,5 s.
  const ready = () => { if (document.fonts && document.fonts.ready) document.fonts.ready.then(hide); else hide(); };
  if (document.readyState !== 'loading') ready();
  else document.addEventListener('DOMContentLoaded', ready);
  setTimeout(hide, 2500);
})();

// ===== Lichtschweif: dezente Leuchtspur, die der Maus nachzieht =====
// Nur bei echter Maus (pointer: fine), nicht bei Touch, reduzierter
// Bewegung oder im Static-Modus.
(() => {
  const fine = window.matchMedia('(pointer: fine)').matches;
  const reduced = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  if (!fine || reduced || STATIC_MODE) return;

  const canvas = document.createElement('canvas');
  canvas.className = 'cursor-trail';
  document.body.appendChild(canvas);
  const ctx = canvas.getContext('2d');

  let dpr = 1;
  const resize = () => {
    dpr = Math.min(window.devicePixelRatio || 1, 2);
    canvas.width = innerWidth * dpr;
    canvas.height = innerHeight * dpr;
  };
  resize();
  window.addEventListener('resize', resize);

  let mx = -100, my = -100;   // echte Mausposition
  let hx = -100, hy = -100;   // geglätteter Kopf des Schweifs
  let started = false;
  document.addEventListener('mousemove', e => {
    mx = e.clientX; my = e.clientY;
    if (!started) { started = true; hx = mx; hy = my; }
  }, { passive: true });

  const pts = [];        // Spurpunkte mit Zeitstempel
  const LIFE = 600;      // Lebensdauer eines Punkts in ms
  const COLOR = '239, 201, 59';  // warmes Marken-Gold

  const loop = t => {
    // Kopf gleitet der Maus weich hinterher -> geschmeidige Kurve
    hx += (mx - hx) * 0.3;
    hy += (my - hy) * 0.3;

    // Nur Punkte setzen, wenn sich die Maus wirklich bewegt:
    // im Stillstand verglüht der Schweif vollständig.
    const last = pts[pts.length - 1];
    if (started && (!last || Math.hypot(hx - last.x, hy - last.y) > 2)) {
      pts.push({ x: hx, y: hy, t });
    }
    while (pts.length && t - pts[0].t > LIFE) pts.shift();

    ctx.clearRect(0, 0, canvas.width, canvas.height);
    ctx.globalCompositeOperation = 'lighter';
    for (const p of pts) {
      const age = (t - p.t) / LIFE;           // 0 = frisch, 1 = verglüht
      const alpha = (1 - age) * 0.12;
      const rad = ((1 - age) * 9 + 2) * dpr;
      const g = ctx.createRadialGradient(p.x * dpr, p.y * dpr, 0, p.x * dpr, p.y * dpr, rad);
      g.addColorStop(0, `rgba(${COLOR}, ${alpha})`);
      g.addColorStop(1, `rgba(${COLOR}, 0)`);
      ctx.fillStyle = g;
      ctx.beginPath();
      ctx.arc(p.x * dpr, p.y * dpr, rad, 0, Math.PI * 2);
      ctx.fill();
    }
    requestAnimationFrame(loop);
  };
  requestAnimationFrame(loop);
})();

// ===== Spotlight auf Leistungskarten: Maus leuchtet die Karte aus =====
if (window.matchMedia('(pointer: fine)').matches) {
  document.querySelectorAll('.case').forEach(card => {
    card.addEventListener('pointermove', e => {
      const r = card.getBoundingClientRect();
      card.style.setProperty('--mx', (e.clientX - r.left) + 'px');
      card.style.setProperty('--my', (e.clientY - r.top) + 'px');
    }, { passive: true });
  });
}

// ===== Scroll-Lichtlinie: Fallback für Browser ohne Scroll-Driven Animations =====
(() => {
  const light = document.querySelector('.scroll-light');
  if (!light || STATIC_MODE) return;
  if (CSS.supports('animation-timeline: scroll()')) return;
  let ticking = false;
  const update = () => {
    const max = document.documentElement.scrollHeight - window.innerHeight;
    light.style.transform = `scaleY(${max > 0 ? window.scrollY / max : 0})`;
    ticking = false;
  };
  window.addEventListener('scroll', () => {
    if (!ticking) { ticking = true; requestAnimationFrame(update); }
  }, { passive: true });
  update();
})();

// ===== Hero-Formen: sanfter Drift + Verdrängung durch die Maus =====
(() => {
  const wrap = document.querySelector('.hero__shapes');
  const hero = document.querySelector('.hero');
  if (!wrap || !hero) return;
  if (window.matchMedia('(prefers-reduced-motion: reduce)').matches || STATIC_MODE) return;

  const shapes = [...wrap.querySelectorAll('.hshape')].map((el, i) => ({
    el, x: 0, y: 0,
    phase: i * 2.1,
    speed: 0.00022 + i * 0.00007,  // jede Form treibt in eigenem Rhythmus
    amp: 10 + i * 5
  }));

  let mx = -1e4, my = -1e4;
  hero.addEventListener('pointermove', e => { mx = e.clientX; my = e.clientY; }, { passive: true });
  hero.addEventListener('pointerleave', () => { mx = my = -1e4; });

  // Nur animieren, solange der Hero sichtbar ist
  let active = true;
  new IntersectionObserver(entries => { active = entries[0].isIntersecting; }).observe(hero);

  const RADIUS = 240;  // Wirkradius der Maus
  const PUSH = 110;    // maximale Verdrängung in px

  const tick = t => {
    if (active) {
      shapes.forEach(s => {
        const r = s.el.getBoundingClientRect();
        // Ruhelage: aktuelle Position abzüglich des eigenen Versatzes
        const cx = r.left + r.width / 2 - s.x;
        const cy = r.top + r.height / 2 - s.y;
        const dx = cx - mx, dy = cy - my;
        const d = Math.hypot(dx, dy) || 1;

        // Grunddrift: ruhiges, organisches Schweben
        let tx = Math.sin(t * s.speed + s.phase) * s.amp;
        let ty = Math.cos(t * s.speed * 0.8 + s.phase) * s.amp;

        // Verdrängung: je näher die Maus, desto stärker weicht die Form aus
        if (d < RADIUS) {
          const f = Math.pow(1 - d / RADIUS, 1.5) * PUSH;
          tx += (dx / d) * f;
          ty += (dy / d) * f;
        }

        // Weiches Nachfedern in die Zielposition
        s.x += (tx - s.x) * 0.055;
        s.y += (ty - s.y) * 0.055;
        s.el.style.transform = `translate(${s.x}px, ${s.y}px) rotate(${s.x * 0.05}deg)`;
      });
    }
    requestAnimationFrame(tick);
  };
  requestAnimationFrame(tick);
})();

// ===== Hero-Video: erst nach dem Seitenaufbau laden, deutlich verlangsamt =====
// Das Poster ist das LCP-Bild. Das Video (6 MB, am Handy 2,8 MB) kommt erst, wenn die
// Seite steht. Bei Datensparmodus oder reduzierter Bewegung bleibt es beim Poster.
const heroVideo = document.querySelector('.hero__bg video');
if (heroVideo) {
  const slowDown = () => { heroVideo.playbackRate = 0.4; };
  heroVideo.addEventListener('loadeddata', slowDown);
  heroVideo.addEventListener('play', slowDown);
  const conn = navigator.connection || {};
  const skip = STATIC_MODE || conn.saveData || window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const loadVideo = () => {
    if (skip || heroVideo.src) return;
    const mobile = window.innerWidth <= 720 && heroVideo.dataset.srcMobile;
    heroVideo.src = mobile ? heroVideo.dataset.srcMobile : heroVideo.dataset.src;
    heroVideo.load();
    const p = heroVideo.play();
    if (p && p.catch) p.catch(() => {});
  };
  const start = () => {
    if (window.innerWidth <= 720) {
      // Am Handy zählt das Poster als größtes Element. Das Video kommt, sobald der Nutzer
      // scrollt oder tippt, spätestens nach sechs Sekunden.
      const once = () => { loadVideo(); ['scroll', 'touchstart', 'pointerdown', 'keydown'].forEach(ev => window.removeEventListener(ev, once)); };
      ['scroll', 'touchstart', 'pointerdown', 'keydown'].forEach(ev => window.addEventListener(ev, once, { passive: true }));
      setTimeout(once, 6000);
    } else {
      setTimeout(loadVideo, 300);
    }
  };
  if (document.readyState === 'complete') start();
  else window.addEventListener('load', start);
}

// ===== Navigation: Scroll-Zustand + Mobile-Menü =====
const nav = document.getElementById('nav');
const burger = document.getElementById('burger');
const stickyCta = document.getElementById('stickyCta');

if (nav) {
  const onScroll = () => {
    nav.classList.toggle('is-scrolled', window.scrollY > 40);
    if (stickyCta) stickyCta.classList.toggle('is-visible', window.scrollY > 500);
  };
  window.addEventListener('scroll', onScroll, { passive: true });
  if (burger) burger.addEventListener('click', () => nav.classList.toggle('is-open'));
  document.querySelectorAll('.nav__links a').forEach(a =>
    a.addEventListener('click', () => nav.classList.remove('is-open'))
  );
  onScroll();
}

// ===== Sticky Mobile CTA =====
// Sichtbarkeit steuert onScroll oben, sobald #stickyCta vorhanden ist.

// ===== Scroll-Reveal =====
const revealObserver = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (e.isIntersecting) {
      e.target.classList.add('is-visible');
      revealObserver.unobserve(e.target);
    }
  });
}, { threshold: 0.12, rootMargin: '0px 0px -5% 0px' });
if (!STATIC_MODE) {
  document.querySelectorAll('.reveal').forEach(el => revealObserver.observe(el));
}

// Fallback: falls Animationen nicht ausgelöst werden (alte Browser, Print,
// Anker-Sprung beim Laden), nach kurzer Zeit alles sichtbar machen.
setTimeout(() => {
  document.querySelectorAll('.reveal:not(.is-visible)').forEach(el => {
    const r = el.getBoundingClientRect();
    if (r.top < window.innerHeight && r.bottom > 0) el.classList.add('is-visible');
  });
}, 900);

// ===== Zähler-Animation =====
const statObserver = new IntersectionObserver(entries => {
  entries.forEach(e => {
    if (!e.isIntersecting) return;
    const el = e.target;
    const target = parseInt(el.dataset.count, 10);
    const dur = 1400;
    const start = performance.now();
    const tick = now => {
      const p = Math.min((now - start) / dur, 1);
      el.textContent = Math.round(target * (1 - Math.pow(1 - p, 3)));
      if (p < 1) requestAnimationFrame(tick);
    };
    requestAnimationFrame(tick);
    statObserver.unobserve(el);
  });
}, { threshold: 0.5 });
document.querySelectorAll('[data-count]').forEach(el => statObserver.observe(el));

// ===== Vorher/Nachher-Vergleich =====
const stage = document.getElementById('compareStage');
if (stage) {
  const clip = document.getElementById('compareClip');
  const divider = document.getElementById('compareDivider');
  const imgVorher = document.getElementById('compareVorher');
  const imgNachher = document.getElementById('compareNachher');
  const lineEl = document.getElementById('compareLine');
  let pct = 40;
  let dragging = false;

  const apply = () => {
    clip.style.clipPath = `inset(0 ${100 - pct}% 0 0)`;
    divider.style.left = pct + '%';
    stage.setAttribute('aria-valuenow', Math.round(pct));
  };
  const pctFrom = e => {
    const r = stage.getBoundingClientRect();
    return Math.min(94, Math.max(6, ((e.clientX - r.left) / r.width) * 100));
  };

  stage.addEventListener('pointerdown', e => {
    if (e.target.closest('.compare__toggle')) return;
    dragging = true;
    stage.setPointerCapture(e.pointerId);
    pct = pctFrom(e);
    apply();
  });
  stage.addEventListener('pointermove', e => {
    if (!dragging) return;
    pct = pctFrom(e);
    apply();
  });
  stage.addEventListener('pointerup', () => { dragging = false; });
  stage.addEventListener('keydown', e => {
    if (e.key === 'ArrowLeft') { e.preventDefault(); pct = Math.max(6, pct - 3); apply(); }
    if (e.key === 'ArrowRight') { e.preventDefault(); pct = Math.min(94, pct + 3); apply(); }
  });

  // Mobil: Umschalter Vorher/Nachher statt Regler
  document.querySelectorAll('#compareToggle button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('#compareToggle button').forEach(b => b.classList.remove('is-active'));
      btn.classList.add('is-active');
      stage.classList.toggle('show-nachher', btn.dataset.mode === 'nachher');
    });
  });

  // Projekt-Auswahl über Chips
  document.querySelectorAll('.compare__chips .chip').forEach(chip => {
    chip.addEventListener('click', () => {
      document.querySelectorAll('.compare__chips .chip').forEach(c => c.classList.remove('is-active'));
      chip.classList.add('is-active');
      imgVorher.srcset = chip.dataset.vorherSet || '';
      imgNachher.srcset = chip.dataset.nachherSet || '';
      imgVorher.src = chip.dataset.vorher;
      imgNachher.src = chip.dataset.nachher;
      imgVorher.alt = 'Vorher: ' + chip.dataset.alt;
      imgNachher.alt = 'Nachher: ' + chip.dataset.alt;
      // Bildausschnitt je Paar (data-pos), etwa "50% 0%" damit die Decke im Bild bleibt
      imgVorher.style.objectPosition = chip.dataset.pos || '';
      imgNachher.style.objectPosition = chip.dataset.pos || '';
      lineEl.textContent = chip.dataset.line;
      pct = 40;
      apply();
      stage.classList.remove('show-nachher');
      const toggleButtons = document.querySelectorAll('#compareToggle button');
      toggleButtons.forEach(b => b.classList.toggle('is-active', b.dataset.mode === 'vorher'));
    });
  });

  apply();
}

// ===== FAQ: immer nur ein Eintrag offen =====
// Öffnen + Schließen synchron im selben Frame, damit die Seite
// nicht zwischen zwei Layout-Zuständen weiß aufblitzt.
const faqItems = document.querySelectorAll('.faq__list details');
faqItems.forEach(d => {
  const summary = d.querySelector('summary');
  summary.addEventListener('click', e => {
    e.preventDefault();
    const willOpen = !d.open;
    faqItems.forEach(other => { other.open = false; });
    d.open = willOpen;
  });
});

// ===== Use-Case-Karten: Auswahl ins Formular übernehmen =====
document.querySelectorAll('.case[data-usecase]').forEach(card => {
  card.addEventListener('click', () => {
    const value = card.dataset.usecase;
    const radio = document.querySelector(`#usecaseOptions input[value="${value}"]`);
    if (radio) radio.checked = true;
  });
});

// ===== Zwei-Stufen-Formular =====
const form = document.getElementById('leadForm');
if (form) {
  const step1 = form.querySelector('[data-step="1"]');
  const step2 = form.querySelector('[data-step="2"]');
  const formBar = document.getElementById('formBar');
  const toStep2 = document.getElementById('toStep2');
  const backStep1 = document.getElementById('backStep1');
  const formSuccess = document.getElementById('formSuccess');

  if (toStep2 && step1 && step2) {
    toStep2.addEventListener('click', () => {
      let valid = true;
      step1.querySelectorAll('input[required]').forEach(input => {
        const ok = input.value.trim().length > 1;
        input.classList.toggle('is-invalid', !ok);
        if (!ok) valid = false;
      });
      if (!valid) return;
      step1.classList.remove('is-active');
      step2.classList.add('is-active');
      if (formBar) formBar.style.width = '100%';
    });
  }

  if (backStep1 && step1 && step2) {
    backStep1.addEventListener('click', () => {
      step2.classList.remove('is-active');
      step1.classList.add('is-active');
      if (formBar) formBar.style.width = '50%';
    });
  }

  form.addEventListener('submit', e => {
    e.preventDefault();
    if (step2) step2.classList.remove('is-active');
    const progress = form.querySelector('.form__progress');
    if (progress) progress.style.display = 'none';
    if (formSuccess) formSuccess.hidden = false;
  });
}

// ===== Endlos-Slider (Referenzen, Bewertungen, Objekt und Gewerbe) =====
// Jonas, Miro 14.09.2026: Desktop wie am Handy, wischen, nach dem letzten Bild kommt wieder
// das erste. Umsetzung: an beide Enden werden Klone gehängt; steht der Slider auf einem Klon,
// springt er ohne Animation auf das echte Bild. Die Pfeile bewegen sich leicht, bis der Slider
// zum ersten Mal bedient wurde (Klasse is-touched). Mit ?static (Screenshots) bleibt alles
// stehen und die Klone entfallen.
(() => {
  const sliders = [...document.querySelectorAll('.lslider')];
  if (!sliders.length) return;

  const setup = root => {
    const track = root.querySelector('.lslider__track');
    const dots = root.querySelector('.lslider__dots');
    const prev = root.querySelector('.lslider__btn--prev');
    const next = root.querySelector('.lslider__btn--next');
    const items = [...track.children];
    const n = items.length;
    if (!n) return;
    const perView = () => {
      const pv = parseInt(getComputedStyle(root).getPropertyValue('--perview'), 10) || 1;
      return Math.max(1, Math.min(pv, n));
    };
    // Alles sichtbar oder Screenshot-Modus: kein Slider nötig
    const staticNow = STATIC_MODE || n <= perView();
    if (staticNow) {
      root.classList.add('is-static');
      if (STATIC_MODE) {
        track.querySelectorAll('img[data-src]').forEach(img => { if (img.dataset.srcset) img.srcset = img.dataset.srcset; img.src = img.dataset.src; });
        return;
      }
    }
    const CL = Math.min(n, 3);   // Klone je Seite
    let cloned = false;
    const clone = () => {
      if (cloned) return;
      cloned = true;
      items.slice(-CL).forEach(el => { const c = el.cloneNode(true); c.setAttribute('data-clone', ''); c.setAttribute('aria-hidden', 'true'); track.insertBefore(c, track.firstChild); });
      items.slice(0, CL).forEach(el => { const c = el.cloneNode(true); c.setAttribute('data-clone', ''); c.setAttribute('aria-hidden', 'true'); track.appendChild(c); });
    };
    if (!staticNow) clone();

    const gap = () => parseFloat(getComputedStyle(track).gap) || 0;
    let stepCache = 0;
    const measure = () => { stepCache = (track.children[0] ? track.children[0].getBoundingClientRect().width : 0) + gap(); return stepCache; };
    const step = () => stepCache || measure();
    const offset = () => (cloned ? CL : 0);
    const current = () => Math.round(track.scrollLeft / (step() || 1));
    const jump = i => { track.style.scrollSnapType = 'none'; track.scrollLeft = i * step(); track.style.scrollSnapType = ''; };
    const go = (i) => track.scrollTo({ left: i * step(), behavior: 'smooth' });
    const real = i => (((i - offset()) % n) + n) % n;
    let aligned = false;

    // Bilder ab dem vierten je Spur tragen data-src und werden erst geladen, wenn sie in Sicht
    // kommen oder der Nutzer in ihre Richtung blättert. Klone teilen sich das Verhalten.
    const load = img => {
      if (!img || !img.dataset.src) return;
      if (img.dataset.srcset) img.srcset = img.dataset.srcset;
      img.src = img.dataset.src;
      delete img.dataset.src; delete img.dataset.srcset;
    };
    const loadRange = (from, to) => {
      const kids = [...track.children];
      for (let i = Math.max(0, from); i <= Math.min(kids.length - 1, to); i++) kids[i].querySelectorAll('img[data-src]').forEach(load);
    };
    const io = ('IntersectionObserver' in window) ? new IntersectionObserver(entries => {
      entries.forEach(e => { if (e.isIntersecting) { load(e.target); io.unobserve(e.target); } });
    }, { rootMargin: '400px 0px' }) : null;
    const watch = () => track.querySelectorAll('img[data-src]').forEach(img => io ? io.observe(img) : load(img));
    watch();

    if (dots) {
      dots.innerHTML = '';
      for (let i = 0; i < n; i++) { const d = document.createElement('span'); if (i === 0) d.classList.add('is-active'); dots.appendChild(d); }
    }
    const paint = () => {
      if (!dots) return;
      const r = real(current());
      [...dots.children].forEach((d, k) => d.classList.toggle('is-active', k === r));
    };
    const touched = () => root.classList.add('is-touched');

    // Startposition auf dem ersten echten Bild, sobald Breiten bekannt sind
    const align = () => {
      if (!measure()) return;          // noch unsichtbar (Reiter), später erneut
      if (cloned && !aligned) jump(offset());
      aligned = true;
      loadRange(current() - 1, current() + perView() + 1);
      paint();
    };
    requestAnimationFrame(align);
    window.addEventListener('load', align, { once: true });

    let settle;
    track.addEventListener('scroll', () => {
      paint();
      loadRange(current() - 1, current() + perView() + 1);
      clearTimeout(settle);
      settle = setTimeout(() => {
        if (!cloned) return;
        const i = current();
        if (i < offset()) jump(i + n);
        else if (i >= offset() + n) jump(i - n);
        paint();
      }, 90);
    }, { passive: true });
    track.addEventListener('pointerdown', touched, { passive: true });
    track.addEventListener('touchstart', touched, { passive: true });

    prev?.addEventListener('click', () => { touched(); loadRange(current() - 2, current() + perView()); go(current() - 1); });
    next?.addEventListener('click', () => { touched(); loadRange(current(), current() + perView() + 2); go(current() + 1); });

    let resize;
    window.addEventListener('resize', () => {
      clearTimeout(resize);
      resize = setTimeout(() => {
        if (!aligned) { align(); return; }
        const r = real(current());
        root.classList.toggle('is-static', n <= perView());
        measure();
        jump(offset() + r);
        loadRange(current() - 1, current() + perView() + 1);
      }, 120);
    }, { passive: true });
  };
  sliders.forEach(setup);

  // Reiter: nur das gewählte Panel ist sichtbar
  document.querySelectorAll('.refs').forEach(refs => {
    const tabs = refs.querySelector('.refs__tabs');
    tabs.addEventListener('click', e => {
      const t = e.target.closest('.chip');
      if (!t) return;
      tabs.querySelectorAll('.chip').forEach(c => { const on = c === t; c.classList.toggle('is-active', on); c.setAttribute('aria-selected', on ? 'true' : 'false'); });
      refs.querySelectorAll('.refs__panel').forEach(p => p.classList.toggle('is-active', p.dataset.panel === t.dataset.panel));
      // Slider im neu sichtbaren Panel auf das erste echte Bild setzen
      window.dispatchEvent(new Event('resize'));
    });
  });
})();
