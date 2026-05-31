const fs = require('fs');
const path = require('path');
const vm = require('vm');

const root = path.resolve(__dirname, '..', '..');
const clientPath = path.join(root, 'assets/js/components/deepthink-client.js');
const source = fs.readFileSync(clientPath, 'utf8');
const agentSource = fs.readFileSync(path.join(root, 'assets/js/pages/agent.js'), 'utf8');
const sideSource = fs.readFileSync(path.join(root, 'assets/js/components/side-deepthink.js'), 'utf8');
const previewSource = fs.readFileSync(path.join(root, 'assets/js/pages/preview/preview-deepthink.js'), 'utf8');
const agentPhpSource = fs.readFileSync(path.join(root, 'agent.php'), 'utf8');
const context = { window: {} };
vm.createContext(context);
vm.runInContext(source, context, { filename: clientPath });

const client = context.window.TEKGDeepThinkClient || {};
if (typeof client.createStreamState !== 'function' || typeof client.reduceStreamEvent !== 'function') {
  throw new Error('Deep Think client must expose shared stream state reducer helpers.');
}

function reduce(state, event) {
  return client.reduceStreamEvent(state, event);
}

function assert(condition, message) {
  if (!condition) throw new Error(message);
}

function equal(actual, expected, message) {
  const actualJson = JSON.stringify(actual);
  const expectedJson = JSON.stringify(expected);
  if (actualJson !== expectedJson) {
    throw new Error(`${message}: expected ${expectedJson}, got ${actualJson}`);
  }
}

const initial = client.createStreamState();
equal(client.DEEPTHINK_STAGES, ['Understanding', 'Planning', 'Executing', 'Writing'], 'Deep Think stages must stay fixed');
assert(initial.progressVisible === false, 'Progress must start hidden.');

const afterAnalysis = reduce(initial, { type: 'analysis', message: 'Inspecting question' });
assert(afterAnalysis.progressVisible === false, 'Analysis must not reveal or advance progress.');
assert(afterAnalysis.stage === '', 'Analysis must not synthesize a stage.');

const afterTool = reduce(afterAnalysis, { type: 'tool_result', payload: {} });
assert(afterTool.progressVisible === false, 'Tool events must not reveal or advance progress.');
assert(afterTool.stage === '', 'Tool events must not synthesize a stage.');

const afterReflection = reduce(afterTool, { type: 'reflection', message: 'Reviewing evidence' });
assert(afterReflection.progressVisible === false, 'Reflection must not reveal or advance progress.');
assert(afterReflection.stage === '', 'Reflection must not synthesize a stage.');

const afterStage = reduce(afterReflection, {
  type: 'stage_state',
  payload: { current_stage: 'Planning' },
});
assert(afterStage.progressVisible === true, 'First real stage_state must reveal progress.');
assert(afterStage.stage === 'Planning', 'Real stage_state must set the visible stage.');

const afterError = reduce(afterStage, { type: 'error', message: 'Writing failed.' });
assert(afterError.failed === true, 'Error must record failure.');
assert(afterError.done === false, 'Error must continue waiting for done.');
assert(afterError.appendError === true, 'First error must be rendered.');

const afterDuplicateError = reduce(afterError, { type: 'error', message: 'Writing failed.' });
assert(afterDuplicateError.appendError === false, 'Duplicate error must not render twice.');

const afterIgnoredAnswer = reduce(afterDuplicateError, { type: 'answer', message: 'stale answer' });
assert(afterIgnoredAnswer.answer === '', 'Answer after failure must be ignored.');
assert(afterIgnoredAnswer.renderAnswer === false, 'Answer after failure must not render.');

const afterFailedDone = reduce(afterIgnoredAnswer, {
  type: 'done',
  payload: { answer: 'fallback answer', failed: true },
});
assert(afterFailedDone.done === true, 'Done must terminate the stream state.');
assert(afterFailedDone.failed === true, 'Done payload failure must remain failed.');
assert(afterFailedDone.answer === '', 'Failed done must not accept fallback answer.');
assert(afterFailedDone.renderAnswer === false, 'Failed done must not render fallback answer.');
assert(afterFailedDone.stopTimer === true, 'Timer must stop on done.');

const payloadFailedDone = reduce(client.createStreamState(), {
  type: 'done',
  payload: { answer: 'fallback answer', failed: true },
});
assert(payloadFailedDone.failed === true, 'payload.failed must mark done as failed.');
assert(payloadFailedDone.answer === '', 'payload.failed must suppress fallback answer.');

const writingFailedDone = reduce(client.createStreamState(), {
  type: 'done',
  payload: { answer: 'fallback answer', writing_failed: true },
});
assert(writingFailedDone.failed === true, 'payload.writing_failed must mark done as failed.');
assert(writingFailedDone.answer === '', 'payload.writing_failed must suppress fallback answer.');

const normalAnswer = reduce(client.createStreamState(), { type: 'answer', message: 'normal answer' });
assert(normalAnswer.answer === 'normal answer', 'Normal answer must be retained.');
assert(normalAnswer.renderAnswer === true, 'Normal answer must render.');

const normalDone = reduce(normalAnswer, { type: 'done', payload: {} });
assert(normalDone.done === true, 'Normal done must terminate the stream state.');
assert(normalDone.failed === false, 'Normal done must remain successful.');
assert(normalDone.answer === 'normal answer', 'Normal answer must survive done.');
assert(normalDone.stopTimer === true, 'Normal done must stop the timer.');

for (const [label, entrySource] of [
  ['agent page', agentSource],
  ['shared side panel', sideSource],
  ['preview panel', previewSource],
]) {
  assert(/reduceStreamEvent/.test(entrySource), `${label} must consume the shared reducer.`);
  assert(/createProgressMarkup/.test(entrySource), `${label} must render the shared four-stage progress markup.`);
  assert(/applyProgressState/.test(entrySource), `${label} must apply shared progress state.`);
}

for (const [label, entrySource] of [
  ['shared side panel', sideSource],
  ['preview panel', previewSource],
]) {
  const errorBranch = entrySource.match(/if \(event\.type === 'error'\) \{([\s\S]*?)\n    \}/);
  assert(errorBranch && !/stopTurnTimer/.test(errorBranch[1]), `${label} must keep the timer running after error until done.`);
}

assert(/js\/components\/deepthink-client\.js/.test(agentPhpSource), 'Agent page must load the shared Deep Think client before agent.js.');

console.log('Deep Think frontend state contract checks passed.');
