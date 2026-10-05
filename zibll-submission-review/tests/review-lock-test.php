<?php

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

class ZsrReviewLockTestDatabase
{
    public $options = 'wp_options';
    public $rows;
    public $last_error = '';
    public $suppressed = false;
    public $prepared = array();
    public $calls = array();
    public $before = array();
    public $after_read = null;
    public $failure = '';
    public $exception = '';

    public function __construct(&$rows)
    {
        $this->rows =& $rows;
    }

    public function prepare($query, ...$args)
    {
        if (substr_count($query, '%s') !== count($args)) {
            throw new RuntimeException('Unexpected prepared argument count');
        }
        $position = 0;
        $rendered = preg_replace_callback('/%s/', function () use ($args, &$position) {
            return "'" . str_replace(array('\\', "'"), array('\\\\', "\\'"), (string) $args[$position++]) . "'";
        }, $query);
        $this->prepared[$rendered] = array($query, $args);
        return $rendered;
    }

    public function suppress_errors($suppress = true)
    {
        $previous = $this->suppressed;
        $this->suppressed = $suppress;
        return $previous;
    }

    private function start($sql, $operation)
    {
        if (!isset($this->prepared[$sql])) {
            throw new RuntimeException('Lock SQL bypassed prepare');
        }
        $this->calls[] = array($operation, $this->prepared[$sql], $this->suppressed);
        $this->last_error = '';
        if (isset($this->before[$operation])) {
            $callback = $this->before[$operation];
            unset($this->before[$operation]);
            $callback($this);
        }
        if ($this->exception === $operation) {
            throw new RuntimeException('PRIVATE_DATABASE_TOKEN_SQL');
        }
        if ($this->failure === $operation) {
            $this->last_error = 'PRIVATE_DATABASE_TOKEN_SQL';
            return false;
        }
        return $this->prepared[$sql];
    }

    public function query($sql)
    {
        $operation = strpos($sql, 'INSERT IGNORE ') === 0 ? 'insert' : 'delete';
        $prepared = $this->start($sql, $operation);
        if ($prepared === false) {
            return false;
        }
        list($query, $args) = $prepared;
        if ($query === "INSERT IGNORE INTO {$this->options} (option_name, option_value, autoload) VALUES (%s, %s, %s)") {
            if (isset($this->rows[$args[0]])) {
                return 0;
            }
            $this->rows[$args[0]] = array('value' => $args[1], 'autoload' => $args[2]);
            return 1;
        }
        if ($query === "DELETE FROM {$this->options} WHERE option_name = %s AND BINARY option_value = %s") {
            if (!isset($this->rows[$args[0]]) || $this->rows[$args[0]]['value'] !== $args[1]) {
                return 0;
            }
            unset($this->rows[$args[0]]);
            return 1;
        }
        if ($query === "DELETE FROM {$this->options} WHERE option_name = %s") {
            $exists = isset($this->rows[$args[0]]);
            unset($this->rows[$args[0]]);
            return $exists ? 1 : 0;
        }
        throw new RuntimeException('Unexpected lock write SQL');
    }

    public function get_var($sql)
    {
        $prepared = $this->start($sql, 'read');
        if ($prepared === false) {
            return null;
        }
        list($query, $args) = $prepared;
        if ($query !== "SELECT option_value FROM {$this->options} WHERE option_name = %s LIMIT 1") {
            throw new RuntimeException('Unexpected lock read SQL');
        }
        $value = isset($this->rows[$args[0]]) ? $this->rows[$args[0]]['value'] : null;
        if ($this->after_read) {
            $callback = $this->after_read;
            $this->after_read = null;
            $callback($this);
        }
        return $value;
    }

    public function get_col($sql)
    {
        $prepared = $this->start($sql, 'list');
        if ($prepared === false) {
            return array();
        }
        list($query, $args) = $prepared;
        if ($query !== "SELECT option_name FROM {$this->options} WHERE option_name LIKE %s" || $args !== array('zsr\\_lock\\_%')) {
            throw new RuntimeException('Unexpected lock cleanup SQL');
        }
        return array_keys($this->rows);
    }
}

