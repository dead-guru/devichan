(function($) {
    'use strict';

    var pickers = new WeakMap();

    function createPicker(button) {
        if (typeof picmoPopup === 'undefined') return null;

        var p = picmoPopup.createPopup({
            animate: false,
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

        p.addEventListener('emoji:select', function(selection) {
            var $form = $(button).closest('form');
            var $textarea = $form.find('textarea[name="body"]');
            if ($textarea.length) {
                $textarea.val($textarea.val() + selection.emoji + ' ').trigger('input');
            }
        });

        return p;
    }

    function addEmojiButtons() {
        $('.format-text').each(function() {
            if ($(this).find('.emoji-picker-trigger').length) return;
            $(this).append('<button type="button" class="emoji-picker-trigger" title="Add emoji" data-action="emoji">E</button>');
        });
    }

    $(document).on('click', '.emoji-picker-trigger', function(e) {
        e.preventDefault();
        e.stopPropagation();

        var btn = this;
        if (!$(btn).is(':visible')) return;

        var picker = pickers.get(btn);
        if (!picker) {
            picker = createPicker(btn);
            if (picker) pickers.set(btn, picker);
        }

        if (picker) {
            picker.toggle();
        }
    });

    $(document).ready(addEmojiButtons);
    $(document).on('formatText', addEmojiButtons);

})(jQuery);
