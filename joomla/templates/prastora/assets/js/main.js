function toggleMenu() {
  document.querySelector('.mob_menu').classList.toggle('active_mob_menu');
}

document.addEventListener('DOMContentLoaded', () => {
  initialiseVideoBlocks();
  initialiseLanguageSwitchers();
  initialiseSectionFade();
  initialiseProjectsDateFilter();
});

function initialiseVideoBlocks() {
  const players = document.querySelectorAll('[data-video-player]');
  players.forEach((player) => {
    const playBtn = player.querySelector('[data-video-play]');
    const overlay = player.querySelector('[data-video-overlay]');
    const frame = player.querySelector('[data-video-frame]');
    const embedUrl = player.getAttribute('data-video-embed');
    if (!playBtn || !frame || !embedUrl) {
      return;
    }

    const loadVideo = () => {
      if (player.getAttribute('data-video-playing') === '1') {
        return;
      }

      const iframe = document.createElement('iframe');
      iframe.setAttribute('src', embedUrl);
      iframe.setAttribute('title', playBtn.getAttribute('aria-label') || 'YouTube video');
      iframe.setAttribute('allowfullscreen', '');
      iframe.setAttribute('allow', 'accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share');
      iframe.setAttribute('loading', 'lazy');
      frame.innerHTML = '';
      frame.appendChild(iframe);
      player.setAttribute('data-video-playing', '1');
      if (overlay) {
        overlay.style.display = 'none';
      }
      if (playBtn) {
        playBtn.setAttribute('disabled', 'disabled');
      }
    };

    playBtn.addEventListener('click', (event) => {
      event.preventDefault();
      loadVideo();
    });
  });
}

function initialiseLanguageSwitchers() {
  const switchers = document.querySelectorAll('[data-lang-switcher]');

  if (!switchers.length) {
    return;
  }

  const closeSwitcher = (switcher) => {
    const toggle = switcher.querySelector('[data-lang-toggle]');
    const dropdown = switcher.querySelector('[data-lang-dropdown]');

    if (toggle) {
      toggle.setAttribute('aria-expanded', 'false');
    }

    if (dropdown) {
      dropdown.hidden = true;
    }

    switcher.removeAttribute('data-open');
  };

  const closeAll = () => {
    switchers.forEach((switcher) => closeSwitcher(switcher));
  };

  switchers.forEach((switcher) => {
    const toggle = switcher.querySelector('[data-lang-toggle]');
    const dropdown = switcher.querySelector('[data-lang-dropdown]');

    if (!toggle || !dropdown) {
      return;
    }

    dropdown.hidden = true;

    toggle.addEventListener('click', (event) => {
      event.preventDefault();
      const isOpen = switcher.getAttribute('data-open') === 'true';

      if (isOpen) {
        closeSwitcher(switcher);
        return;
      }

      closeAll();
      dropdown.hidden = false;
      toggle.setAttribute('aria-expanded', 'true');
      switcher.setAttribute('data-open', 'true');
    });
  });

  document.addEventListener('click', (event) => {
    if (!event.target.closest('[data-lang-switcher]')) {
      closeAll();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') {
      closeAll();
    }
  });
}

function initialiseSectionFade() {
  const homeHeader = document.querySelector('.header_wrapper');

  if (!homeHeader) {
    return;
  }

  const groups = Array.from(document.querySelectorAll('section'));
  const howItWorksBlocks = document.querySelectorAll('.how_it_works');

  howItWorksBlocks.forEach((block) => {
    if (block && !groups.includes(block)) {
      groups.push(block);
    }
  });

  if (!groups.length) {
    return;
  }

  const fadeTargets = [];
  const maxDelay = 280;
  const delayStep = 70;

  groups.forEach((group) => {
    const children = Array.from(group.children);

    children.forEach((child, index) => {
      if (!(child instanceof HTMLElement)) {
        return;
      }

      if (child.hasAttribute('data-fade-on-scroll')) {
        return;
      }

      const delay = Math.min(index * delayStep, maxDelay);
      child.setAttribute('data-fade-on-scroll', '');
      child.style.setProperty('--fade-on-scroll-delay', `${delay}ms`);
      fadeTargets.push(child);
    });
  });

  if (!fadeTargets.length) {
    return;
  }

  const prefersReducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
  const revealImmediately = () => {
    fadeTargets.forEach((target) => target.classList.add('is-visible'));
  };

  if (prefersReducedMotion.matches || !('IntersectionObserver' in window)) {
    revealImmediately();
    return;
  }

  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    },
    {
      rootMargin: '0px 0px -5% 0px',
      threshold: 0.2,
    }
  );

  fadeTargets.forEach((target) => observer.observe(target));

  const handleMotionChange = (event) => {
    if (!event.matches) {
      return;
    }

    observer.disconnect();
    revealImmediately();
  };

  if (typeof prefersReducedMotion.addEventListener === 'function') {
    prefersReducedMotion.addEventListener('change', handleMotionChange);
  } else if (typeof prefersReducedMotion.addListener === 'function') {
    prefersReducedMotion.addListener(handleMotionChange);
  }
}

