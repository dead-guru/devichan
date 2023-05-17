$(window).ready(function () {

    var formatText = document.querySelector('.format-text');

    if (formatText !== null) {
        var button = document.createElement('button');
        button.innerHTML = 'E';
        button.title = 'Add emoji';
        button.setAttribute('type', 'button');
        button.setAttribute('data-action', 'emoji');
        button.classList.add('emoji-picker-trigger');
        button.addEventListener('click', () => {
            window.picker.toggle();
        });
        formatText.appendChild(button);

        window.picker = picmoPopup.createPopup({
            animate: true,
            showRecents: false,
            showSearch: false,
            showVariants: false,
            showPreview: false,
            showCategoryTabs: false,
            categories: ['custom'],
            visibleRows: 4,
            custom: window.emo,
        }, {
            referenceElement: button,
            triggerElement: button,
            position: 'bottom-start',
            showCloseButton: false,

        });

        window.picker.addEventListener('emoji:select', selection => {
            $('#body').val($('#body').val() + selection.emoji + ' ').focus().trigger('keyup');
        });
    }
});
