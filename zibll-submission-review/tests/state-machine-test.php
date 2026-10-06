<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/i18n-helpers.php';

define('ABSPATH', __DIR__ . '/');
require_once dirname(__DIR__) . '/inc/domain/state-machine.php';

function zsr_test_assert($condition, $message)
{
    if (!$condition) {
        fwrite(STDERR, "FAIL: {$message}\n");
        exit(1);
    }
}

$context = array(
    'post_type'          => 'post',
    'can_review'         => true,
    'can_review_others'  => true,
    'is_other'           => true,
    'message'            => '请补充版本号',
);

$approved = zsr_state_transition('pending', 'pending', 'approve', array(), $context);
zsr_test_assert($approved['ok'] && $approved['to_status'] === 'publish' && $approved['to_state'] === 'approved', 'approve transition');

$native = zsr_state_transition('pending', '', 'approve', array(), $context);
zsr_test_assert($native['ok'] && $native['to_status'] === 'publish', 'native pending post can be approved without plugin metadata');
$scheduled = zsr_state_transition('future', '', 'approve', array(), $context);
zsr_test_assert($scheduled['ok'] && $scheduled['to_status'] === 'future', 'approving a scheduled post preserves scheduled publication');
foreach (array('returned', 'approved', 'draft') as $previous_state) {
    $native = zsr_state_transition('pending', $previous_state, 'approve', array(), $context);
    zsr_test_assert($native['ok'], 'current pending status remains reviewable after native resubmission');
}
$scheduled_return = zsr_state_transition('future', '', 'return', array(), $context);
zsr_test_assert($scheduled_return['ok'] && $scheduled_return['to_status'] === 'draft', 'scheduled post can be returned to the author');

$rejected = zsr_state_transition('pending', 'pending', 'reject', array(), $context);
zsr_test_assert($rejected['ok'] && $rejected['to_status'] === 'pending' && $rejected['to_state'] === 'rejected', 'reject transition');

$returned = zsr_state_transition('pending', 'pending', 'return', array(), $context);
zsr_test_assert($returned['ok'] && $returned['to_status'] === 'draft' && $returned['to_state'] === 'returned', 'return transition');

$missing_message = zsr_state_transition('pending', 'pending', 'reject', array(), array_merge($context, array('message' => '')));
zsr_test_assert(!$missing_message['ok'] && $missing_message['code'] === 'message_required', 'required message');

$wrong_type = zsr_state_transition('pending', 'pending', 'approve', array(), array_merge($context, array('post_type' => 'page')));
zsr_test_assert(!$wrong_type['ok'] && $wrong_type['code'] === 'invalid_post_type', 'post type guard');

$wrong_status = zsr_state_transition('draft', 'draft', 'approve', array(), $context);
zsr_test_assert(!$wrong_status['ok'] && $wrong_status['code'] === 'invalid_source_status', 'source status guard');

$self_review = zsr_state_transition('pending', 'pending', 'approve', array(), array_merge($context, array('is_self' => true)));
zsr_test_assert(!$self_review['ok'] && $self_review['code'] === 'self_review_forbidden', 'self review guard');

$limited = zsr_state_transition('pending', 'pending', 'reject', array('zsr_reason_maxlength' => 4), $context);
zsr_test_assert($limited['ok'] && zsr_string_length($limited['message']) === 4, 'message length limit');

fwrite(STDOUT, "state-machine tests passed\n");
