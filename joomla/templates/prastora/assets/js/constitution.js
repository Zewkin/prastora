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

    const joomla = window.Joomla && typeof window.Joomla.getOptions === 'function' ? window.Joomla : null;
    const tokenKey = joomla ? joomla.getOptions('csrf.token', '') : '';
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
      try {
        const url = buildUrl({
          option: 'com_ajax',
          plugin: 'reactions',
          format: 'json',
          article_id: articleId,
          sections: sections.join(','),
        });
        const response = await fetch(url, { credentials: 'same-origin' });
        if (!response.ok) {
          throw new Error(`HTTP ${response.status}`);
        }
        const payload = await response.json();
        if (payload && payload.ok) {
          updateCountsFromRows(payload.rows || {});
        }
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
        const trigger = event.target.closest('[data-role="reaction-like"], [data-role="reaction-dislike"]');
        if (!trigger) {
          return;
        }

        event.preventDefault();

        const sectionId = container.dataset.sectionId || '';
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
  });
})();
