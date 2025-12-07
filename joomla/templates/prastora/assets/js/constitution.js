(function () {
  const getTranslator = () => {
    const joomla = window.Joomla;
    if (joomla && joomla.JText && typeof joomla.JText._ === 'function') {
      return joomla.JText._.bind(joomla.JText);
    }
    return null;
  };

  const translator = getTranslator();

  const translate = (key, fallback = '') => {
    if (translator) {
      const value = translator(key);
      if (value && value !== key) {
        return value;
      }
    }
    return fallback || key;
  };

  const translateFormat = (key, fallback = '', ...args) => {
    let text = translate(key, fallback);
    if (!args.length) {
      return text;
    }
    args.forEach((arg) => {
      text = text.replace('%s', arg);
    });
    return text;
  };

  const scrollToId = (id) => {
    if (!id) {
      return;
    }

    const target = document.getElementById(id);
    if (!target) {
      return;
    }

    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
  };

  const MOBILE_OVERLAY_CLOSE_EVENT = 'prastora:mobileOverlayClose';

  const MobileOverlay = (() => {
    const storedPositions = new Map();
    const mediaQuery = typeof window !== 'undefined' && typeof window.matchMedia === 'function'
      ? window.matchMedia('(max-width: 500px)')
      : null;

    let overlay = null;
    let content = null;
    let activeType = null;
    let initialized = false;

    const dispatchOverlayEvent = (name, detail) => {
      if (typeof document === 'undefined' || !document) {
        return;
      }
      let event;
      try {
        event = new CustomEvent(name, { detail });
      } catch (error) {
        event = document.createEvent('CustomEvent');
        event.initCustomEvent(name, true, true, detail);
      }
      document.dispatchEvent(event);
    };

    const getBody = () => document.body || null;

    const isMobile = () => {
      if (mediaQuery) {
        return mediaQuery.matches;
      }
      if (typeof window !== 'undefined' && typeof window.innerWidth === 'number') {
        return window.innerWidth <= 500;
      }
      return false;
    };

    const rememberElement = (element) => {
      if (!element || storedPositions.has(element)) {
        return;
      }
      storedPositions.set(element, {
        parent: element.parentNode,
        nextSibling: element.nextSibling,
      });
    };

    const restoreElement = (element) => {
      const meta = storedPositions.get(element);
      if (!meta || !meta.parent) {
        return;
      }
      if (meta.nextSibling && meta.nextSibling.parentNode === meta.parent) {
        meta.parent.insertBefore(element, meta.nextSibling);
      } else {
        meta.parent.appendChild(element);
      }
    };

    const isOverlayOpen = () => overlay && overlay.classList.contains('is-open');

    function close(reason = 'close') {
      if (!overlay) {
        return;
      }
      const previousType = activeType;
      if (content && content.firstElementChild) {
        const element = content.firstElementChild;
        restoreElement(element);
        content.innerHTML = '';
      }
      overlay.classList.remove('is-open');
      overlay.setAttribute('hidden', 'hidden');
      overlay.removeAttribute('data-overlay-type');
      document.documentElement.classList.remove('mobile-overlay-open');
      const body = getBody();
      if (body) {
        body.classList.remove('mobile-overlay-open');
      }
      activeType = null;
      if (previousType) {
        dispatchOverlayEvent(MOBILE_OVERLAY_CLOSE_EVENT, { type: previousType, reason });
      }
    }

    const handleBackdropClick = (event) => {
      if (event.target === overlay) {
        close('backdrop');
      }
    };

    const handleKeydown = (event) => {
      if ((event.key === 'Escape' || event.key === 'Esc') && isOverlayOpen()) {
        event.preventDefault();
        close('escape');
      }
    };

    const handleMediaChange = (event) => {
      if (!event.matches) {
        close('media-change');
      }
    };

    const ensureElements = () => {
      if (!overlay) {
        overlay = document.querySelector('[data-role="mobile-overlay"]');
        if (!overlay) {
          return false;
        }
      }

      if (!content) {
        content = overlay.querySelector('[data-role="mobile-overlay-content"]');
      }

      if (!initialized) {
        overlay.addEventListener('click', handleBackdropClick);
        document.addEventListener('keydown', handleKeydown);

        if (mediaQuery) {
          if (typeof mediaQuery.addEventListener === 'function') {
            mediaQuery.addEventListener('change', handleMediaChange);
          } else if (typeof mediaQuery.addListener === 'function') {
            mediaQuery.addListener(handleMediaChange);
          }
        }

        initialized = true;
      }

      return !!content;
    };

    const prepareForType = (type, title, element) => {
      if (!ensureElements() || !element) {
        return false;
      }

      if (!isMobile()) {
        return false;
      }

      const previousType = activeType;

      if (content.firstElementChild && content.firstElementChild !== element) {
        const current = content.firstElementChild;
        restoreElement(current);
        content.innerHTML = '';
      }

      if (previousType && previousType !== type) {
        dispatchOverlayEvent(MOBILE_OVERLAY_CLOSE_EVENT, { type: previousType, reason: 'switch' });
      }

      if (activeType === type && isOverlayOpen()) {
        return true;
      }

      rememberElement(element);
      content.appendChild(element);
      content.scrollTop = 0;
      overlay.classList.add('is-open');
      overlay.removeAttribute('hidden');
      overlay.setAttribute('data-overlay-type', type);

      document.documentElement.classList.add('mobile-overlay-open');
      const body = getBody();
      if (body) {
        body.classList.add('mobile-overlay-open');
      }

      activeType = type;
      return true;
    };

    const toggleType = (type, title, element) => {
      if (activeType === type && isOverlayOpen()) {
        close('toggle');
        return false;
      }
      return prepareForType(type, title, element);
    };

    const ensureType = (type, title, element) => {
      if (activeType === type && isOverlayOpen()) {
        return true;
      }
      return prepareForType(type, title, element);
    };

    const isOpen = (type) => {
      if (!isOverlayOpen()) {
        return false;
      }
      return !type || activeType === type;
    };

    const query = (selector) => document.querySelector(selector);

    const toggleContents = () => toggleType('contents', '', query('.articles_list_block'));
    const toggleComments = () => toggleType('comments', '', query('.comments_block'));
    const ensureContents = () => ensureType('contents', '', query('.articles_list_block'));
    const ensureComments = () => ensureType('comments', '', query('.comments_block'));

    return {
      init: () => ensureElements(),
      close,
      toggleContents,
      toggleComments,
      ensureContents,
      ensureComments,
      isOpen,
      isMobile,
    };
  })();

  const resolveAjaxEndpoint = () => {
    const joomla = window.Joomla && typeof window.Joomla.getOptions === 'function' ? window.Joomla : null;
    const paths = joomla ? joomla.getOptions('system.paths', null) : null;
    const candidates = [];

    if (paths) {
      if (paths.baseFull) {
        candidates.push(paths.baseFull);
      }
      if (paths.rootFull) {
        candidates.push(paths.rootFull);
      }
      if (paths.base) {
        candidates.push(paths.base);
      }
      if (paths.root) {
        candidates.push(paths.root);
      }
    }

    const baseElement = document.querySelector('base[href]');
    if (baseElement && baseElement.href) {
      candidates.push(baseElement.href);
    }

    candidates.push(window.location.origin || '');

    // Pick the first candidate that results in a valid absolute URL.
    for (let i = 0; i < candidates.length; i += 1) {
      const base = candidates[i];
      if (!base) {
        continue;
      }
      try {
        const normalized = base.endsWith('/') ? base : `${base}/`;
        return new URL('index.php', normalized).toString();
      } catch (error) {
        // Ignore invalid candidates and continue.
      }
    }

    return 'index.php';
  };

  const STORAGE_TOKEN_KEY = 'prastora-comment-token';
  const COOKIE_TOKEN_KEY = 'prastora_comment_token';
  const COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

  const COMMENT_ICON_PATH = 'M8.18359 0C3.66406 0 0 3.35742 0 7.5C0 11.6426 3.66406 15 8.18359 15C9.35352 15 10.4668 14.7754 11.4727 14.3701C11.5996 14.3193 11.7344 14.29 11.8691 14.29L15.9121 14.29C16.6035 14.29 17.0859 13.6045 16.8535 12.9531L15.9766 10.502C15.8926 10.2686 15.9023 10.0146 15.9824 9.78125C16.2324 9.06152 16.3691 8.29492 16.3691 7.5C16.3691 3.35742 12.7051 0 8.18359 0Z';

  const createCommentIcon = (className) => {
    const svg = document.createElementNS('http://www.w3.org/2000/svg', 'svg');
    svg.setAttribute('viewBox', '0 0 16.9121 15');
    svg.setAttribute('focusable', 'false');
    svg.setAttribute('aria-hidden', 'true');
    svg.classList.add(className);

    const path = document.createElementNS('http://www.w3.org/2000/svg', 'path');
    path.setAttribute('d', COMMENT_ICON_PATH);
    svg.appendChild(path);

    return svg;
  };

  const readTokenFromCookie = () => {
    if (typeof document === 'undefined' || !document.cookie) {
      return '';
    }
    const match = document.cookie.match(new RegExp(`${COOKIE_TOKEN_KEY}=([^;]+)`));
    return match && match[1] ? match[1] : '';
  };

  const writeTokenToCookie = (token) => {
    try {
      document.cookie = `${COOKIE_TOKEN_KEY}=${token}; path=/; max-age=${COOKIE_MAX_AGE}; SameSite=Lax`;
    } catch (error) {
      // Ignore cookie write errors.
    }
  };

  const generateToken = () => {
    if (window.crypto && typeof window.crypto.randomUUID === 'function') {
      return window.crypto.randomUUID().replace(/-/g, '');
    }
    const alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
    let value = '';
    for (let i = 0; i < 32; i += 1) {
      value += alphabet.charAt(Math.floor(Math.random() * alphabet.length));
    }
    return value;
  };

  const getClientToken = () => {
    let token = '';
    try {
      token = window.localStorage.getItem(STORAGE_TOKEN_KEY) || '';
    } catch (error) {
      token = '';
    }
    if (!token) {
      token = readTokenFromCookie();
    }
    if (!token) {
      token = generateToken();
    }
    try {
      window.localStorage.setItem(STORAGE_TOKEN_KEY, token);
    } catch (error) {
      // localStorage may be unavailable. Use cookie fallback.
    }
    writeTokenToCookie(token);
    return token;
  };

  const getCsrfTokenKey = () => {
    const joomla = window.Joomla && typeof window.Joomla.getOptions === 'function' ? window.Joomla : null;
    return joomla ? joomla.getOptions('csrf.token', '') : '';
  };

  const initReactions = () => {
    const containers = Array.from(document.querySelectorAll('.article_reactions[data-article-id][data-section-id]'));
    if (!containers.length) {
      return;
    }

    const articleId = parseInt(containers[0].dataset.articleId || '0', 10);
    if (!Number.isInteger(articleId) || articleId <= 0) {
      return;
    }

    const sections = containers
      .map((container) => container.dataset.sectionId || '')
      .filter((sectionId) => sectionId !== '');

    if (!sections.length) {
      return;
    }

    const endpoint = resolveAjaxEndpoint();
    const containerMap = new Map();
    containers.forEach((container) => {
      containerMap.set(container.dataset.sectionId, container);
    });

    const tokenKey = getCsrfTokenKey();
    const clientToken = getClientToken();
    const pendingSections = new Set();

    const setCountsForSection = (sectionId, likes, dislikes) => {
      const container = containerMap.get(sectionId);
      if (!container) {
        return;
      }

      const likeNode = container.querySelector('[data-role="count-like"]');
      const dislikeNode = container.querySelector('[data-role="count-dislike"]');
      const likeValue = Number.parseInt(likes, 10);
      const dislikeValue = Number.parseInt(dislikes, 10);

      if (likeNode) {
        likeNode.textContent = Number.isNaN(likeValue) ? '0' : String(likeValue);
      }
      if (dislikeNode) {
        dislikeNode.textContent = Number.isNaN(dislikeValue) ? '0' : String(dislikeValue);
      }
    };

    const updateCountsFromRows = (rows) => {
      if (!rows || typeof rows !== 'object') {
        return;
      }
      Object.keys(rows).forEach((sectionId) => {
        const entry = rows[sectionId];
        if (!entry) {
          return;
        }
        setCountsForSection(sectionId, entry.likes, entry.dislikes);
      });
    };

    const buildUrl = (params) => {
      const url = new URL(endpoint);
      Object.keys(params).forEach((key) => {
        if (params[key] !== undefined && params[key] !== null) {
          url.searchParams.set(key, params[key]);
        }
      });
      return url.toString();
    };


    const loadCounts = async () => {
      const chunkSize = 5;
      const tasks = [];
      for (let i = 0; i < sections.length; i += chunkSize) {
        const chunk = sections.slice(i, i + chunkSize);
        if (!chunk.length) {
          continue;
        }
        const url = buildUrl({
          option: 'com_ajax',
          plugin: 'reactions',
          format: 'json',
          article_id: articleId,
          sections: chunk.join(','),
        });
        tasks.push(
          fetch(url, { credentials: 'same-origin' })
            .then((response) => {
              if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
              }
              return response.json();
            })
            .then((payload) => {
              if (payload && payload.ok) {
                updateCountsFromRows(payload.rows || {});
              }
            })
        );
      }
      try {
        await (tasks.length ? Promise.all(tasks) : Promise.resolve());
      } catch (error) {
        console.error('Failed to load reactions', error);
      }
    };

    const vote = async (container, type) => {
      const sectionId = container.dataset.sectionId || '';
      if (!sectionId || pendingSections.has(sectionId)) {
        return;
      }

      pendingSections.add(sectionId);
      container.setAttribute('data-reaction-loading', '1');

      try {
        const params = {
          option: 'com_ajax',
          plugin: 'reactions',
          format: 'json',
          task: 'vote',
          article_id: articleId,
          section_id: sectionId,
          type,
        };

        if (tokenKey) {
          params[tokenKey] = 1;
        }

        if (clientToken) {
          params.client_token = clientToken;
        }

        const body = new URLSearchParams(params);
        const response = await fetch(endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          },
          body: body.toString(),
        });

        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }

        const payload = await response.json();
        if (payload && payload.ok) {
          setCountsForSection(sectionId, payload.likes, payload.dislikes);
          container.dataset.userReaction = type;
        } else if (payload && payload.error === 'already_voted') {
          container.dataset.userReaction = 'locked';
          await loadCounts();
        }
      } catch (error) {
        console.error('Failed to send reaction', error);
      } finally {
        pendingSections.delete(container.dataset.sectionId || '');
        container.removeAttribute('data-reaction-loading');
      }
    };

    containers.forEach((container) => {
      container.addEventListener('click', (event) => {
        const sectionId = container.dataset.sectionId || '';
        const sectionTitle = container.dataset.sectionTitle || '';

        const commentsTrigger = event.target.closest('[data-role="open-comments"]');
        if (commentsTrigger) {
          event.preventDefault();
          if (MobileOverlay && typeof MobileOverlay.ensureComments === 'function') {
            const opened = MobileOverlay.ensureComments();
            if (opened && CommentsModule && typeof CommentsModule.open === 'function') {
              CommentsModule.open();
            }
          }
          if (sectionId && CommentsModule && typeof CommentsModule.showSection === 'function') {
            CommentsModule.showSection(sectionId, sectionTitle);
          }
          return;
        }

        const commentTrigger = event.target.closest('[data-role="comment-open"]');
        if (commentTrigger) {
          event.preventDefault();
          if (MobileOverlay && typeof MobileOverlay.ensureComments === 'function') {
            const opened = MobileOverlay.ensureComments();
            if (opened && CommentsModule && typeof CommentsModule.open === 'function') {
              CommentsModule.open();
            }
          }
          if (sectionId && CommentsModule && typeof CommentsModule.startComment === 'function') {
            CommentsModule.startComment(sectionId, sectionTitle);
          }
          return;
        }

        const trigger = event.target.closest('[data-role="reaction-like"], [data-role="reaction-dislike"]');
        if (!trigger) {
          return;
        }

        event.preventDefault();

        if (!sectionId) {
          return;
        }

        const role = trigger.getAttribute('data-role');
        if (role === 'reaction-like') {
          vote(container, 'like');
        } else if (role === 'reaction-dislike') {
          vote(container, 'dislike');
        }
      });
    });

    loadCounts();
  };

  const CommentsModule = (() => {
    const state = {
      root: null,
      articleId: 0,
      endpoint: '',
      token: '',
      tokenKey: '',
      icons: {
        like: '',
        dislike: '',
        reply: '',
      },
      moderationMode: 'premoderation',
      captchaEnabled: false,
      captchaMode: 'none',
      recaptcha: {
        siteKey: '',
        badge: '',
        locale: '',
        placeholder: null,
        tokenInput: null,
        wrapper: null,
        widgetId: null,
        scriptPromise: null,
        pendingResolve: null,
        pendingReject: null,
      },
      list: null,
      listTitle: null,
      backButton: null,
      empty: null,
      form: null,
      formError: null,
      formNotice: null,
      replyContext: null,
      replyLabel: null,
      replyClear: null,
      selectionPreview: null,
      selectionText: null,
      selectionClear: null,
      textarea: null,
      allComments: [],
      commentsById: new Map(),
      commentElements: new Map(),
      highlights: new Map(),
      pendingScrollId: null,
      pendingRequest: false,
      pendingReactions: new Set(),
      sectionTitles: new Map(),
      commentCountNodes: new Map(),
      activeSectionId: null,
      activeSectionTitle: '',
      activeCommentId: null,
      currentContext: {
        parentId: null,
        sectionId: null,
        anchor: null,
      },
      selectionState: null,
      activeSelection: null,
      selectionBubble: null,
      selectionCheckHandle: null,
      skipNextBubbleClick: false,
      bubbleClickResetTimer: null,
      overlayCloseListenerAttached: false,
    };

    const isPanelCollapsed = () => state.root && state.root.classList.contains('comments_block--collapsed');

    const openPanel = (options = {}) => {
      if (!state.root) {
        return;
      }

      const wasCollapsed = isPanelCollapsed();

      if (wasCollapsed) {
        state.root.classList.remove('comments_block--collapsed');
        state.root.classList.add('comments_block--expanded');
      }

      if (options.focusTextarea && state.textarea) {
        requestAnimationFrame(() => {
          try {
            state.textarea.focus({ preventScroll: true });
          } catch (error) {
            state.textarea.focus();
          }
        });
      }

      if (options.scrollList && state.list) {
        if (typeof state.list.scrollTo === 'function') {
          state.list.scrollTo({ top: 0, behavior: options.smooth === false ? 'auto' : 'smooth' });
        } else {
          state.list.scrollTop = 0;
        }
      }
    };

    const collapsePanel = () => {
      if (!state.root) {
        return;
      }
      state.root.classList.remove('comments_block--expanded');
      state.root.classList.add('comments_block--collapsed');
    };

    const sanitizeHtmlText = (html) => {
      if (typeof html !== 'string' || html === '') {
        return '';
      }
      // Use DOMParser to safely parse HTML without executing scripts
      try {
        const parser = new DOMParser();
        const doc = parser.parseFromString(html, 'text/html');
        return doc.body.textContent || '';
      } catch (e) {
        // Fallback: strip tags manually
        return html.replace(/<[^>]*>/g, '');
      }
    };

    const truncateText = (text, maxLength) => {
      if (text.length <= maxLength) {
        return text;
      }
      return `${text.substring(0, maxLength - 1)}…`;
    };

    const getRepliesCount = (comment) => {
      if (!comment || !Array.isArray(comment.replies) || !comment.replies.length) {
        return 0;
      }

      return comment.replies.reduce((total, reply) => total + 1 + getRepliesCount(reply), 0);
    };

    const parseDateValue = (value) => {
      const timestamp = Date.parse(value || '');
      return Number.isNaN(timestamp) ? 0 : timestamp;
    };

    const sortCommentsRecursive = (comments) => {
      if (!Array.isArray(comments)) {
        return [];
      }

      return comments
        .map((comment) => ({
          ...comment,
          replies: sortCommentsRecursive(Array.isArray(comment.replies) ? comment.replies : []),
        }))
        .sort((a, b) => parseDateValue(b.created) - parseDateValue(a.created));
    };

    const filterCommentsBySection = (comments, sectionId) => {
      if (!sectionId) {
        return comments;
      }

      const result = [];

      comments.forEach((comment) => {
        const replies = filterCommentsBySection(Array.isArray(comment.replies) ? comment.replies : [], sectionId);

        if ((comment.section_id || '') === sectionId || replies.length) {
          result.push({
            ...comment,
            replies,
          });
        }
      });

      return result;
    };

    const getSectionTitle = (sectionId) => {
      if (!sectionId) {
        return '';
      }

      if (state.sectionTitles.has(sectionId)) {
        return state.sectionTitles.get(sectionId);
      }

      const escapedId = window.CSS && typeof window.CSS.escape === 'function'
        ? window.CSS.escape(sectionId)
        : sectionId.replace(/(["'\\\[\]\.#,:\s])/g, '\\$1');
      const block = document.querySelector(`.article_block_item[data-section-id="${escapedId}"]`);
      if (!block) {
        return '';
      }

      let title = block.dataset.sectionTitle || '';
      if (!title) {
        const heading = block.querySelector('.article_block_item_title h4');
        if (heading) {
          title = heading.textContent.trim();
        }
      }

      if (title) {
        state.sectionTitles.set(sectionId, title);
      }

      return title;
    };

    const clearFormError = () => {
      if (state.formError) {
        state.formError.textContent = '';
        state.formError.hidden = true;
      }
    };

    const clearFormNotice = () => {
      if (state.formNotice) {
        state.formNotice.textContent = '';
        state.formNotice.hidden = true;
      }
    };

    const showFormError = (message) => {
      if (!state.formError) {
        return;
      }
      state.formError.textContent = message;
      state.formError.hidden = false;
    };

    const showFormNotice = (message) => {
      if (!state.formNotice) {
        return;
      }
      state.formNotice.textContent = message;
      state.formNotice.hidden = false;
    };

    const loadRecaptchaScript = () => {
      if (!state.recaptcha.siteKey) {
        return Promise.reject(new Error('recaptcha_unavailable'));
      }

      if (state.recaptcha.scriptPromise) {
        return state.recaptcha.scriptPromise;
      }

      const loader = new Promise((resolve, reject) => {
        if (window.grecaptcha && typeof window.grecaptcha.render === 'function') {
          resolve(window.grecaptcha);
          return;
        }

        const existing = document.querySelector('script[data-role="recaptcha-api"]');
        if (existing) {
          existing.addEventListener('load', () => {
            if (window.grecaptcha && typeof window.grecaptcha.render === 'function') {
              resolve(window.grecaptcha);
            } else {
              reject(new Error('recaptcha_unavailable'));
            }
          });
          existing.addEventListener('error', () => reject(new Error('recaptcha_failed')));
          return;
        }

        const script = document.createElement('script');
        const localeParam = state.recaptcha.locale ? `&hl=${encodeURIComponent(state.recaptcha.locale)}` : '';
        script.src = `https://www.google.com/recaptcha/api.js?render=explicit${localeParam}`;
        script.async = true;
        script.defer = true;
        script.dataset.role = 'recaptcha-api';
        script.onload = () => {
          if (window.grecaptcha && typeof window.grecaptcha.render === 'function') {
            resolve(window.grecaptcha);
          } else {
            reject(new Error('recaptcha_unavailable'));
          }
        };
        script.onerror = () => reject(new Error('recaptcha_failed'));
        document.head.appendChild(script);
      });

      state.recaptcha.scriptPromise = loader.then(
        (value) => value,
        (error) => {
          state.recaptcha.scriptPromise = null;
          throw error;
        },
      );

      return state.recaptcha.scriptPromise;
    };

    const renderInvisibleRecaptcha = async () => {
      if (!state.form || !state.recaptcha.siteKey) {
        throw new Error('recaptcha_unavailable');
      }

      if (state.recaptcha.widgetId !== null && window.grecaptcha && typeof window.grecaptcha.getResponse === 'function') {
        return state.recaptcha.widgetId;
      }

      const grecaptcha = await loadRecaptchaScript();

      if (!state.recaptcha.placeholder) {
        state.recaptcha.placeholder = state.form.querySelector('[data-role="recaptcha-placeholder"]');
      }

      if (!state.recaptcha.placeholder) {
        const placeholder = document.createElement('div');
        placeholder.className = 'g-recaptcha';
        placeholder.setAttribute('data-role', 'recaptcha-placeholder');
        const wrapper = state.form.querySelector('[data-role="captcha-wrapper"]');
        if (wrapper) {
          wrapper.appendChild(placeholder);
          state.recaptcha.wrapper = wrapper;
        } else {
          state.form.appendChild(placeholder);
        }
        state.recaptcha.placeholder = placeholder;
      } else if (!state.recaptcha.wrapper) {
        state.recaptcha.wrapper = state.recaptcha.placeholder.closest('[data-role="captcha-wrapper"]');
      }

      if (state.recaptcha.wrapper) {
        state.recaptcha.wrapper.removeAttribute('hidden');
        state.recaptcha.wrapper.style.display = 'block';
      }

      state.recaptcha.tokenInput = state.form.querySelector('[data-role="recaptcha-response"]');

      state.recaptcha.widgetId = grecaptcha.render(state.recaptcha.placeholder, {
        sitekey: state.recaptcha.siteKey,
        size: 'invisible',
        badge: state.recaptcha.badge || 'bottomright',
        callback: (token) => {
          if (state.recaptcha.tokenInput) {
            state.recaptcha.tokenInput.value = token || '';
          }
          if (typeof state.recaptcha.pendingResolve === 'function') {
            state.recaptcha.pendingResolve(token || '');
          }
          state.recaptcha.pendingResolve = null;
          state.recaptcha.pendingReject = null;
        },
        'expired-callback': () => {
          if (state.recaptcha.tokenInput) {
            state.recaptcha.tokenInput.value = '';
          }
          if (typeof state.recaptcha.pendingReject === 'function') {
            state.recaptcha.pendingReject(new Error('captcha_expired'));
          }
          state.recaptcha.pendingResolve = null;
          state.recaptcha.pendingReject = null;
        },
        'error-callback': () => {
          if (typeof state.recaptcha.pendingReject === 'function') {
            state.recaptcha.pendingReject(new Error('captcha_error'));
          } else {
            showFormError(translate('TPL_PRASTORA_JS_ERROR_CAPTCHA', 'Captcha verification failed.'));
          }
          state.recaptcha.pendingResolve = null;
          state.recaptcha.pendingReject = null;
        },
      });

      return state.recaptcha.widgetId;
    };

    const ensureRecaptchaToken = async () => {
      await renderInvisibleRecaptcha();

      if (!window.grecaptcha || state.recaptcha.widgetId === null) {
        throw new Error('recaptcha_unavailable');
      }

      return new Promise((resolve, reject) => {
        state.recaptcha.pendingResolve = resolve;
        state.recaptcha.pendingReject = reject;

        try {
          window.grecaptcha.execute(state.recaptcha.widgetId);
        } catch (error) {
          state.recaptcha.pendingResolve = null;
          state.recaptcha.pendingReject = null;
          reject(error);
        }
      });
    };

    const resetRecaptcha = () => {
      if (window.grecaptcha && state.recaptcha.widgetId !== null) {
        try {
          window.grecaptcha.reset(state.recaptcha.widgetId);
        } catch (error) {
          // ignore
        }
      }
      if (state.recaptcha.tokenInput) {
        state.recaptcha.tokenInput.value = '';
      }
    };

    const clearReplyContext = () => {
      state.currentContext.parentId = null;
      state.currentContext.sectionId = null;
      state.currentContext.anchor = null;
      if (state.replyContext) {
        state.replyContext.hidden = true;
        state.replyContext.style.display = 'none';
      }
      if (state.replyLabel) {
        state.replyLabel.textContent = translate('TPL_PRASTORA_JS_REPLY_LABEL');
      }
    };

    const clearSelectionPreview = () => {
      if (state.selectionPreview) {
        state.selectionPreview.hidden = true;
        state.selectionPreview.style.display = 'none';
      }
      if (state.selectionText) {
        state.selectionText.textContent = '';
      }
    };

    const unwrapHighlightNode = (node) => {
      if (!node || !node.parentNode) {
        return;
      }
      const parent = node.parentNode;
      while (node.firstChild) {
        parent.insertBefore(node.firstChild, node);
      }
      parent.removeChild(node);
      if (typeof parent.normalize === 'function') {
        parent.normalize();
      }
    };

    const clearPreviewHighlight = () => {
      if (state.activeSelection && Array.isArray(state.activeSelection.nodes)) {
        state.activeSelection.nodes.forEach((node) => unwrapHighlightNode(node));
      }
      state.activeSelection = null;
      state.currentContext.anchor = null;
      state.currentContext.sectionId = null;
    };

    const clearSelectionContext = () => {
      clearPreviewHighlight();
      clearSelectionPreview();
      state.selectionState = null;
      hideSelectionBubble();
    };

    const clearAllHighlights = () => {
      state.highlights.forEach((nodes) => {
        nodes.forEach((node) => unwrapHighlightNode(node));
      });
      state.highlights.clear();
    };

    const clearActiveHighlights = () => {
      state.highlights.forEach((nodes) => {
        nodes.forEach((node) => {
          if (node && typeof node.removeAttribute === 'function') {
            node.removeAttribute('data-active');
            node.removeAttribute('data-hover');
            if (node.dataset) {
              delete node.dataset.active;
              delete node.dataset.hover;
            }
          }
        });
      });
      state.activeCommentId = null;
    };

    const ensureSelectionBubble = () => {
      if (state.selectionBubble) {
        return state.selectionBubble;
      }
      const bubble = document.createElement('button');
      bubble.type = 'button';
      bubble.className = 'comment-selection-bubble';
      bubble.style.display = 'none';
      bubble.style.opacity = '0';
      bubble.style.top = '-1000px';
      bubble.style.left = '-1000px';
      bubble.setAttribute('aria-label', translate('TPL_PRASTORA_JS_SELECTION_HINT'));
      bubble.setAttribute('data-role', 'comment-selection-trigger');

      const activateFromBubble = (event) => {
        if (event) {
          event.preventDefault();
          event.stopPropagation();
        }
        startCommentFromSelection();
      };

      const resetBubbleClickSkip = (delay = 400) => {
        if (state.bubbleClickResetTimer) {
          clearTimeout(state.bubbleClickResetTimer);
          state.bubbleClickResetTimer = null;
        }
        state.bubbleClickResetTimer = setTimeout(() => {
          state.skipNextBubbleClick = false;
          if (state.bubbleClickResetTimer) {
            clearTimeout(state.bubbleClickResetTimer);
            state.bubbleClickResetTimer = null;
          }
        }, delay);
      };

      const handleImmediateBubbleActivation = (event) => {
        state.skipNextBubbleClick = true;
        activateFromBubble(event);
        resetBubbleClickSkip(600);
      };

      bubble.addEventListener('click', (event) => {
        if (state.skipNextBubbleClick) {
          state.skipNextBubbleClick = false;
          if (state.bubbleClickResetTimer) {
            clearTimeout(state.bubbleClickResetTimer);
            state.bubbleClickResetTimer = null;
          }
          return;
        }
        activateFromBubble(event);
      });

      if (typeof window !== 'undefined' && 'PointerEvent' in window) {
        bubble.addEventListener(
          'pointerdown',
          (event) => {
            if (event.pointerType !== 'touch') {
              return;
            }
            handleImmediateBubbleActivation(event);
          },
          { passive: false }
        );
      } else {
        bubble.addEventListener(
          'touchstart',
          (event) => {
            handleImmediateBubbleActivation(event);
          },
          { passive: false }
        );
      }

      document.body.appendChild(bubble);
      state.selectionBubble = bubble;
      return bubble;
    };

    const hideSelectionBubble = () => {
      if (state.selectionBubble) {
        state.selectionBubble.style.display = 'none';
        state.selectionBubble.style.opacity = '0';
      }
    };

    const showSelectionBubble = (range) => {
      const bubble = ensureSelectionBubble();
      if (!bubble) {
        return;
      }
      const rects = range.getClientRects();
      if (!rects.length) {
        hideSelectionBubble();
        return;
      }
      const rect = rects[rects.length - 1];
      const bubbleRect = bubble.getBoundingClientRect();
      const bubbleHeight = bubbleRect.height || 40;
      const top = rect.top + rect.height / 2 - bubbleHeight / 2 - 16;
      const left = rect.right + 12;
      bubble.style.top = `${Math.round(top)}px`;
      bubble.style.left = `${Math.round(left)}px`;
      bubble.style.display = 'flex';
      bubble.style.opacity = '1';
      console.log('[comments] show bubble', { top, left, rect });
    };

    const wrapRangeWithHighlights = (range, options) => {
      if (!range || range.collapsed) {
        return [];
      }
      const { preview = false, commentId = null, sectionId = '' } = options || {};
      const highlight = document.createElement('mark');
      highlight.className = 'comment-highlight';
      if (preview) {
        highlight.classList.add('comment-highlight--preview');
      }
      if (commentId !== null) {
        highlight.dataset.commentId = String(commentId);
      }
      if (sectionId) {
        highlight.dataset.sectionId = sectionId;
      }
      const fragment = range.extractContents();
      highlight.appendChild(fragment);
      range.insertNode(highlight);
      if (highlight.parentNode && typeof highlight.parentNode.normalize === 'function') {
        highlight.parentNode.normalize();
      }
      return [highlight];
    };

    const startCommentFromSelection = () => {
      if (!state.selectionState) {
        return;
      }
      const { range, articleContent, sectionId, anchor } = state.selectionState;
      if (!anchor || !anchor.exact || !anchor.exact.trim()) {
        state.selectionState = null;
        hideSelectionBubble();
        return;
      }
      if (MobileOverlay && typeof MobileOverlay.ensureComments === 'function') {
        MobileOverlay.ensureComments();
      }
      hideSelectionBubble();
      clearPreviewHighlight();
      const highlightRange = range.cloneRange();
      const nodes = wrapRangeWithHighlights(highlightRange, {
        preview: true,
        sectionId,
        root: articleContent,
      });
      state.activeSelection = {
        nodes,
        sectionId,
        anchor,
      };
      state.currentContext.sectionId = sectionId;
      state.currentContext.anchor = anchor;
      if (sectionId) {
        openPanel({ focusTextarea: true, scrollList: true });
        setSectionFilter(sectionId, getSectionTitle(sectionId), { scrollList: true, smooth: true });
        clearFormNotice();
        const title = state.activeSectionTitle || getSectionTitle(sectionId) || '';
        if (title) {
          showFormNotice(translateFormat('TPL_PRASTORA_JS_COMMENT_CONTEXT', `You are commenting: ${title}`, title));
        }
      }
      const selection = window.getSelection && window.getSelection();
      if (selection && typeof selection.removeAllRanges === 'function') {
        selection.removeAllRanges();
      }
      state.selectionState = null;
      if (state.textarea) {
        state.textarea.focus();
      }
    };

    const positionToDom = (articleContent, index) => {
      const walker = document.createTreeWalker(articleContent, NodeFilter.SHOW_TEXT, null);
      let current = null;
      let offset = 0;
      while (walker.nextNode()) {
        const node = walker.currentNode;
        const length = node.textContent ? node.textContent.length : 0;
        if (index <= offset + length) {
          return {
            node,
            offset: index - offset,
          };
        }
        offset += length;
        current = node;
      }
      if (current) {
        return {
          node: current,
          offset: (current.textContent ? current.textContent.length : 0),
        };
      }
      return null;
    };

    const buildRangeFromOffsets = (articleContent, start, end) => {
      const doc = articleContent.ownerDocument || document;
      const startPos = positionToDom(articleContent, start);
      const endPos = positionToDom(articleContent, end);
      if (!startPos || !endPos) {
        return null;
      }
      const range = doc.createRange();
      range.setStart(startPos.node, Math.max(0, startPos.offset));
      range.setEnd(endPos.node, Math.max(0, endPos.offset));
      return range;
    };

    const findRangeByAnchor = (articleContent, anchor) => {
      if (!anchor) {
        return null;
      }
      const { start, end, exact, prefix, suffix } = anchor;
      if (typeof start === 'number' && typeof end === 'number' && end > start) {
        const offsetRange = buildRangeFromOffsets(articleContent, start, end);
        if (offsetRange) {
          const selected = offsetRange.toString();
          if (!exact || selected === exact) {
            return offsetRange;
          }
        }
      }
      const fullText = articleContent.textContent || '';
      if (!exact) {
        return null;
      }
      const safeExact = exact.trim();
      if (!safeExact) {
        return null;
      }
      let fromIndex = 0;
      while (fromIndex < fullText.length) {
        const index = fullText.indexOf(safeExact, fromIndex);
        if (index === -1) {
          break;
        }
        const prefixMatches = !prefix || fullText.slice(Math.max(0, index - prefix.length), index) === prefix;
        const suffixMatches = !suffix || fullText.slice(index + safeExact.length, index + safeExact.length + suffix.length) === suffix;
        if (prefixMatches && suffixMatches) {
          const range = buildRangeFromOffsets(articleContent, index, index + safeExact.length);
          if (range) {
            return range;
          }
        }
        fromIndex = index + safeExact.length;
      }
      return null;
    };

    const applyHighlightForComment = (comment) => {
      if (!comment || !comment.anchor || !comment.section_id) {
        return;
      }
      const section = document.querySelector(`.article_block_item[data-section-id="${comment.section_id}"]`);
      if (!section) {
        return;
      }
      const articleContent = section.querySelector('[data-role="article-content"]');
      if (!articleContent) {
        return;
      }
      const range = findRangeByAnchor(articleContent, comment.anchor);
      if (!range) {
        return;
      }
      const nodes = wrapRangeWithHighlights(range, {
        commentId: comment.id,
        sectionId: comment.section_id,
        root: articleContent,
      });
      if (nodes.length) {
        state.highlights.set(comment.id, nodes);
      }
    };

    const applyHighlightsForComments = (comments) => {
      clearAllHighlights();
      const flat = [];
      flattenComments(comments, flat);
      flat.forEach((comment) => applyHighlightForComment(comment));
    };

    const highlightComment = (commentId, active, mode = 'active') => {
      const nodes = state.highlights.get(commentId);
      if (!nodes) {
        return;
      }
      const attr = mode === 'hover' ? 'hover' : 'active';
      nodes.forEach((node) => {
        if (!node) {
          return;
        }
        if (active) {
          if (node.dataset) {
            node.dataset[attr] = '1';
          } else {
            node.setAttribute(`data-${attr}`, '1');
          }
        } else if (attr === 'active') {
          node.removeAttribute('data-active');
          if (node.dataset) {
            delete node.dataset.active;
          }
        } else {
          node.removeAttribute('data-hover');
          if (node.dataset) {
            delete node.dataset.hover;
          }
        }
      });
    };

    const focusComment = (commentId, options) => {
      openPanel();
      const opts = options || {};
      const element = state.commentElements.get(commentId);
      if (!element) {
        return;
      }
      state.commentElements.forEach((node) => {
        node.classList.remove('comment_item_active');
      });
      element.classList.add('comment_item_active');
      element.scrollIntoView({ behavior: 'smooth', block: 'center' });
      clearActiveHighlights();
      state.activeCommentId = commentId;
      highlightComment(commentId, true);
      if (opts.scrollArticle) {
        const nodes = state.highlights.get(commentId);
        const hasHighlight = !!(nodes && nodes.length);
        const scrollArticleToContext = () => {
          if (hasHighlight) {
            nodes[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
            return;
          }
          if (state.commentsById.has(commentId)) {
            const comment = state.commentsById.get(commentId);
            if (comment && comment.section_id) {
              const section = document.querySelector(`.article_block_item[data-section-id="${comment.section_id}"]`);
              if (section) {
                section.scrollIntoView({ behavior: 'smooth', block: 'center' });
              }
            }
          }
        };

        const shouldCloseOverlay = hasHighlight
          && MobileOverlay
          && typeof MobileOverlay.isOpen === 'function'
          && typeof MobileOverlay.close === 'function'
          && MobileOverlay.isOpen('comments');

        if (shouldCloseOverlay) {
          MobileOverlay.close();
          requestAnimationFrame(() => {
            requestAnimationFrame(scrollArticleToContext);
          });
        } else {
          scrollArticleToContext();
        }
      }
    };

    const handleHighlightInteraction = (event) => {
      const highlight = event.target.closest('.comment-highlight');
      if (highlight) {
        const commentId = parseInt(highlight.dataset.commentId || '', 10);
        if (commentId) {
          if (MobileOverlay && typeof MobileOverlay.ensureComments === 'function') {
            MobileOverlay.ensureComments();
          }
          focusComment(commentId, { scrollArticle: false });
        }
        return;
      }

      const trigger = event.target.closest('.comment-selection-bubble');
      if (trigger) {
        event.preventDefault();
        if (MobileOverlay && typeof MobileOverlay.ensureComments === 'function') {
          MobileOverlay.ensureComments();
        }
        startCommentFromSelection();
      }
    };

    const startReply = (comment) => {
      if (!comment) {
        return;
      }
      state.currentContext.parentId = comment.id;
      if (state.replyLabel) {
        const plain = sanitizeHtmlText(comment.body_html || comment.body || '');
        const truncated = truncateText(plain, 120);
        const fallback = `You are replying: ${truncated}`;
        state.replyLabel.textContent = translateFormat('TPL_PRASTORA_JS_REPLY_CONTEXT', fallback, truncated);
      }
      if (state.replyContext) {
        state.replyContext.hidden = false;
        state.replyContext.style.display = 'flex';
      }
      if (state.textarea) {
        state.textarea.focus();
      }
    };

    const updateEmptyState = (hasComments) => {
      if (!state.empty) {
        return;
      }
      state.empty.hidden = hasComments;
    };

    const buildReactionNode = (type, count) => {
      const wrapper = document.createElement('div');
      wrapper.className = 'article_reaction';
      wrapper.dataset.role = type === 'like' ? 'comment-like' : 'comment-dislike';
      const icon = document.createElement('img');
      icon.src = type === 'like' ? state.icons.like : state.icons.dislike;
      icon.alt = '';
      wrapper.appendChild(icon);
      const counter = document.createElement('p');
      counter.dataset.role = type === 'like' ? 'count-like' : 'count-dislike';
      counter.textContent = String(count || 0);
      wrapper.appendChild(counter);
      return wrapper;
    };

    const buildCommentElement = (comment, depth) => {
      const item = document.createElement('div');
      item.className = 'comment_item';
      item.dataset.commentId = String(comment.id);
      item.dataset.depth = String(depth);

      const header = document.createElement('div');
      header.className = 'comment_item_header';
      const name = document.createElement('span');
      name.className = 'comment_item_header_name';
      name.textContent = comment.author_name || translate('TPL_PRASTORA_JS_AUTHOR_ANONYMOUS', 'Anonymous User');
      header.appendChild(name);
      item.appendChild(header);

      const body = document.createElement('div');
      body.className = 'comment_item_content';
      // body_html is server-side escaped; fallback to textContent for safety
      if (comment.body_html) {
        body.innerHTML = comment.body_html;
      } else {
        body.textContent = comment.body || '';
      }
      item.appendChild(body);

      const actions = document.createElement('div');
      actions.className = 'article_reactions comment_reactions';
      actions.dataset.commentId = String(comment.id);
      actions.dataset.articleId = String(state.articleId);
      actions.dataset.sectionId = comment.section_id || '';
      if (comment.user_reaction) {
        actions.dataset.userReaction = comment.user_reaction;
      }

      actions.appendChild(buildReactionNode('like', comment.likes));
      actions.appendChild(buildReactionNode('dislike', comment.dislikes));

      const repliesIndicator = document.createElement('div');
      repliesIndicator.className = 'article_reaction comment_replies_count';
      repliesIndicator.dataset.role = 'comment-replies';
      repliesIndicator.dataset.commentId = String(comment.id);

      const repliesIcon = createCommentIcon('comment_replies_icon');
      repliesIndicator.appendChild(repliesIcon);

      const repliesCounter = document.createElement('p');
      repliesCounter.dataset.role = 'replies-count';
      const repliesTotal = getRepliesCount(comment);
      repliesCounter.textContent = String(repliesTotal);
      repliesIndicator.appendChild(repliesCounter);

      if (repliesTotal > 0) {
        repliesIndicator.classList.add('comment_replies_count--active');
      }

      actions.appendChild(repliesIndicator);

      const reply = document.createElement('div');
      reply.className = 'article_reaction article_commenting';
      reply.dataset.role = 'comment-reply';
      if (state.icons.reply) {
        const replyIcon = document.createElement('img');
        replyIcon.src = state.icons.reply;
        replyIcon.alt = '';
        reply.appendChild(replyIcon);
      }
      const replyText = document.createElement('p');
      replyText.textContent = translate('TPL_PRASTORA_JS_ACTION_REPLY', 'Reply');
      reply.appendChild(replyText);
      actions.appendChild(reply);

      item.appendChild(actions);

      state.commentElements.set(comment.id, item);

      item.addEventListener('mouseenter', () => {
        if (state.activeCommentId !== comment.id) {
          highlightComment(comment.id, true, 'hover');
        }
      });
      item.addEventListener('mouseleave', () => {
        highlightComment(comment.id, false, 'hover');
      });

      if (Array.isArray(comment.replies) && comment.replies.length) {
        const childrenContainer = document.createElement('div');
        childrenContainer.className = 'comment_children';
        comment.replies.forEach((child) => {
          childrenContainer.appendChild(buildCommentElement(child, depth + 1));
        });
        item.appendChild(childrenContainer);
      }

      return item;
    };

    const flattenComments = (comments, target) => {
      comments.forEach((comment) => {
        target.push(comment);
        if (Array.isArray(comment.replies) && comment.replies.length) {
          flattenComments(comment.replies, target);
        }
      });
    };

    const renderComments = (comments) => {
      state.commentElements.clear();
      if (state.list) {
        state.list.innerHTML = '';
      }
      const flat = [];
      flattenComments(comments, flat);
      updateEmptyState(flat.length > 0);
      comments.forEach((comment) => {
        if (state.list) {
          state.list.appendChild(buildCommentElement(comment, 0));
        }
      });
      applyHighlightsForComments(comments);
      if (state.activeCommentId) {
        highlightComment(state.activeCommentId, true);
      }
      if (state.pendingScrollId) {
        focusComment(state.pendingScrollId, { scrollArticle: true });
        state.pendingScrollId = null;
      }
      return flat.length;
    };

    const collectCommentMap = (comments) => {
      state.commentsById.clear();
      const stack = [];
      flattenComments(comments, stack);
      stack.forEach((comment) => {
        state.commentsById.set(comment.id, comment);
      });
    };

    const updateCommentCounters = () => {
      if (!state.commentCountNodes || state.commentCountNodes.size === 0) {
        return;
      }

      const counts = new Map();

      const accumulate = (comment) => {
        const sectionId = comment.section_id || '';
        if (sectionId) {
          counts.set(sectionId, (counts.get(sectionId) || 0) + 1);
        }
        if (Array.isArray(comment.replies)) {
          comment.replies.forEach(accumulate);
        }
      };

      state.allComments.forEach(accumulate);

      state.commentCountNodes.forEach((node, sectionId) => {
        const value = counts.get(sectionId) || 0;
        node.textContent = String(value);
      });
    };

    const getVisibleComments = () => {
      if (state.activeSectionId) {
        return filterCommentsBySection(state.allComments, state.activeSectionId);
      }

      return state.allComments;
    };

    const updateListHeader = (totalCount) => {
      let baseText = translate('TPL_PRASTORA_JS_COMMENTS_ALL', 'All comments');

      if (state.activeSectionId) {
        baseText = translate('TPL_PRASTORA_JS_COMMENTS_FILTERED', 'Comments');
      }

      if (state.listTitle) {
        const countSuffix = typeof totalCount === 'number' ? ` (${totalCount})` : '';
        state.listTitle.textContent = `${baseText}${countSuffix}`;
      }

      if (state.backButton) {
        state.backButton.hidden = !state.activeSectionId;
      }
    };

    const updateCommentView = (options = {}) => {
      const commentsToRender = getVisibleComments();
      collectCommentMap(state.allComments);
      const total = renderComments(commentsToRender);
      updateListHeader(total);

      if (options.scrollList && state.list) {
        const behavior = options.smooth === false ? 'auto' : 'smooth';
        if (typeof state.list.scrollTo === 'function') {
          state.list.scrollTo({ top: 0, behavior });
        } else {
          state.list.scrollTop = 0;
        }
      }

      return total;
    };

    const setSectionFilter = (sectionId, sectionTitle = '', options = {}) => {
      const normalizedId = sectionId || null;
      state.activeSectionId = normalizedId;
      state.activeSectionTitle = (sectionTitle || (normalizedId ? getSectionTitle(normalizedId) : '') || '').trim();
      return updateCommentView({ scrollList: options.scrollList !== false, smooth: options.smooth !== false });
    };

    const clearSectionFilter = () => {
      if (!state.activeSectionId) {
        return;
      }
      state.activeSectionId = null;
      state.activeSectionTitle = '';
      if (!state.currentContext.anchor) {
        state.currentContext.sectionId = null;
      }
      clearFormNotice();
      openPanel({ scrollList: true });
      updateCommentView({ scrollList: true, smooth: true });
    };

    const showSectionComments = (sectionId, sectionTitle = '') => {
      if (!sectionId) {
        clearSectionFilter();
        return;
      }

      clearFormNotice();
      openPanel({ scrollList: true });
      setSectionFilter(sectionId, sectionTitle, { scrollList: true, smooth: true });
      state.currentContext.parentId = null;
      state.currentContext.anchor = null;
      state.currentContext.sectionId = sectionId;
    };

    const startCommentForSection = (sectionId, sectionTitle = '') => {
      if (!sectionId) {
        return;
      }

      openPanel({ focusTextarea: true, scrollList: true });
      setSectionFilter(sectionId, sectionTitle, { scrollList: true, smooth: true });
      clearReplyContext();
      clearSelectionContext();
      state.currentContext.parentId = null;
      state.currentContext.anchor = null;
      state.currentContext.sectionId = sectionId;

      const title = state.activeSectionTitle || sectionTitle || getSectionTitle(sectionId);
      clearFormNotice();
      if (title) {
        showFormNotice(translateFormat('TPL_PRASTORA_JS_COMMENT_CONTEXT', `You are commenting: ${title}`, title));
      }

      if (state.root) {
        state.root.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      if (state.form) {
        state.form.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }

      if (state.textarea && typeof state.textarea.focus === 'function') {
        state.textarea.focus({ preventScroll: true });
      }
    };

    const loadComments = async () => {
      if (!state.articleId) {
        return;
      }
      try {
        const url = new URL(state.endpoint);
        url.searchParams.set('option', 'com_ajax');
        url.searchParams.set('plugin', 'comments');
        url.searchParams.set('format', 'json');
        url.searchParams.set('task', 'list');
        url.searchParams.set('article_id', String(state.articleId));
        if (state.token) {
          url.searchParams.set('client_token', state.token);
        }
        const response = await fetch(url.toString(), { credentials: 'same-origin' });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        if (!payload || !payload.ok) {
          throw new Error('bad_response');
        }
        const comments = Array.isArray(payload.comments) ? payload.comments : [];
        state.allComments = sortCommentsRecursive(comments);
        updateCommentCounters();
        updateCommentView({ scrollList: false });
      } catch (error) {
        console.error('Failed to load comments', error);
      }
    };

    const handleListClick = (event) => {
      if (isPanelCollapsed()) {
        openPanel();
      }
      const likeTrigger = event.target.closest('[data-role="comment-like"]');
      if (likeTrigger) {
        const container = likeTrigger.closest('.comment_reactions');
        const commentId = container ? parseInt(container.dataset.commentId || '', 10) : 0;
        if (commentId) {
          event.preventDefault();
          sendCommentReaction(commentId, 'like', container);
        }
        return;
      }
      const dislikeTrigger = event.target.closest('[data-role="comment-dislike"]');
      if (dislikeTrigger) {
        const container = dislikeTrigger.closest('.comment_reactions');
        const commentId = container ? parseInt(container.dataset.commentId || '', 10) : 0;
        if (commentId) {
          event.preventDefault();
          sendCommentReaction(commentId, 'dislike', container);
        }
        return;
      }
      const replyTrigger = event.target.closest('[data-role="comment-reply"]');
      if (replyTrigger) {
        const item = replyTrigger.closest('.comment_item');
        const commentId = item ? parseInt(item.dataset.commentId || '', 10) : 0;
        if (commentId && state.commentsById.has(commentId)) {
          event.preventDefault();
          const comment = state.commentsById.get(commentId);
          startReply(comment);
        }
        return;
      }

      const commentItem = event.target.closest('.comment_item');
      if (commentItem && !event.target.closest('.comment_reactions')) {
        const link = event.target.closest('a[href]');
        if (link) {
          return;
        }
        const commentId = parseInt(commentItem.dataset.commentId || '', 10);
        if (commentId) {
          event.preventDefault();
          focusComment(commentId, { scrollArticle: true });
        }
      }
    };

    const updateReactionCounts = (container, likes, dislikes, type) => {
      const likeNode = container.querySelector('[data-role="count-like"]');
      const dislikeNode = container.querySelector('[data-role="count-dislike"]');
      if (likeNode) {
        likeNode.textContent = String(likes);
      }
      if (dislikeNode) {
        dislikeNode.textContent = String(dislikes);
      }
      if (type) {
        container.dataset.userReaction = type;
      }
    };

    const sendCommentReaction = async (commentId, type, container) => {
      if (state.pendingReactions.has(commentId)) {
        return;
      }
      state.pendingReactions.add(commentId);
      try {
        const params = new URLSearchParams();
        params.set('option', 'com_ajax');
        params.set('plugin', 'comments');
        params.set('format', 'json');
        params.set('task', 'react');
        params.set('comment_id', String(commentId));
        params.set('type', type);
        params.set('client_token', state.token);
        if (state.tokenKey) {
          params.set(state.tokenKey, '1');
        }
        const response = await fetch(state.endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          },
          body: params.toString(),
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        if (payload && payload.ok) {
          if (container) {
            updateReactionCounts(container, payload.likes || 0, payload.dislikes || 0, type);
          }
        }
      } catch (error) {
        console.error('Failed to react on comment', error);
      } finally {
        state.pendingReactions.delete(commentId);
      }
    };

    const buildErrorMessage = (code) => {
      const keyMap = {
        missing_token: { key: 'TPL_PRASTORA_JS_ERROR_USER', fallback: 'Unable to identify the user. Please refresh the page.' },
        bad_email: { key: 'TPL_PRASTORA_JS_ERROR_EMAIL', fallback: 'Please double-check the email.' },
        empty_body: { key: 'TPL_PRASTORA_JS_ERROR_EMPTY', fallback: 'Add some text to your comment.' },
        bad_anchor: { key: 'TPL_PRASTORA_JS_ERROR_SELECTION', fallback: 'Could not capture the selection. Try again.' },
        parent_not_found: { key: 'TPL_PRASTORA_JS_ERROR_PARENT', fallback: 'The original comment is no longer available.' },
        captcha_not_configured: { key: 'TPL_PRASTORA_JS_ERROR_CAPTCHA_OFF', fallback: 'Captcha is temporarily unavailable. Try later.' },
        captcha_failed: { key: 'TPL_PRASTORA_JS_ERROR_CAPTCHA', fallback: 'Captcha verification failed.' },
      };
      const entry = keyMap[code];
      if (entry) {
        return translate(entry.key, entry.fallback);
      }
      return translate('TPL_PRASTORA_JS_ERROR_GENERIC', 'Something went wrong while sending the comment. Try later.');
    };

    const resetForm = (options = {}) => {
      const preserveNotice = Boolean(options.preserveNotice);
      if (state.textarea) {
        state.textarea.value = '';
      }
      clearReplyContext();
      clearSelectionContext();
      if (state.captchaEnabled && state.captchaMode === 'recaptcha_invisible') {
        resetRecaptcha();
      }
      if (!preserveNotice) {
        clearFormNotice();
      }
    };

    const resetCommentPanelState = () => {
      clearActiveHighlights();
      state.commentElements.forEach((node) => {
        if (node) {
          node.classList.remove('comment_item_active');
        }
      });
      resetForm();
      clearFormError();
      state.currentContext.parentId = null;
      state.currentContext.sectionId = null;
      state.currentContext.anchor = null;
      state.activeSectionId = null;
      state.activeSectionTitle = '';
      state.pendingScrollId = null;
      updateCommentView({ scrollList: false, smooth: false });
    };

    const handleMobileOverlayClose = (event) => {
      if (!event || !event.detail || event.detail.type !== 'comments') {
        return;
      }
      resetCommentPanelState();
      collapsePanel();
    };

    const handleFormSubmit = async (event) => {
      event.preventDefault();
      if (state.pendingRequest || !state.form) {
        return;
      }
      const formData = new FormData(state.form);
      const body = (formData.get('body') || '').toString().trim();
      if (!body) {
        showFormError(translate('TPL_PRASTORA_JS_ERROR_EMPTY', 'Add some text to your comment.'));
        return;
      }
      clearFormError();
      clearFormNotice();
      state.pendingRequest = true;
      const params = new URLSearchParams();
      params.set('option', 'com_ajax');
      params.set('plugin', 'comments');
      params.set('format', 'json');
      params.set('task', 'create');
      params.set('article_id', String(state.articleId));
      params.set('client_token', state.token);
      params.set('body', body);
      const authorName = (formData.get('author_name') || '').toString().trim();
      const authorEmail = (formData.get('author_email') || '').toString().trim();
      if (authorName) {
        params.set('author_name', authorName);
      }
      if (authorEmail) {
        params.set('author_email', authorEmail);
      }
      if (state.currentContext.parentId) {
        params.set('parent_id', String(state.currentContext.parentId));
      }
      if (state.currentContext.sectionId) {
        params.set('section_id', state.currentContext.sectionId);
      }
      const anchor = state.currentContext.anchor;
      if (anchor && typeof anchor === 'object') {
        if (typeof anchor.start === 'number') {
          params.set('anchor_start', String(anchor.start));
        }
        if (typeof anchor.end === 'number') {
          params.set('anchor_end', String(anchor.end));
        }
        if (anchor.exact) {
          params.set('anchor_exact', anchor.exact);
        }
        if (anchor.prefix) {
          params.set('anchor_prefix', anchor.prefix);
        }
        if (anchor.suffix) {
          params.set('anchor_suffix', anchor.suffix);
        }
      }
      if (state.tokenKey) {
        params.set(state.tokenKey, '1');
      }
      if (state.captchaEnabled) {
        try {
          if (state.captchaMode === 'recaptcha_invisible') {
            await ensureRecaptchaToken();
          }
          const captchaValueSource = state.recaptcha.tokenInput
            || state.form.querySelector('textarea[name="g-recaptcha-response"], input[name="g-recaptcha-response"]');
          const captchaValue = captchaValueSource ? (captchaValueSource.value || '').toString().trim() : (formData.get('g-recaptcha-response') || '').toString().trim();
          if (captchaValue) {
            params.set('g-recaptcha-response', captchaValue);
          }
        } catch (captchaError) {
          console.error('Failed to resolve captcha', captchaError);
          state.pendingRequest = false;
          showFormError(translate('TPL_PRASTORA_JS_ERROR_CAPTCHA', 'Captcha verification failed.'));
          return;
        }
      }
      try {
        const response = await fetch(state.endpoint, {
          method: 'POST',
          credentials: 'same-origin',
          headers: {
            'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
          },
          body: params.toString(),
        });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        if (!payload || !payload.ok) {
          const message = payload && payload.error ? buildErrorMessage(payload.error) : buildErrorMessage('');
          showFormError(message);
          return;
        }
        if (payload.pending) {
          resetForm({ preserveNotice: true });
          showFormNotice(translate('TPL_PRASTORA_JS_PENDING_NOTICE', 'Your comment will be published after moderation.'));
          return;
        }
        const comment = payload.comment;
        if (comment && comment.id) {
          state.pendingScrollId = comment.id;
        }
        resetForm();
        await loadComments();
      } catch (error) {
        console.error('Failed to submit comment', error);
        showFormError(translate('TPL_PRASTORA_JS_ERROR_GENERIC', 'Something went wrong while sending the comment. Try later.'));
      } finally {
        state.pendingRequest = false;
        if (state.captchaEnabled && state.captchaMode === 'recaptcha_invisible') {
          resetRecaptcha();
        }
      }
    };

    const computeOffsetsForRange = (articleContent, range) => {
      const doc = articleContent.ownerDocument || document;
      const clone = range.cloneRange();
      const beforeRange = doc.createRange();
      beforeRange.setStart(articleContent, 0);
      beforeRange.setEnd(clone.startContainer, clone.startOffset);
      const start = beforeRange.toString().length;
      const exact = clone.toString();
      const prefixSource = articleContent.textContent || '';
      const prefix = prefixSource.slice(Math.max(0, start - 60), start);
      const end = start + exact.length;
      const suffix = prefixSource.slice(end, end + 60);
      return {
        start,
        end,
        exact,
        prefix,
        suffix,
      };
    };

    const evaluateSelection = () => {
      console.log('[comments] evaluateSelection start');
      if (state.activeSelection) {
        console.log('[comments] abort: active selection');
        hideSelectionBubble();
        return;
      }
      const selection = window.getSelection && window.getSelection();
      if (!selection || selection.rangeCount === 0 || selection.isCollapsed) {
        console.log('[comments] abort: empty selection');
        state.selectionState = null;
        hideSelectionBubble();
        return;
      }
      const range = selection.getRangeAt(0).cloneRange();
      if (!range) {
        console.log('[comments] abort: no range');
        return;
      }
      const container = range.commonAncestorContainer;
      const element = container.nodeType === Node.ELEMENT_NODE ? container : container.parentElement;
      if (!element) {
        console.log('[comments] abort: no element', container);
        state.selectionState = null;
        hideSelectionBubble();
        return;
      }
      if (element.closest('.comments_block')) {
        console.log('[comments] abort: inside comments block');
        state.selectionState = null;
        hideSelectionBubble();
        return;
      }
      let articleContent = element.closest('[data-role="article-content"], .article_block_item_content, .article_block_preface');
      if (!articleContent) {
        const candidate = element.closest('.article_block_item');
        if (candidate) {
          articleContent = candidate.querySelector('[data-role="article-content"], .article_block_item_content, .article_block_preface');
        }
      }
      if (!articleContent) {
        console.log('[comments] abort: no articleContent', element);
        state.selectionState = null;
        hideSelectionBubble();
        return;
      }
      let section = articleContent.closest('.article_block_item') || element.closest('.article_block_item');
      if (!section && articleContent.classList && articleContent.classList.contains('article_block_preface')) {
        section = articleContent;
      }
      if (!section) {
        console.log('[comments] abort: no section', articleContent);
        state.selectionState = null;
        hideSelectionBubble();
        return;
      }
      const sectionId = section.dataset.sectionId || section.id || '';
      if (!sectionId) {
        console.log('[comments] abort: empty sectionId', section);
        state.selectionState = null;
        hideSelectionBubble();
        return;
      }
      const text = range.toString().trim();
      if (!text) {
        console.log('[comments] abort: empty text');
        state.selectionState = null;
        hideSelectionBubble();
        return;
      }
      console.log('[comments] selection detected', text);
      const anchor = computeOffsetsForRange(articleContent, range);
      if (!anchor || !anchor.exact || !anchor.exact.trim()) {
        console.log('[comments] abort: bad anchor', anchor);
        state.selectionState = null;
        hideSelectionBubble();
        return;
      }
      state.selectionState = {
        range,
        articleContent,
        sectionId,
        anchor,
      };
      showSelectionBubble(range);
    };

    const scheduleSelectionCheck = () => {
      console.log('[comments] scheduleSelectionCheck');
      if (state.selectionCheckHandle) {
        cancelAnimationFrame(state.selectionCheckHandle);
      }
      state.selectionCheckHandle = requestAnimationFrame(evaluateSelection);
    };

    const initSelectionTracking = () => {
      document.addEventListener('selectionchange', scheduleSelectionCheck);
      document.addEventListener('mouseup', scheduleSelectionCheck);
      document.addEventListener('keyup', scheduleSelectionCheck);
      window.addEventListener('scroll', hideSelectionBubble, true);
      const articleBlocks = document.querySelectorAll('[data-role="article-content"], .article_block_item_content, .article_block_item, .article_block_preface');
      console.log('[comments] initSelectionTracking blocks', articleBlocks.length);
      articleBlocks.forEach((block) => {
        block.addEventListener('mouseup', scheduleSelectionCheck);
        block.addEventListener('keyup', scheduleSelectionCheck);
        block.addEventListener('touchend', scheduleSelectionCheck);
      });
    };

    const initForm = () => {
      if (!state.form) {
        return;
      }
      state.form.addEventListener('submit', handleFormSubmit);
      if (state.selectionClear) {
        state.selectionClear.addEventListener('click', (event) => {
          event.preventDefault();
          clearSelectionContext();
        });
      }
      if (state.replyClear) {
        state.replyClear.addEventListener('click', (event) => {
          event.preventDefault();
          clearReplyContext();
        });
      }
    };

    const init = () => {
      const root = document.querySelector('.comments_block[data-comments]');
      if (!root) {
        return;
      }
      const articleId = parseInt(root.dataset.articleId || '0', 10);
      if (!Number.isInteger(articleId) || articleId <= 0) {
        return;
      }
      state.root = root;
      state.articleId = articleId;
      state.endpoint = resolveAjaxEndpoint();
      state.token = getClientToken();
      state.tokenKey = getCsrfTokenKey();
      state.icons.like = root.dataset.likeIcon || '';
      state.icons.dislike = root.dataset.dislikeIcon || '';
      state.icons.reply = root.dataset.replyIcon || '';
      state.moderationMode = root.dataset.moderationMode || 'premoderation';
      state.captchaEnabled = root.dataset.captchaEnabled === '1';
      state.captchaMode = root.dataset.captchaMode || 'none';
      state.recaptcha.siteKey = root.dataset.recaptchaSitekey || '';
      state.recaptcha.badge = root.dataset.recaptchaBadge || '';
      state.recaptcha.locale = root.dataset.recaptchaLocale || '';
      state.list = root.querySelector('[data-role="comments-list"]');
      state.listTitle = root.querySelector('[data-role="list-title"]');
      state.backButton = root.querySelector('[data-role="back-to-all"]');
      state.empty = root.querySelector('[data-role="comments-empty"]');
      state.form = root.querySelector('[data-role="comment-form"]');
      state.formError = state.form ? state.form.querySelector('[data-role="form-error"]') : null;
      state.formNotice = state.form ? state.form.querySelector('[data-role="form-notice"]') : null;
      state.replyContext = state.form ? state.form.querySelector('[data-role="reply-context"]') : null;
      state.replyLabel = state.replyContext ? state.replyContext.querySelector('[data-role="reply-label"]') : null;
      state.replyClear = state.replyContext ? state.replyContext.querySelector('[data-role="reply-clear"]') : null;
      state.selectionPreview = state.form ? state.form.querySelector('[data-role="selection-preview"]') : null;
      state.selectionText = state.selectionPreview ? state.selectionPreview.querySelector('[data-role="selection-text"]') : null;
      state.selectionClear = state.selectionPreview ? state.selectionPreview.querySelector('[data-role="selection-clear"]') : null;
      state.textarea = state.form ? state.form.querySelector('textarea[name="body"]') : null;
      state.recaptcha.placeholder = state.form ? state.form.querySelector('[data-role="recaptcha-placeholder"]') : null;
      state.recaptcha.tokenInput = state.form ? state.form.querySelector('[data-role="recaptcha-response"]') : null;
      state.recaptcha.wrapper = state.form ? state.form.querySelector('.comment_form_captcha--invisible[data-role="captcha-wrapper"]') : null;
      state.recaptcha.widgetId = null;
      state.recaptcha.scriptPromise = null;
      state.recaptcha.pendingResolve = null;
      state.recaptcha.pendingReject = null;

      // Preload and render invisible reCAPTCHA once, so the first submit doesn’t fail while the script is still loading.
      if (state.captchaEnabled && state.captchaMode === 'recaptcha_invisible') {
        renderInvisibleRecaptcha().catch(() => {
          // Ignore preload failures; the submit handler will retry and surface errors.
        });
      }

      if (state.replyContext) {
        state.replyContext.hidden = true;
        state.replyContext.style.display = 'none';
      }
      if (state.selectionPreview) {
        state.selectionPreview.hidden = true;
        state.selectionPreview.style.display = 'none';
      }
      if (state.formError) {
        state.formError.hidden = true;
      }
      if (state.formNotice) {
        state.formNotice.hidden = true;
      }

      if (state.backButton) {
        state.backButton.addEventListener('click', (event) => {
          event.preventDefault();
          clearSectionFilter();
        });
      }

      let titleIconWasCollapsed = false;
      const titleIcon = root.querySelector('.comment_list_title_icon');
      if (titleIcon) {
        titleIcon.addEventListener('pointerdown', () => {
          titleIconWasCollapsed = isPanelCollapsed();
        });
        titleIcon.addEventListener('click', (event) => {
          event.preventDefault();
          event.stopPropagation();
          if (titleIconWasCollapsed) {
            titleIconWasCollapsed = false;
            openPanel();
            return;
          }
          titleIconWasCollapsed = false;
          if (isPanelCollapsed()) {
            return;
          }
          resetCommentPanelState();
          collapsePanel();
        });
      }

      state.sectionTitles = new Map();
      document.querySelectorAll('.article_block_item[data-section-id]').forEach((block) => {
        const id = block.dataset.sectionId || block.id || '';
        if (!id) {
          return;
        }
        let title = block.dataset.sectionTitle || '';
        if (!title) {
          const heading = block.querySelector('.article_block_item_title h4');
          if (heading) {
            title = heading.textContent.trim();
          }
        }
        if (title) {
          state.sectionTitles.set(id, title.trim());
        }
      });

      state.commentCountNodes = new Map();
      document.querySelectorAll('.article_reactions[data-section-id]').forEach((container) => {
        const id = container.dataset.sectionId || '';
        if (!id) {
          return;
        }
        const countNode = container.querySelector('[data-role="open-comments"] p');
        if (countNode) {
          state.commentCountNodes.set(id, countNode);
        }
      });

      collapsePanel();

      state.root.addEventListener(
        'click',
        (event) => {
          if (!isPanelCollapsed()) {
            return;
          }

          // Allow modifier clicks (e.g., middle click) to pass through
          if (event.button !== 0) {
            return;
          }

          openPanel();
        },
        true
      );

      if (state.list) {
        state.list.addEventListener('click', handleListClick);
      }
      document.addEventListener('click', handleHighlightInteraction);
      if (!state.overlayCloseListenerAttached) {
        document.addEventListener(MOBILE_OVERLAY_CLOSE_EVENT, handleMobileOverlayClose);
        state.overlayCloseListenerAttached = true;
      }
      initForm();
      initSelectionTracking();
      ensureSelectionBubble();
      loadComments();
    };

    return {
      init,
      showSection: showSectionComments,
      startComment: startCommentForSection,
      clearSection: clearSectionFilter,
      open: () => openPanel(),
      collapse: collapsePanel,
    };
  })();

  document.addEventListener('DOMContentLoaded', () => {
    const normalizeSearchString = (value) => {
      if (!value) {
        return '';
      }
      let text = value.toString();
      if (typeof text.normalize === 'function') {
        text = text.normalize('NFKC');
      }
      text = text.trim().replace(/\s+/g, ' ');
      try {
        return text.toLocaleLowerCase('be-BY');
      } catch (error) {
        return text.toLowerCase();
      }
    };

    const initContentsSearch = () => {
      const searchInput = document.getElementById('tocSearch');
      if (!searchInput) {
        return;
      }

      const entries = Array.from(document.querySelectorAll('.articles_list_item')).map((item) => {
        const desc = item.querySelector('.articles_list_item_desc');
        const paragraphs = desc ? Array.from(desc.querySelectorAll('p')) : [];
        return {
          item,
          desc,
          paragraphs,
        };
      });

      if (!entries.length) {
        return;
      }

      const clearFilter = () => {
        entries.forEach(({ item, desc, paragraphs }) => {
          item.style.display = '';
          delete item.dataset.searchMatch;
          if (desc) {
            desc.style.display = '';
          }
          paragraphs.forEach((paragraph) => {
            paragraph.style.display = '';
          });
        });
      };

      const applyFilter = (query) => {
        entries.forEach(({ item, desc, paragraphs }) => {
          let matches = 0;
          paragraphs.forEach((paragraph) => {
            const text = normalizeSearchString(paragraph.textContent || '');
            if (text.includes(query)) {
              paragraph.style.display = '';
              matches += 1;
            } else {
              paragraph.style.display = 'none';
            }
          });

          if (matches > 0) {
            item.style.display = '';
            item.dataset.searchMatch = '1';
            if (desc) {
              desc.style.display = 'block';
            }
          } else {
            item.style.display = 'none';
            delete item.dataset.searchMatch;
            if (desc) {
              desc.style.display = '';
            }
          }
        });
      };

      const handleInput = (event) => {
        const rawValue = event && event.target ? event.target.value : searchInput.value;
        const normalizedValue = normalizeSearchString(rawValue);
        if (!normalizedValue) {
          clearFilter();
          return;
        }
        applyFilter(normalizedValue);
      };

      searchInput.addEventListener('input', handleInput);
      searchInput.addEventListener('search', handleInput);

      if (searchInput.value) {
        handleInput({ target: searchInput });
      }
    };

    document.querySelectorAll('.articles_list_item').forEach((item) => {
      const sectionId = item.dataset.sectionId || '';
      const titleTrigger = item.querySelector('.articles_list_item_title');

      if (titleTrigger) {
        titleTrigger.addEventListener('click', (event) => {
          event.preventDefault();
          const targetId = titleTrigger.dataset.target || '';
          const isMobileView = MobileOverlay && typeof MobileOverlay.isMobile === 'function' && MobileOverlay.isMobile();
          const overlayOpen = isMobileView && MobileOverlay && typeof MobileOverlay.isOpen === 'function' && MobileOverlay.isOpen('contents');

          item.classList.toggle('articles_list_item_active');

          if (!overlayOpen && targetId) {
            scrollToId(targetId);
            history.replaceState(null, '', `#${targetId}`);
          } else if (sectionId) {
            history.replaceState(null, '', `#${sectionId}`);
          }
        });
      }

      item.querySelectorAll('.articles_list_item_desc [data-target]').forEach((link) => {
        link.addEventListener('click', (event) => {
          event.preventDefault();
          const targetId = link.dataset.target;

          if (!targetId) {
            return;
          }

          item.classList.add('articles_list_item_active');

          const performScroll = () => {
            scrollToId(targetId);
            history.replaceState(null, '', `#${targetId}`);
          };

          if (MobileOverlay && typeof MobileOverlay.isOpen === 'function' && typeof MobileOverlay.close === 'function' && MobileOverlay.isOpen('contents')) {
            MobileOverlay.close();
            requestAnimationFrame(performScroll);
          } else {
            performScroll();
          }
        });
      });
    });

    initContentsSearch();

    MobileOverlay.init();

    const contentsToggle = document.querySelector('[data-role="toggle-contents"]');
    if (contentsToggle) {
      contentsToggle.addEventListener('click', (event) => {
        event.preventDefault();
        MobileOverlay.toggleContents();
      });
    }

    const commentsToggle = document.querySelector('[data-role="toggle-comments"]');
    if (commentsToggle) {
      commentsToggle.addEventListener('click', (event) => {
        event.preventDefault();
        const opened = MobileOverlay.toggleComments();
        if (opened && CommentsModule && typeof CommentsModule.open === 'function') {
          CommentsModule.open();
        }
      });
    }

    const initialHash = window.location.hash ? window.location.hash.substring(1) : '';

    if (initialHash) {
      scrollToId(initialHash);
    }

    initReactions();
    CommentsModule.init();
  });
})();
