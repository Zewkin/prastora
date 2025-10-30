function toggleMenu() {
  document.querySelector('.mob_menu').classList.toggle('active_mob_menu');
}

document.addEventListener('DOMContentLoaded', () => {
  initialiseVideoBlocks();
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
