(function ($) {
  'use strict';

  $(document).on('mousedown', '.zsr-form .wp-ajax-submit', function () {
    var button = $(this);
    var form = button.closest('form');
    var action = button.attr('form-action');
    var nativeNonce = action === 'zsr_draft' ? '_wpnonce_posts_draft' : '_wpnonce_posts_save';
    form.find('[name="_wpnonce"]').val(form.find('[name="' + nativeNonce + '"]').val());

    if (!window.tinyMCE) {
      return;
    }

    var editor = window.tinyMCE.get('zsr_post_content');
    if (editor && !editor.isHidden()) {
      $('.zsr-form textarea[name="post_content"]').val(editor.getContent());
    }
  });

  $(document).on('mousedown', '.zsr-review-form .wp-ajax-submit', function () {
    var button = $(this);
    var form = button.closest('form');
    var method = button.attr('data-review-method');
    form.find('[name="method"]').remove();
    $('<input>', { type: 'hidden', name: 'method', value: method }).appendTo(form);
  });

  $(document).on('zib_ajax.success', '.zsr-review-form .wp-ajax-submit', function (event, response) {
    if (!response || response.error || !response.notifications || response.reload !== false) {
      return;
    }
    var form = $(this).closest('form');
    form.find('.wp-ajax-submit').prop('disabled', true);
    form.find('.zsr-notification-result').remove();
    $('<p>', { 'class': 'zsr-notification-result c-yellow', role: 'status' }).text(response.msg).appendTo(form);
  });
})(jQuery);
