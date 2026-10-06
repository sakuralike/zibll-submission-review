'use strict';

const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');

const document = {};
const handlers = new Map();

function element(tag, attrs = {}) {
  return { tag, attrs, children: [], props: {}, textContent: '', textWrites: 0, parent: null };
}

function append(parent, child) {
  child.parent = parent;
  parent.children.push(child);
  return child;
}

function matches(node, selector) {
  if (selector.startsWith('.')) {
    return (node.attrs.class || '').split(/\s+/).includes(selector.slice(1));
  }
  assert.equal(selector, '[name="method"]');
  return node.attrs.name === 'method';
}

function $(value, attrs) {
  if (typeof value === 'function') return;
  if (value === document) {
    return {
      on(event, selector, handler) {
        handlers.set(event + ' ' + selector, handler);
      }
    };
  }
  const nodes = Array.isArray(value) ? value : [typeof value === 'string' ? element(value.slice(1, -1), attrs) : value];
  return {
    nodes,
    attr(name) {
      return nodes[0].attrs[name];
    },
    closest(selector) {
      assert.equal(selector, 'form');
      let current = nodes[0];
      while (current && current.tag !== 'form') current = current.parent;
      assert.ok(current, 'event target belongs to a form');
      return $(current);
    },
    find(selector) {
      return $(nodes.flatMap(node => node.children.filter(child => matches(child, selector))));
    },
    prop(name, value) {
      for (const node of nodes) node.props[name] = value;
      return this;
    },
    remove() {
      for (const node of nodes) {
        node.parent.children.splice(node.parent.children.indexOf(node), 1);
        node.parent = null;
      }
      return this;
    },
    text(value) {
      for (const node of nodes) {
        node.textContent = value;
        node.textWrites++;
      }
      return this;
    },
    html() {
      assert.fail('notification messages must not be inserted as HTML');
    },
    appendTo(target) {
      for (const node of nodes) append(target.nodes[0], node);
      return this;
    }
  };
}

vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/zsr-frontend.js'), 'utf8'), {
  jQuery: $, document, window: {}
});

const selector = '.zsr-review-form .wp-ajax-submit';
const success = handlers.get('zib_ajax.success ' + selector);
const mousedown = handlers.get('mousedown ' + selector);
assert.equal(typeof success, 'function', 'registers the native success event');
assert.equal(typeof mousedown, 'function', 'retains review method selection');

function fixture() {
  const form = element('form', { class: 'zsr-review-form' });
  const approve = append(form, element('button', { class: 'wp-ajax-submit', 'data-review-method': 'approve' }));
  const reject = append(form, element('button', { class: 'wp-ajax-submit', 'data-review-method': 'reject' }));
  const other = append(form, element('button', { class: 'other-button' }));
  append(form, element('p', { class: 'zsr-notification-result' }));
  append(form, element('p', { class: 'zsr-notification-result' }));
  return { form, approve, reject, other };
}

function snapshot(form) {
  return form.children.map(node => ({ tag: node.tag, attrs: { ...node.attrs }, props: { ...node.props }, textContent: node.textContent, textWrites: node.textWrites }));
}

for (const status of ['failed', 'skipped']) {
  const current = fixture();
  const unrelated = fixture();
  const unrelatedBefore = snapshot(unrelated.form);
  const msg = '<img src=x onerror="alert(1)"> review saved, notification ' + status;
  const response = { error: false, reload: false, notifications: { email: { status } }, msg };
  success.call(current.approve, { type: 'zib_ajax.success' }, response);
  assert.equal(current.approve.props.disabled, true);
  assert.equal(current.reject.props.disabled, true);
  assert.equal(current.other.props.disabled, undefined);
  assert.deepEqual(snapshot(unrelated.form), unrelatedBefore, 'other forms are unchanged');
  let notices = current.form.children.filter(node => matches(node, '.zsr-notification-result'));
  assert.equal(notices.length, 1, 'replaces all existing notification messages');
  assert.equal(notices[0].tag, 'p');
  assert.equal(notices[0].attrs.role, 'status');
  assert.equal(notices[0].textContent, msg, 'keeps an untrusted message as literal text');
  assert.equal(notices[0].textWrites, 1);
  success.call(current.reject, { type: 'zib_ajax.success' }, { ...response, msg: 'updated message' });
  notices = current.form.children.filter(node => matches(node, '.zsr-notification-result'));
  assert.equal(notices.length, 1, 'repeated events do not accumulate messages');
  assert.equal(notices[0].textContent, 'updated message');
}

for (const response of [
  undefined,
  null,
  { error: true, reload: false, notifications: { email: { status: 'failed' } } },
  { error: false, reload: true, notifications: { email: { status: 'sent' } } },
  { error: false, notifications: { email: { status: 'failed' } } },
  { error: false, reload: false },
  { error: false, reload: false, notifications: null }
]) {
  const current = fixture();
  const before = snapshot(current.form);
  success.call(current.approve, { type: 'zib_ajax.success' }, response);
  assert.deepEqual(snapshot(current.form), before, 'unrelated responses have no side effects');
}

const current = fixture();
const unrelated = fixture();
append(current.form, element('input', { type: 'hidden', name: 'method', value: 'old' }));
append(current.form, element('input', { type: 'hidden', name: 'method', value: 'duplicate' }));
append(unrelated.form, element('input', { type: 'hidden', name: 'method', value: 'unchanged' }));
const unrelatedBefore = snapshot(unrelated.form);
for (const button of [current.approve, current.reject]) {
  mousedown.call(button);
  const methods = current.form.children.filter(node => node.attrs.name === 'method');
  assert.equal(methods.length, 1);
  assert.equal(methods[0].tag, 'input');
  assert.equal(methods[0].attrs.type, 'hidden');
  assert.equal(methods[0].attrs.value, button.attrs['data-review-method']);
  assert.deepEqual(snapshot(unrelated.form), unrelatedBefore);
}

console.log('frontend notification tests passed');
