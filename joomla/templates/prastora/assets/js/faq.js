document.addEventListener('DOMContentLoaded', () => {
  const faqItems = document.querySelectorAll('.faq_item');

  const toggleItem = (item, title) => {
    const expanded = item.classList.toggle('faq_item_active');
    if (title) {
      title.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }
  };

  faqItems.forEach((item) => {
    const title = item.querySelector('.faq_item_title');
    if (!title) {
      return;
    }

    title.addEventListener('click', () => toggleItem(item, title));
    title.addEventListener('keydown', (event) => {
      if (event.key === 'Enter' || event.key === ' ') {
        event.preventDefault();
        toggleItem(item, title);
      }
    });
  });
});
