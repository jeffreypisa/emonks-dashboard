/* global jQuery, wp, websitebeheerAgency */
jQuery(function ($) {
    'use strict';
    const name = document.getElementById('agency-name');
    if (!name) return;
    const logo = document.getElementById('agency-logo');
    const hidden = document.getElementById('agency-logo-hidden');
    const preview = document.getElementById('agency-logo-preview');
    let frame;
    function mark(url) {
        preview.replaceChildren();
        const node = document.createElement(url ? 'img' : 'span');
        if (url) {
            node.src = url;
            node.alt = name.value || 'Je webbureau';
            node.className = 'sw-agency-logo';
        } else {
            node.textContent = name.value || 'Je webbureau';
            node.className = 'sw-agency-name';
        }
        preview.appendChild(node);
    }
    name.addEventListener('input', function () {
        if (!Number(logo.value)) mark(name.value.trim().toLowerCase() === 'emonks' && hidden.value !== '1' ? websitebeheerAgency.defaultLogo : '');
        else if (preview.querySelector('img')) preview.querySelector('img').alt = name.value || 'Je webbureau';
    });
    $('[data-agency-logo]').on('click', function () {
        if (!frame) {
            frame = wp.media({title: 'Kies het bureaulogo', button: {text: 'Gebruik dit logo'}, library: {type: 'image'}, multiple: false});
            frame.on('select', function () {
                const item = frame.state().get('selection').first().toJSON();
                logo.value = item.id;
                hidden.value = '0';
                mark(item.sizes?.medium?.url || item.url);
            });
        }
        frame.open();
    });
    $('[data-agency-logo-remove]').on('click', function () {
        logo.value = '0';
        hidden.value = '1';
        mark('');
    });
});