function initialiseProjectsDateFilter() {
  const toggle = document.querySelector('[data-date-filter-toggle]');
  const popup = document.querySelector('[data-date-filter]');

  if (!toggle || !popup) {
    return;
  }

  const grid = popup.querySelector('[data-date-filter-grid]');
  const label = popup.querySelector('[data-date-filter-label]');
  const selection = popup.querySelector('[data-date-filter-selection]');
  const fromEl = popup.querySelector('[data-date-filter-from]');
  const separatorEl = popup.querySelector('[data-date-filter-separator]');
  const toEl = popup.querySelector('[data-date-filter-to]');
  const applyBtn = popup.querySelector('[data-date-filter-apply]');
  const resetBtn = popup.querySelector('[data-date-filter-reset]');
  const prevBtn = popup.querySelector('[data-date-filter-prev]');
  const nextBtn = popup.querySelector('[data-date-filter-next]');

  if (!grid || !label || !selection || !fromEl || !separatorEl || !toEl || !applyBtn || !resetBtn || !prevBtn || !nextBtn) {
    return;
  }

  const emptyText = selection.getAttribute('data-empty-text') || '';
  const monthNames = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
  ];

  const normaliseDate = (input) => {
    if (!(input instanceof Date) || Number.isNaN(input.getTime())) {
      return null;
    }

    return new Date(input.getFullYear(), input.getMonth(), input.getDate());
  };

  const parseMachineDate = (value) => {
    if (typeof value !== 'string' || !value.match(/^\d{4}-\d{2}-\d{2}$/)) {
      return null;
    }

    const [year, month, day] = value.split('-').map((part) => parseInt(part, 10));
    if (Number.isNaN(year) || Number.isNaN(month) || Number.isNaN(day)) {
      return null;
    }

    return normaliseDate(new Date(year, month - 1, day));
  };

  const formatMachineDate = (date) => {
    const year = date.getFullYear();
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const day = `${date.getDate()}`.padStart(2, '0');
    return `${year}-${month}-${day}`;
  };

  const formatDisplayDate = (date) => {
    const day = `${date.getDate()}`.padStart(2, '0');
    const month = `${date.getMonth() + 1}`.padStart(2, '0');
    const year = date.getFullYear();
    return `${day}.${month}.${year}`;
  };

  const datesEqual = (a, b) => {
    if (!a || !b) {
      return false;
    }

    return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
  };

  const today = normaliseDate(new Date());
  let selectedStart = normaliseDate(parseMachineDate(popup.getAttribute('data-initial-from')));
  let selectedEnd = normaliseDate(parseMachineDate(popup.getAttribute('data-initial-to')));

  if (selectedStart && !selectedEnd) {
    selectedEnd = selectedStart;
  }

  if (!selectedStart && selectedEnd) {
    selectedStart = selectedEnd;
  }

  if (selectedStart && selectedEnd && selectedEnd < selectedStart) {
    const swap = selectedStart;
    selectedStart = selectedEnd;
    selectedEnd = swap;
  }

  let displayedMonth = selectedStart
    ? new Date(selectedStart.getFullYear(), selectedStart.getMonth(), 1)
    : new Date(today.getFullYear(), today.getMonth(), 1);

  let isOpen = false;
  let outsideClickHandler = null;
  let escapeHandler = null;

  const setToggleActive = () => {
    if (selectedStart && selectedEnd) {
      toggle.classList.add('projects_nav_action_active');
    } else {
      toggle.classList.remove('projects_nav_action_active');
    }
  };

  const updateSelection = () => {
    if (!selectedStart) {
      selection.classList.add('is-empty');
      fromEl.textContent = emptyText;
      separatorEl.textContent = '';
      toEl.textContent = '';
      applyBtn.disabled = true;
      return;
    }

    selection.classList.remove('is-empty');
    fromEl.textContent = formatDisplayDate(selectedStart);

    if (selectedEnd && selectedEnd >= selectedStart && !datesEqual(selectedStart, selectedEnd)) {
      separatorEl.textContent = '—';
      toEl.textContent = formatDisplayDate(selectedEnd);
    } else {
      separatorEl.textContent = '';
      toEl.textContent = '';
    }

    applyBtn.disabled = false;
  };

  const isWithinRange = (date) => {
    if (!selectedStart) {
      return false;
    }

    const effectiveEnd = selectedEnd && selectedEnd >= selectedStart ? selectedEnd : selectedStart;
    return date >= selectedStart && date <= effectiveEnd;
  };

  const handleDaySelection = (pickedDate) => {
    const date = normaliseDate(pickedDate);

    if (!date) {
      return;
    }

    if (!selectedStart || (selectedStart && selectedEnd)) {
      selectedStart = date;
      selectedEnd = null;
    } else if (date < selectedStart) {
      selectedEnd = selectedStart;
      selectedStart = date;
    } else if (datesEqual(date, selectedStart)) {
      selectedEnd = date;
    } else {
      selectedEnd = date;
    }

    updateSelection();
    setToggleActive();
  };

  const renderCalendar = () => {
    label.textContent = `${monthNames[displayedMonth.getMonth()]} ${displayedMonth.getFullYear()}`;
    grid.innerHTML = '';

    const firstDay = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth(), 1);
    const startWeekday = firstDay.getDay(); // Sunday = 0
    const totalCells = 42;

    for (let cellIndex = 0; cellIndex < totalCells; cellIndex += 1) {
      const current = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth(), cellIndex - startWeekday + 1);
      const isCurrentMonth = current.getMonth() === displayedMonth.getMonth();

      const button = document.createElement('button');
      button.type = 'button';
      button.className = 'projects_datepicker_day';
      button.textContent = current.getDate();
      button.setAttribute('data-value', formatMachineDate(current));
      button.setAttribute('aria-pressed', isWithinRange(current) ? 'true' : 'false');

      if (!isCurrentMonth) {
        button.classList.add('is-outside');
      }

      if (today && datesEqual(current, today)) {
        button.classList.add('is-today');
      }

      if (isWithinRange(current)) {
        button.classList.add('is-in-range');
      }

      if (selectedStart && datesEqual(current, selectedStart)) {
        button.classList.add('is-selected-start');
      }

      const effectiveEnd = selectedStart
        ? (selectedEnd && selectedEnd >= selectedStart ? selectedEnd : selectedStart)
        : null;
      if (effectiveEnd && datesEqual(current, effectiveEnd)) {
        button.classList.add('is-selected-end');
      }

      button.addEventListener('click', () => {
        if (!isCurrentMonth) {
          displayedMonth = new Date(current.getFullYear(), current.getMonth(), 1);
        }

        handleDaySelection(current);
        renderCalendar();
      });

      grid.appendChild(button);
    }
  };

  const openPopup = () => {
    if (isOpen) {
      return;
    }

    isOpen = true;
    popup.hidden = false;
    toggle.setAttribute('aria-expanded', 'true');
    updateSelection();
    renderCalendar();

    outsideClickHandler = (event) => {
      if (!popup.contains(event.target) && !toggle.contains(event.target)) {
        closePopup();
      }
    };

    escapeHandler = (event) => {
      if (event.key === 'Escape') {
        closePopup();
        toggle.focus();
      }
    };

    document.addEventListener('mousedown', outsideClickHandler);
    document.addEventListener('keydown', escapeHandler);
  };

  const closePopup = () => {
    if (!isOpen) {
      return;
    }

    isOpen = false;
    popup.hidden = true;
    toggle.setAttribute('aria-expanded', 'false');

    if (outsideClickHandler) {
      document.removeEventListener('mousedown', outsideClickHandler);
      outsideClickHandler = null;
    }

    if (escapeHandler) {
      document.removeEventListener('keydown', escapeHandler);
      escapeHandler = null;
    }
  };

  toggle.addEventListener('click', (event) => {
    event.preventDefault();
    if (isOpen) {
      closePopup();
    } else {
      openPopup();
    }
  });

  toggle.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' || event.key === ' ') {
      event.preventDefault();
      if (isOpen) {
        closePopup();
      } else {
        openPopup();
      }
    }
  });

  prevBtn.addEventListener('click', () => {
    displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth() - 1, 1);
    renderCalendar();
  });

  nextBtn.addEventListener('click', () => {
    displayedMonth = new Date(displayedMonth.getFullYear(), displayedMonth.getMonth() + 1, 1);
    renderCalendar();
  });

  applyBtn.addEventListener('click', (event) => {
    event.preventDefault();
    if (!selectedStart) {
      return;
    }

    const startValue = formatMachineDate(selectedStart);
    const endDate = selectedEnd && selectedEnd >= selectedStart ? selectedEnd : selectedStart;
    const endValue = formatMachineDate(endDate);

    const params = new URLSearchParams(window.location.search);
    params.set('from', startValue);
    params.set('to', endValue);
    params.delete('start');

    closePopup();

    const queryString = params.toString();
    const nextUrl = `${window.location.pathname}${queryString ? `?${queryString}` : ''}`;
    window.location.assign(nextUrl);
  });

  resetBtn.addEventListener('click', (event) => {
    event.preventDefault();
    const params = new URLSearchParams(window.location.search);
    params.delete('from');
    params.delete('to');
    params.delete('start');

    closePopup();

    const queryString = params.toString();
    const nextUrl = `${window.location.pathname}${queryString ? `?${queryString}` : ''}`;
    window.location.assign(nextUrl);
  });

  setToggleActive();
  updateSelection();
  renderCalendar();
}