if (!defined('ZSR_REVIEW_LOCK_FIXTURE_ONLY')) {
    define('ABSPATH', __DIR__ . '/');
    $lock_assertions = 0;
    $lock_logs = array();
    $lock_cache = array();
    $lock_cache_calls = 0;

    function lock_assert($condition, $message)
    {
        global $lock_assertions;
        $lock_assertions++;
        if (!$condition) {
            fwrite(STDERR, "FAIL: {$message}\n");
            exit(1);
        }
    }
    function zsr_log($level, $event, $context = array())
    {
        global $lock_logs;
        $lock_logs[] = array($level, $event, $context);
    }
    function wp_generate_uuid4()
    {
        static $counter = 0;
        return 'lock-fixture-' . ++$counter;
    }
    function wp_cache_add($key, $value, $group = '', $ttl = 0)
    {
        global $lock_cache, $lock_cache_calls;
        $lock_cache_calls++;
        if (isset($lock_cache[$group][$key])) {
            return false;
        }
        $lock_cache[$group][$key] = $value;
        return true;
    }

    require_once dirname(__DIR__) . '/inc/ajax/review.php';
    $rows = array();
    $first = new ZsrReviewLockTestDatabase($rows);
    $second = new ZsrReviewLockTestDatabase($rows);
    $wpdb = $first;
    $before = time();
    $owner = zsr_acquire_review_lock(101, 12);
    lock_assert(is_string($owner) && $rows['zsr_lock_101']['value'] === $owner, 'first request owns exact stored token');
    $expires = (int) explode(':', $owner)[0];
    lock_assert($expires >= $before + 60 && $expires <= time() + 60, 'lock lifetime remains sixty seconds');
    lock_assert($rows['zsr_lock_101']['autoload'] === 'no', 'review lock is not autoloaded');
    lock_assert($first->suppressed === false, 'successful acquire restores database error suppression');
    $lock_cache = array();
    $wpdb = $second;
    lock_assert(zsr_acquire_review_lock(101, 13) === false, 'separate request with empty request cache cannot acquire occupied database lock');
    lock_assert($lock_cache_calls === 0, 'request-local object cache is not a lock authority');
    $other_post = zsr_acquire_review_lock(102, 13);
    lock_assert(is_string($other_post), 'different posts are independent');
    lock_assert(zsr_release_review_lock(101, '') === false && zsr_release_review_lock(101) === false, 'empty token cannot release');
    lock_assert(zsr_release_review_lock(101, 'wrong-token') === false, 'wrong token cannot release');
    lock_assert(zsr_release_review_lock(101, strtoupper($owner)) === false, 'token ownership is case-sensitive');
    lock_assert($rows['zsr_lock_101']['value'] === $owner, 'unauthorized release leaves owner intact');
    lock_assert(zsr_release_review_lock(101, $owner) === true && !isset($rows['zsr_lock_101']), 'exact owner can release from another connection');
    lock_assert(zsr_release_review_lock(101, $owner) === false, 'release is safely repeatable');
    $replacement = zsr_acquire_review_lock(101, 12);
    lock_assert(is_string($replacement) && $replacement !== $owner, 'reacquisition has a fresh owner token');
    lock_assert(zsr_release_review_lock(101, $owner) === false && $rows['zsr_lock_101']['value'] === $replacement, 'old owner cannot delete new owner after early release');

    foreach (array(0, -1, '', '0', '01', '101a', '1 OR 1=1', '9223372036854775808', 1.1, true, array(), new stdClass()) as $invalid) {
        $call_count = count($second->calls);
        lock_assert(zsr_acquire_review_lock($invalid, 12) === false, 'invalid post ID cannot acquire');
        lock_assert(zsr_release_review_lock($invalid, $replacement) === false, 'invalid post ID cannot release');
        lock_assert(count($second->calls) === $call_count, 'invalid post ID never reaches database');
    }
    lock_assert(is_string(zsr_acquire_review_lock('103', 12)), 'canonical decimal post ID is accepted');
    lock_assert(zsr_release_review_lock(101, "' OR 1=1 --") === false, 'quoted token remains prepared data');
    $last = end($second->calls);
    lock_assert($last[1][1] === array('zsr_lock_101', "' OR 1=1 --") && strpos($last[1][0], 'OR 1=1') === false, 'release token is bound separately from SQL');

    foreach (array('', 'broken', '0:broken', '999999999999999999999:12:owner') as $invalid_token) {
        $rows['zsr_lock_104'] = array('value' => $invalid_token, 'autoload' => 'no');
        lock_assert(zsr_acquire_review_lock(104, 12) === false && $rows['zsr_lock_104']['value'] === $invalid_token, 'malformed or future lock fails closed');
    }
    $expired = (time() - 1) . ':12:expired-owner';
    $rows['zsr_lock_105'] = array('value' => $expired, 'autoload' => 'no');
    $new_owner = zsr_acquire_review_lock(105, 13);
    lock_assert(is_string($new_owner) && $new_owner !== $expired, 'expired owner can be replaced');
    lock_assert(zsr_release_review_lock(105, $expired) === false && $rows['zsr_lock_105']['value'] === $new_owner, 'expired owner cannot release replacement');

    $rows['zsr_lock_106'] = array('value' => $expired, 'autoload' => 'no');
    $wpdb = $first;
    $contender = false;
    $first->after_read = function () use ($first, $second, &$contender) {
        global $wpdb;
        $wpdb = $second;
        $contender = zsr_acquire_review_lock(106, 13);
        $wpdb = $first;
    };
    $loser = zsr_acquire_review_lock(106, 12);
    lock_assert($loser === false && is_string($contender), 'two expired readers have exactly one winner when replacement precedes stale delete');
    lock_assert($rows['zsr_lock_106']['value'] === $contender, 'stale expired compare-delete does not remove replacement');

    $rows['zsr_lock_107'] = array('value' => $expired, 'autoload' => 'no');
    $first->before['delete'] = function ($database) use ($first, $second, &$contender) {
        global $wpdb;
        $database->before['insert'] = function () use ($first, $second, &$contender) {
            global $wpdb;
            $wpdb = $second;
            $contender = zsr_acquire_review_lock(107, 13);
            $wpdb = $first;
        };
    };
    $call_count = count($first->calls);
    $loser = zsr_acquire_review_lock(107, 12);
    lock_assert($loser === false && is_string($contender) && $rows['zsr_lock_107']['value'] === $contender, 'competitor winning between expired delete and insert keeps lock');
    $inserts = array_filter(array_slice($first->calls, $call_count), function ($call) { return $call[0] === 'insert'; });
    lock_assert(count($inserts) === 2, 'expired acquisition retries insert only once');

    $rows['zsr_lock_108'] = array('value' => $expired, 'autoload' => 'no');
    $first->before['read'] = function ($database) { unset($database->rows['zsr_lock_108']); };
    lock_assert(is_string(zsr_acquire_review_lock(108, 12)), 'owner release between insert collision and read permits one retry');

    foreach (array('insert', 'read', 'delete') as $operation) {
        foreach (array('failure', 'exception') as $mode) {
            $rows['zsr_lock_109'] = array('value' => $expired, 'autoload' => 'no');
            $first->$mode = $operation;
            lock_assert(zsr_acquire_review_lock(109, 12) === false, $operation . ' ' . $mode . ' cannot grant a lock');
            lock_assert($rows['zsr_lock_109']['value'] === $expired, $operation . ' ' . $mode . ' does not remove owner');
            lock_assert($first->suppressed === false, $operation . ' ' . $mode . ' restores error suppression');
            $first->$mode = '';
        }
    }
    foreach (array('failure', 'exception') as $mode) {
        $first->$mode = 'delete';
        lock_assert(zsr_release_review_lock(109, $expired) === false && $rows['zsr_lock_109']['value'] === $expired, 'failed release does not claim success');
        lock_assert($first->suppressed === false, 'failed release restores error suppression');
        $first->$mode = '';
    }
    $first->suppressed = true;
    $held = zsr_acquire_review_lock(110, 12);
    zsr_release_review_lock(110, $held);
    lock_assert($first->suppressed === true, 'pre-existing suppression is retained');
    foreach (array_merge($first->calls, $second->calls) as $call) {
        lock_assert($call[2] === true, 'database errors never print token-bearing SQL');
    }
    $wpdb = null;
    lock_assert(zsr_acquire_review_lock(101, 12) === false && zsr_release_review_lock(101, $replacement) === false, 'missing database fails closed');
    $wpdb = new stdClass();
    lock_assert(zsr_acquire_review_lock(101, 12) === false, 'incomplete database adapter fails closed');
    $encoded_logs = json_encode($lock_logs);
    lock_assert(strpos($encoded_logs, 'PRIVATE_DATABASE_TOKEN_SQL') === false && strpos($encoded_logs, $replacement) === false && strpos($encoded_logs, 'INSERT IGNORE') === false, 'failure logs contain neither exception details, owner tokens nor SQL');

    $rows = array();
    foreach (array('zsr_lock_1', 'zsr_lock_101', 'zsr_lock_0', 'zsr_lock_01', 'zsr_lock_1_suffix', 'zsr_lock_1' . "\n", 'ZSR_LOCK_12', 'zsrXlock_9', 'unrelated_option', '_transient_zsr_lock_1') as $name) {
        $rows[$name] = array('value' => 'preserve-or-delete', 'autoload' => 'no');
    }
    $wpdb = new ZsrReviewLockTestDatabase($rows);
    define('WP_UNINSTALL_PLUGIN', true);
    require dirname(__DIR__) . '/uninstall.php';
    lock_assert(!isset($rows['zsr_lock_1']) && !isset($rows['zsr_lock_101']), 'uninstall removes canonical numeric review locks');
    lock_assert(count($rows) === 8, 'uninstall preserves non-lock and lookalike option names');
    fwrite(STDOUT, "review lock fixture tests passed ({$lock_assertions} assertions)\n");
}
