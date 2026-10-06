(function ($) {
  'use strict';

  $(function () {
    $('.zsr-review-details').each(function () {
      var box = $(this);
      var fallback = box.attr('data-error');
      $.ajax({
        url: box.attr('data-url'),
        method: 'POST',
        dataType: 'json',
        data: {
          action: 'zsr_review_details',
          post_id: box.attr('data-post-id'),
          _wpnonce: box.attr('data-nonce')
        }
      }).done(function (response) {
        if (response && response.success && response.data && typeof response.data.html === 'string') {
          box.html(response.data.html);
        } else {
          box.text(fallback).attr('role', 'alert');
        }
      }).fail(function () {
        box.text(fallback).attr('role', 'alert');
      }).always(function () {
        box.attr('aria-busy', 'false');
      });
    });
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
