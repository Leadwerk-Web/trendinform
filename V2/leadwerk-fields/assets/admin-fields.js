document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('[data-leadwerk-tab]').forEach((tab) => {
        tab.addEventListener('click', () => {
            const app = tab.closest('.leadwerk-fields-app');
            app?.querySelectorAll('[data-leadwerk-tab], [data-leadwerk-panel]').forEach((node) => node.classList.remove('is-active'));
            tab.classList.add('is-active');
            app?.querySelector(`[data-leadwerk-panel="${tab.dataset.leadwerkTab}"]`)?.classList.add('is-active');
        });
    });

    document.addEventListener('click', (event) => {
        const select = event.target.closest('.leadwerk-media-select');
        const remove = event.target.closest('.leadwerk-media-remove');
        if (select) {
            event.preventDefault();
            const field = select.closest('.leadwerk-media-field');
            const kind = field?.dataset.leadwerkMediaKind || 'image';
            const isVideo = kind === 'video';
            const frame = wp.media({ title: isVideo ? 'Video wählen' : 'Bild wählen', library: { type: isVideo ? 'video' : 'image' }, multiple: false });
            frame.on('select', () => {
                const media = frame.state().get('selection').first().toJSON();
                field.querySelector('.leadwerk-media-id').value = media.id;
                field.querySelector('.leadwerk-media-preview').innerHTML = isVideo
                    ? `<video src="${media.url}" controls muted></video>`
                    : `<img src="${media.sizes?.medium?.url || media.url}" alt="">`;
            });
            frame.open();
        }
        if (remove) {
            event.preventDefault();
            const field = remove.closest('.leadwerk-media-field');
            field.querySelector('.leadwerk-media-id').value = '0';
            field.querySelector('.leadwerk-media-preview').innerHTML = '<span>Kein Bild gewählt</span>';
        }
    });
});
