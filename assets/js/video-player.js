/* Google's Shaka Player UI, serving Kidora's own MP4 files only. */
window.KidoraPlayer = {
  async mount(host, video, url) {
    shaka.polyfill.installAll();
    const player = new shaka.Player();
    let ui;
    try {
      video.preload = 'metadata';
      await player.attach(video);
      ui = new shaka.ui.Overlay(player, host, video);
      host.classList.add('kidora-player');
      ui.configure({
        controlPanelElements: host.clientWidth < 480
          ? ['time_and_duration', 'spacer', 'mute', 'overflow_menu', 'fullscreen']
          : ['play_pause', 'time_and_duration', 'spacer', 'mute', 'volume', 'overflow_menu', 'fullscreen'],
        overflowMenuButtons: ['playback_rate', 'picture_in_picture'],
        playbackRates: [0.75, 1, 1.25, 1.5, 2],
        bigButtons: ['play_pause_buffering'],
        showUIOnPaused: true,
        seekBarColors: { base: 'rgba(255,255,255,.25)', buffered: 'rgba(255,255,255,.55)', played: '#ffc93c' },
      });
      ui.getControls().getLocalization().changeLocale(['ar']);
      await player.load(url, null, 'video/mp4');
      return ui;
    } catch (error) {
      if (ui) await ui.destroy();
      else await player.destroy();
      throw error;
    }
  },
};

document.addEventListener('DOMContentLoaded', () => {
  // Admin previews initialize on demand, without loading every library file.
  document.querySelectorAll('[data-kidora-preview]').forEach(host => {
    const button = host.querySelector('button');
    button.addEventListener('click', async () => {
      button.disabled = true;
      const video = host.querySelector('video');
      try {
        await KidoraPlayer.mount(host, video, video.dataset.src);
        button.remove();
        await video.play().catch(() => {});
      } catch (error) {
        button.disabled = false;
        button.textContent = 'تعذّر تشغيل الفيديو — حاول مجدداً';
      }
    });
  });
});
