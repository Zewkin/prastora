document.addEventListener('DOMContentLoaded', () => {
  const faqItems = document.querySelectorAll('.faq_item');

  const onHeightTransitionEnd = (element, callback) => {
    if (!element) {
      return;
    }

    if (element._faqHeightHandler) {
      element.removeEventListener('transitionend', element._faqHeightHandler);
    }

    const handler = (event) => {
      if (event.target !== element || event.propertyName !== 'height') {
        return;
      }

      element.removeEventListener('transitionend', handler);
      element._faqHeightHandler = null;
      callback();
    };

    element._faqHeightHandler = handler;
    element.addEventListener('transitionend', handler);
  };

  const forceReflow = (element) => element && element.offsetHeight;

  const setExpandedState = (description, expanded, { immediate = false } = {}) => {
    if (!description) {
      return;
    }

    description.dataset.expanded = expanded ? 'true' : 'false';

    if (immediate) {
      description.style.height = expanded ? 'auto' : '0px';
      return;
    }

    if (expanded) {
      const targetHeight = description.scrollHeight;
      description.style.height = `${targetHeight}px`;
      onHeightTransitionEnd(description, () => {
        if (description.dataset.expanded === 'true') {
          description.style.height = 'auto';
        }
      });
    } else {
      if (description.style.height === 'auto' || !description.style.height) {
        description.style.height = `${description.scrollHeight}px`;
      }

      forceReflow(description);
      description.style.height = '0px';
      onHeightTransitionEnd(description, () => {
        if (description.dataset.expanded !== 'true') {
          description.style.height = '0px';
        }
      });
    }
  };

  faqItems.forEach((item) => {
    const title = item.querySelector('.faq_item_title');
    const description = item.querySelector('.faq_item_desciprition');

    if (!title || !description) {
      return;
    }

    description.style.overflow = 'hidden';

    const initiallyExpanded = item.classList.contains('faq_item_active');
    setExpandedState(description, initiallyExpanded, { immediate: true });
    title.setAttribute('aria-expanded', initiallyExpanded ? 'true' : 'false');

    const toggleItem = () => {
      const nextExpanded = !item.classList.contains('faq_item_active');
      item.classList.toggle('faq_item_active', nextExpanded);
      title.setAttribute('aria-expanded', nextExpanded ? 'true' : 'false');
      setExpandedState(description, nextExpanded);
    };

    title.addEventListener('click', toggleItem);
    title.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ' || event.key === 'Spacebar') {
        event.preventDefault();
        toggleItem();
      }
    });
  });
});
