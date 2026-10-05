(function ($) {
  'use strict';

  $(document).on('mousedown', '.zsr-form .wp-ajax-submit', function () {
    if (!window.tinyMCE) {
      return;
    }

    var editor = window.tinyMCE.get('zsr_post_content');
    if (editor && !editor.isHidden()) {
      $('.zsr-form textarea[name="post_content"]').val(editor.getContent());
    }
  });
})(jQuery);
