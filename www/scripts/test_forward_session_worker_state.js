'use strict';

const test = require('node:test');
const assert = require('node:assert/strict');

const {
  ForwardSessionWorker,
  ForwardWorkerBindingRegistry,
  validateJob,
} = require('./forward_session_worker');

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

test('worker serializes concurrent jobs through an internal queue', async () => {
  const worker = new ForwardSessionWorker({ worker_id: 'fw_worker_queue', autostart: false });
  const page = await seedStartedWorker(worker);
  const originalCookies = page.cookies.bind(page);
  const executionLog = [];
  page.cookies = async () => {
    executionLog.push(`cookies:${worker.activeJobId}`);
    await new Promise((resolve) => setTimeout(resolve, 40));
    return originalCookies();
  };

  const firstJob = worker.handleJob({
    job_id: 'job_first',
    actor_id: 'user_42_forward_x',
    operation_type: 'noop',
    operation_profile: 'continue_same_container',
    container_id: 'CNT-010',
    payload: {},
  });
  const secondJob = worker.handleJob({
    job_id: 'job_second',
    actor_id: 'user_42_forward_x',
    operation_type: 'noop',
    operation_profile: 'continue_same_container',
    container_id: 'CNT-011',
    payload: {},
  });

  await new Promise((resolve) => setTimeout(resolve, 5));
  const midQueueState = worker.getQueueState();
  assert.equal(midQueueState.is_running_job, true);
  assert.equal(midQueueState.active_job_id, 'job_first');
  assert.equal(midQueueState.pending_jobs, 1);

  const [firstResult, secondResult] = await Promise.all([firstJob, secondJob]);
  assert.equal(firstResult.job.job_id, 'job_first');
  assert.equal(secondResult.job.job_id, 'job_second');
  assert.deepEqual(
    executionLog,
    ['cookies:job_first', 'cookies:job_second']
  );

  const finalQueueState = worker.getQueueState();
  assert.equal(finalQueueState.is_running_job, false);
  assert.equal(finalQueueState.active_job_id, '');
  assert.equal(finalQueueState.pending_jobs, 0);

  const status = await worker.getStatus();
  assert.equal(status.queue_state.pending_jobs, 0);
  assert.equal(status.queue_state.is_running_job, false);
  assert.equal(status.last_job.job_id, 'job_second');
  assert.equal(status.current_container_id, 'CNT-011');
});




test('worker stops itself after idle timeout and clears sticky session state', async () => {
  const worker = new ForwardSessionWorker({
    worker_id: 'fw_worker_idle',
    autostart: false,
    idle_timeout_ms: 30,
  });
  await seedStartedWorker(worker);

  await worker.handleJob({
    actor_id: 'user_idle_forward',
    operation_type: 'noop',
    operation_profile: 'continue_same_container',
    container_id: 'CNT-IDLE',
    payload: {},
  });

  await new Promise((resolve) => setTimeout(resolve, 80));

  const status = await worker.getStatus();
  assert.equal(worker.isStarted(), false);
  assert.equal(status.status, 'stopped');
  assert.equal(status.stop_reason, 'idle_timeout');
  assert.equal(status.actor_id, '');
  assert.equal(status.current_container_id, '');
  assert.equal(status.operation_profile, '');
  assert.equal(status.last_job, null);
  assert.equal(status.context_state.session_status, 'stopped');
  assert.equal(status.context_state.actor_id, '');
  assert.equal(status.context_state.current_container_id, '');
  assert.equal(status.context_state.expected_next_action, 'start_session');
});

test('binding registry creates sticky per-actor workers, reuses leases, and releases by TTL', () => {
  let workerSequence = 0;
  const registry = new ForwardWorkerBindingRegistry({
    lease_timeout_ms: 1000,
    worker_factory: ({ actor_id }) => ({ worker_id: `fw_worker_${++workerSequence}`, actor_id }),
  });

  const firstLease = registry.acquire('user_42_forward_x', { operation_profile: 'continue_same_container' });
  assert.equal(firstLease.action, 'create_worker');
  assert.equal(firstLease.binding.worker_id, 'fw_worker_1');
  assert.equal(firstLease.binding.actor_id, 'user_42_forward_x');
  assert.equal(firstLease.binding.sticky, true);
  assert.equal(firstLease.binding.metadata.operation_profile, 'continue_same_container');

  const reusedLease = registry.acquire('user_42_forward_x', { current_container_id: 'CNT-001' });
  assert.equal(reusedLease.action, 'reuse_worker');
  assert.equal(reusedLease.binding.worker_id, 'fw_worker_1');
  assert.equal(reusedLease.binding.metadata.operation_profile, 'continue_same_container');
  assert.equal(reusedLease.binding.metadata.current_container_id, 'CNT-001');

  const secondActorLease = registry.acquire('user_99_forward_y');
  assert.equal(secondActorLease.action, 'create_worker');
  assert.equal(secondActorLease.binding.worker_id, 'fw_worker_2');

  const releasedByTtl = registry.releaseExpired('9999-01-01T00:00:00.000Z');
  assert.equal(releasedByTtl.length, 2);
  assert.deepEqual(
    releasedByTtl.map((binding) => [binding.actor_id, binding.release_reason]),
    [
      ['user_42_forward_x', 'lease_timeout'],
      ['user_99_forward_y', 'lease_timeout'],
    ]
  );
  assert.equal(registry.getBindingByActor('user_42_forward_x'), null);
  assert.equal(registry.listBindings().length, 0);
});

test('binding registry can release a worker explicitly before TTL', () => {
  const registry = new ForwardWorkerBindingRegistry({
    lease_timeout_ms: 60_000,
    worker_factory: () => ({ worker_id: 'fw_worker_manual' }),
  });

  const lease = registry.acquire('user_manual_forward');
  assert.equal(lease.binding.worker_id, 'fw_worker_manual');

  const released = registry.release('fw_worker_manual', 'release_worker');
  assert.equal(released.worker_id, 'fw_worker_manual');
  assert.equal(released.release_reason, 'release_worker');
  assert.equal(registry.getBindingByWorker('fw_worker_manual'), null);
});
