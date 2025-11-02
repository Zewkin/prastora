(function () {
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
          if (sectionId && CommentsModule && typeof CommentsModule.showSection === 'function') {
            CommentsModule.showSection(sectionId, sectionTitle);
          }
          return;
        }

        const commentTrigger = event.target.closest('[data-role="comment-open"]');
        if (commentTrigger) {
          event.preventDefault();
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
      currentContext: {
        parentId: null,
        sectionId: null,
        anchor: null,
      },
      selectionState: null,
      activeSelection: null,
      selectionBubble: null,
      selectionCheckHandle: null,
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
      const tmp = document.createElement('div');
      tmp.innerHTML = html;
      return tmp.textContent || tmp.innerText || '';
    };

    const truncateText = (text, maxLength) => {
      if (text.length <= maxLength) {
        return text;
      }
      return `${text.substring(0, maxLength - 1)}…`;
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

    const clearReplyContext = () => {
      state.currentContext.parentId = null;
      state.currentContext.sectionId = null;
      state.currentContext.anchor = null;
      if (state.replyContext) {
        state.replyContext.hidden = true;
        state.replyContext.style.display = 'none';
      }
      if (state.replyLabel) {
        state.replyLabel.textContent = 'Вы адказваеце на каментар.';
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
      bubble.setAttribute('aria-label', 'Дадаць каментар да вылучэння');
      bubble.textContent = '+';

      bubble.addEventListener('click', (event) => {
        event.preventDefault();
        startCommentFromSelection();
      });

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
      const top = rect.top + rect.height / 2 - bubbleHeight / 2;
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
          showFormNotice(`Вы каментуеце: ${title}`);
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

    const highlightComment = (commentId, active) => {
      const nodes = state.highlights.get(commentId);
      if (!nodes) {
        return;
      }
      nodes.forEach((node) => {
        if (active) {
          node.dataset.active = '1';
        } else {
          node.removeAttribute('data-active');
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
      highlightComment(commentId, true);
      if (opts.scrollArticle) {
        const nodes = state.highlights.get(commentId);
        if (nodes && nodes.length) {
          nodes[0].scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else if (state.commentsById.has(commentId)) {
          const comment = state.commentsById.get(commentId);
          if (comment && comment.section_id) {
            const section = document.querySelector(`.article_block_item[data-section-id="${comment.section_id}"]`);
            if (section) {
              section.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
          }
        }
      }
      setTimeout(() => {
        highlightComment(commentId, false);
      }, 1500);
    };

    const handleHighlightInteraction = (event) => {
      const highlight = event.target.closest('.comment-highlight');
      if (highlight) {
        const commentId = parseInt(highlight.dataset.commentId || '', 10);
        if (commentId) {
          focusComment(commentId, { scrollArticle: false });
        }
        return;
      }

      const trigger = event.target.closest('.comment-selection-bubble');
      if (trigger) {
        event.preventDefault();
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
        state.replyLabel.textContent = `Вы адказваеце: ${truncateText(plain, 120)}`;
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
      name.textContent = comment.author_name || 'Ананімны Карыстальнік';
      header.appendChild(name);
      item.appendChild(header);

      const body = document.createElement('div');
      body.className = 'comment_item_content';
      body.innerHTML = comment.body_html || comment.body || '';
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
      replyText.textContent = 'Адказаць';
      reply.appendChild(replyText);
      actions.appendChild(reply);

      item.appendChild(actions);

      state.commentElements.set(comment.id, item);

      item.addEventListener('mouseenter', () => highlightComment(comment.id, true));
      item.addEventListener('mouseleave', () => highlightComment(comment.id, false));

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
      let baseText = 'Усе Каментары';

      if (state.activeSectionId) {
        baseText = 'Каментары';
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
        showFormNotice(`Вы каментуеце: ${title}`);
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
      switch (code) {
        case 'missing_token':
          return 'Не атрымалася вызначыць карыстальніка. Абнавіце старонку.';
        case 'bad_email':
          return 'Калі ласка, праверце email.';
        case 'empty_body':
          return 'Дадайце тэкст каментару.';
        case 'bad_anchor':
          return 'Не атрымалася зафіксаваць вылучэнне. Паспрабуйце яшчэ раз.';
        case 'parent_not_found':
          return 'Першапачатковы каментар ужо недаступны.';
        case 'captcha_not_configured':
          return 'Капча часова недаступная. Паспрабуйце пазней.';
        case 'captcha_failed':
          return 'Капча ўведзена няправільна.';
        default:
          return 'Памылка пры адпраўцы каментару. Паспрабуйце пазней.';
      }
    };

    const resetForm = (options = {}) => {
      const preserveNotice = Boolean(options.preserveNotice);
      if (state.textarea) {
        state.textarea.value = '';
      }
      clearReplyContext();
      clearSelectionContext();
      if (!preserveNotice) {
        clearFormNotice();
      }
    };

    const handleFormSubmit = async (event) => {
      event.preventDefault();
      if (state.pendingRequest || !state.form) {
        return;
      }
      const formData = new FormData(state.form);
      const body = (formData.get('body') || '').toString().trim();
      if (!body) {
        showFormError('Дадайце тэкст каментару.');
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
          showFormNotice('Ваш каментар будзе апублікаваны пасля модэрацыі.');
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
        showFormError('Памылка пры адпраўцы каментару. Паспрабуйце пазней.');
      } finally {
        state.pendingRequest = false;
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
    document.querySelectorAll('.articles_list_item').forEach((item) => {
      const sectionId = item.dataset.sectionId || '';
      const titleTrigger = item.querySelector('.articles_list_item_title');

      if (titleTrigger && titleTrigger.dataset.target) {
        titleTrigger.addEventListener('click', (event) => {
          event.preventDefault();
          item.classList.toggle('articles_list_item_active');
          scrollToId(titleTrigger.dataset.target);

          if (sectionId) {
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
          scrollToId(targetId);
          history.replaceState(null, '', `#${targetId}`);
        });
      });
    });

    const initialHash = window.location.hash ? window.location.hash.substring(1) : '';

    if (initialHash) {
      scrollToId(initialHash);
    }

    initReactions();
    CommentsModule.init();
  });
})();
