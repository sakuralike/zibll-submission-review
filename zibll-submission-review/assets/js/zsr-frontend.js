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
})(jQuery);
