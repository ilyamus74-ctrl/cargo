use strict';

const test = require('node:test');
const assert = require('node:assert/strict');
const { ForwardSessionWorker, validateJob } = require('./forward_session_worker');

function makeFakePage(url = 'about:blank') {
  let currentUrl = url;
  return {
    url() {
      return currentUrl;
    },
    title() {
      return Promise.resolve('Fake page');
    },
    cookies() {
      return Promise.resolve([{ name: 'sid', value: '123' }]);
    },
    close() {
      return Promise.resolve();
    },
    setUrl(nextUrl) {
      currentUrl = nextUrl;
    },
  };
}

async function seedStartedWorker(worker, url = 'https://example.test/') {
  const page = makeFakePage(url);
  worker.browser = {
    close() {
      return Promise.resolve();
    },
  };
  worker.page = page;
  worker.startedAt = '2026-03-23T00:00:00.000Z';
  worker.touch({ session_status: 'ready', expected_next_action: 'accept_job' });
  return page;
}

test('validateJob keeps item 4 contract for required JSON API fields', () => {
  const job = validateJob({
    actor_id: 'user_42_forward_x',
    operation_type: 'add_parcel_to_forward_container',
    operation_profile: 'continue_same_container',
    container_id: 'CNT-001',
    payload: { tracking: '123456789' },
  });

  assert.equal(job.actor_id, 'user_42_forward_x');
  assert.equal(job.operation_type, 'add_parcel_to_forward_container');
  assert.equal(job.operation_profile, 'continue_same_container');
  assert.equal(job.container_id, 'CNT-001');
  assert.deepEqual(job.payload, { tracking: '123456789' });
  assert.match(job.job_id, /^job_/);
});

test('context state tracks actor/container/profile and next action for forward jobs', async () => {
  const worker = new ForwardSessionWorker({ worker_id: 'fw_worker_01', autostart: false });
  await seedStartedWorker(worker);

  const result = await worker.handleJob({
    actor_id: 'user_42_forward_x',
    operation_type: 'add_parcel_to_forward_container',
    operation_profile: 'continue_same_container',
    container_id: 'CNT-001',
    payload: { tracking: '123456789', awaiting_popup: true, awaiting_label: true },
  });

  assert.equal(result.ok, false);
  assert.equal(result.action, 'accepted_but_not_implemented');
  assert.equal(result.state.context_state.worker_id, 'fw_worker_01');
  assert.equal(result.state.context_state.actor_id, 'user_42_forward_x');
  assert.equal(result.state.context_state.current_container_id, 'CNT-001');
  assert.equal(result.state.context_state.operation_profile, 'continue_same_container');
  assert.equal(result.state.context_state.session_status, 'ready');
  assert.equal(result.state.context_state.expected_next_action, 'fill_tracking');
  assert.equal(result.state.context_state.awaiting_popup, true);
  assert.equal(result.state.context_state.awaiting_label, true);

  const status = await worker.getStatus();
  assert.equal(status.context_state.expected_next_action, 'fill_tracking');
  assert.equal(status.context_state.status, 'idle');
  assert.equal(status.cookies, 'sid=123');
});

test('context_state remains available after stop and transitions to stopped state', async () => {
  const worker = new ForwardSessionWorker({ worker_id: 'fw_worker_02', autostart: false });
  await seedStartedWorker(worker);

  await worker.handleJob({
    actor_id: 'user_42_forward_x',
    operation_type: 'noop',
    operation_profile: 'continue_same_container',
    container_id: 'CNT-002',
    payload: {},
  });

  const stopped = await worker.stop('shutdown');
  assert.equal(stopped.status, 'stopped');

  const contextState = worker.getContextState();
  assert.equal(contextState.worker_id, 'fw_worker_02');
  assert.equal(contextState.actor_id, 'user_42_forward_x');
  assert.equal(contextState.current_container_id, 'CNT-002');
  assert.equal(contextState.session_status, 'stopped');
  assert.equal(contextState.expected_next_action, 'stopped');
});
