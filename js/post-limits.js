/*
 * post-limit.js
 *
 *
 * Usage:
 *   $config['additional_javascript'][] = 'js/jquery.min.js';
 *   $config['additional_javascript'][] = 'js/post-limits.js';
 *
 */
$(document).ready(function () {
    const $postForm = $('form[name="post"]');
    const textarea = $postForm.find('textarea[name="body"]');
    const limits = textarea.parent().find('.postform__limits').find('.postform__len').first();
    const limit = parseInt(limits.text());

    $('body').on('keyup', '.text_body', (e) => {
        const $this = $(e.currentTarget);
        const limits = $this.parent().find('.postform__limits').find('.postform__len');
        limits.text(limit - $this.val().length);
    });
});
