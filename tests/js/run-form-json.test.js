import assert from 'node:assert/strict';
import { test } from 'node:test';
import { formatJson, shouldKeepExistingJson, syncFormToJson } from '../../resources/js/run-form-json.js';

function form(entries) {
    const data = new FormData();

    for (const [name, value] of entries) {
        data.append(name, value);
    }

    return data;
}

function decisionForm() {
    return form([
        ['state', 'Checkout is down.'],
        ['questions[0][name]', 'urgent'],
        ['questions[0][type]', 'noul'],
        ['questions[0][instructions]', 'Is this urgent?'],
        ['questions[0][true]', ''],
        ['questions[0][false]', ''],
        ['questions[0][options][0][name]', ''],
        ['questions[0][options][0][description]', ''],
        ['questions[0][levels][0]', ''],
        ['questions[1][name]', 'team'],
        ['questions[1][type]', 'choice'],
        ['questions[1][instructions]', 'Which team?'],
        ['questions[1][true]', 'hidden yes'],
        ['questions[1][false]', 'hidden no'],
        ['questions[1][options][0][name]', 'billing'],
        ['questions[1][options][0][description]', ''],
        ['questions[1][options][1][name]', 'technical'],
        ['questions[1][options][1][description]', 'Outages'],
        ['questions[1][levels][0]', 'should stay on the form'],
        ['questions[2][name]', 'severity'],
        ['questions[2][type]', 'score'],
        ['questions[2][instructions]', 'How severe?'],
        ['questions[2][levels][0]', 'Minor'],
        ['questions[2][levels][1]', ''],
        ['questions[2][levels][2]', 'Critical'],
        ['questions[3][name]', ''],
        ['questions[3][type]', 'noul'],
        ['questions[3][instructions]', ''],
    ]);
}

test('switching to JSON fills a payload from the form and leaves other criteria out', () => {
    const result = syncFormToJson(decisionForm(), '', false);

    assert.equal(result.action, 'replace');
    assert.equal(result.json, `{
  "state": "Checkout is down.",
  "questions": {
    "urgent": {
      "type": "noul",
      "instructions": "Is this urgent?"
    },
    "team": {
      "type": "choice",
      "instructions": "Which team?",
      "criteria": {
        "billing": null,
        "technical": "Outages"
      }
    },
    "severity": {
      "type": "score",
      "instructions": "How severe?",
      "criteria": [
        "Minor",
        "Critical"
      ]
    }
  }
}`);
    assert.equal(result.json.includes('hidden yes'), false);
    assert.equal(result.json.includes('should stay on the form'), false);
});

test('object state is copied as typed, including big integers and key order', () => {
    const state = '{"b":1,"2":"x","a":2,"n":9007199254740993,"huge":1e309}';
    const result = syncFormToJson(form([
        ['state', `  ${state}  `],
        ['questions[0][name]', 'urgent'],
        ['questions[0][type]', 'noul'],
        ['questions[0][instructions]', 'Is this urgent?'],
    ]), '', false);

    assert.equal(result.action, 'replace');
    assert.equal(result.json.includes(`"state": ${state}`), true);
    assert.equal(JSON.parse(result.json).questions.urgent.instructions, 'Is this urgent?');
});

test('empty object state and non-object JSON state stay as text', () => {
    const emptyObject = syncFormToJson(form([
        ['state', '{}'],
        ['questions[0][name]', 'urgent'],
        ['questions[0][type]', 'noul'],
        ['questions[0][instructions]', 'Is this urgent?'],
    ]), '', false);
    const scalar = syncFormToJson(form([
        ['state', '1'],
        ['questions[0][name]', 'urgent'],
        ['questions[0][type]', 'noul'],
        ['questions[0][instructions]', 'Is this urgent?'],
    ]), '', false);

    assert.equal(emptyObject.json.includes('"state": {}'), true);
    assert.equal(JSON.parse(scalar.json).state, '1');
});

test('yes and no criteria are included when either side is filled', () => {
    const result = syncFormToJson(form([
        ['state', 'Checkout is down.'],
        ['questions[0][name]', 'urgent'],
        ['questions[0][type]', 'noul'],
        ['questions[0][instructions]', 'Is this urgent?'],
        ['questions[0][true]', 'Customers cannot pay'],
        ['questions[0][false]', ''],
    ]), '', false);

    assert.equal(result.action, 'replace');
    assert.deepEqual(JSON.parse(result.json).questions.urgent.criteria, {
        true: 'Customers cannot pay',
        false: '',
    });
});

test('JSON already on the page is kept only when it differs from the form', () => {
    const data = form([
        ['state', 'Checkout is down.'],
        ['questions[0][name]', 'urgent'],
        ['questions[0][type]', 'noul'],
        ['questions[0][instructions]', 'Is this urgent?'],
    ]);
    const filled = syncFormToJson(data, '', false);
    const reformatted = JSON.stringify(JSON.parse(filled.json));
    const customized = filled.json.replace('Is this urgent?', 'Keep this edit');

    assert.equal(shouldKeepExistingJson(data, filled.json, false), false);
    assert.equal(shouldKeepExistingJson(data, reformatted, false), false);
    assert.equal(shouldKeepExistingJson(data, customized, false), true);
    assert.equal(shouldKeepExistingJson(data, filled.json, true), true);
    assert.equal(shouldKeepExistingJson(data, '', false), false);
    assert.equal(syncFormToJson(data, filled.json, false).action, 'replace');
    assert.equal(syncFormToJson(data, customized, true).action, 'keep');
});

test('a score question with one level does not write an empty criteria list', () => {
    const result = syncFormToJson(form([
        ['state', 'Checkout is down.'],
        ['questions[0][name]', 'severity'],
        ['questions[0][type]', 'score'],
        ['questions[0][instructions]', 'How severe?'],
        ['questions[0][levels][0]', 'Minor'],
        ['questions[0][levels][1]', ''],
    ]), '{"state":"keep me"}', false);

    assert.equal(result.action, 'keep');
    assert.equal(result.message, 'JSON left unchanged. Score questions need at least two levels, lowest first.');
});

test('edited JSON is kept whole', () => {
    const existing = '{"state":"mine","questions":{"urgent":{"type":"noul","instructions":"Keep this","extra":true}},"note":"also keep"}';
    const result = syncFormToJson(decisionForm(), existing, true);

    assert.equal(result.action, 'keep');
    assert.equal(result.json, undefined);
    assert.match(result.message, /not copied to the form/);
});

test('invalid JSON is kept', () => {
    const result = syncFormToJson(decisionForm(), '{"state":', false);

    assert.equal(result.action, 'keep');
    assert.equal(result.tone, 'danger');
    assert.match(result.message, /not valid JSON/);
});

test('JSON with fields outside the form is kept', () => {
    const extraTop = syncFormToJson(decisionForm(), '{"state":"x","questions":{},"note":"keep"}', false);
    const extraQuestion = syncFormToJson(decisionForm(), '{"questions":{"urgent":{"type":"noul","instructions":"Go","weight":1}}}', false);
    const list = syncFormToJson(decisionForm(), '{"questions":[{"type":"noul"}]}', false);

    assert.equal(extraTop.action, 'keep');
    assert.equal(extraQuestion.action, 'keep');
    assert.equal(list.action, 'keep');
    assert.match(extraTop.message, /cannot store/);
});

test('a __proto__ question name is written into the JSON', () => {
    const result = syncFormToJson(form([
        ['state', 'Checkout is down.'],
        ['questions[0][name]', '__proto__'],
        ['questions[0][type]', 'noul'],
        ['questions[0][instructions]', 'Is this urgent?'],
    ]), '', false);

    assert.equal(result.action, 'replace');
    assert.equal(result.json.includes('"__proto__": {'), true);
});

test('existing JSON that contains __proto__ is kept', () => {
    const result = syncFormToJson(decisionForm(), '{"__proto__":{"admin":true},"state":"x"}', false);

    assert.equal(result.action, 'keep');
});

test('an incomplete form does not replace JSON that is already there', () => {
    const duplicate = syncFormToJson(form([
        ['state', 'Checkout is down.'],
        ['questions[0][name]', 'urgent'],
        ['questions[0][type]', 'noul'],
        ['questions[0][instructions]', 'First'],
        ['questions[1][name]', 'urgent'],
        ['questions[1][type]', 'noul'],
        ['questions[1][instructions]', 'Second'],
    ]), '{"state":"keep me","questions":{}}', false);
    const oneOption = syncFormToJson(form([
        ['state', 'Checkout is down.'],
        ['questions[0][name]', 'team'],
        ['questions[0][type]', 'choice'],
        ['questions[0][instructions]', 'Which team?'],
        ['questions[0][options][0][name]', 'billing'],
        ['questions[0][options][0][description]', 'Payments'],
    ]), '', false);
    const duplicateOption = syncFormToJson(form([
        ['state', 'Checkout is down.'],
        ['questions[0][name]', 'team'],
        ['questions[0][type]', 'choice'],
        ['questions[0][instructions]', 'Which team?'],
        ['questions[0][options][0][name]', 'billing'],
        ['questions[0][options][1][name]', 'billing'],
    ]), '', false);
    const unnamed = syncFormToJson(form([
        ['state', 'Checkout is down.'],
        ['questions[0][name]', ''],
        ['questions[0][type]', 'noul'],
        ['questions[0][instructions]', 'Still here'],
    ]), '', false);
    const empty = syncFormToJson(form([
        ['state', 'Checkout is down.'],
        ['questions[0][name]', ''],
        ['questions[0][type]', 'noul'],
    ]), '', false);

    assert.equal(duplicate.action, 'keep');
    assert.match(duplicate.message, /JSON left unchanged\. Question names must be unique\./);
    assert.equal(oneOption.action, 'keep');
    assert.equal(oneOption.message, 'Choice questions need at least two options.');
    assert.equal(duplicateOption.message, 'Option names must be unique.');
    assert.equal(unnamed.message, QUESTION_NAME);
    assert.equal(empty.message, 'Add a question.');
});

test('formatting keeps numbers that the browser cannot store exactly', () => {
    const result = formatJson('{"n":9007199254740993,"huge":1e309,"sci":1e2}');

    assert.equal(result.ok, true);
    assert.equal(result.json.includes('9007199254740993'), true);
    assert.equal(result.json.includes('1e309'), true);
    assert.equal(result.json.includes('1e2'), true);
});

test('formatting leaves JSON unchanged when a value would be dropped', () => {
    const proto = formatJson('{"__proto__":{"admin":true}}');
    const duplicate = formatJson('{"a":1,"a":2}');
    const broken = formatJson('{"a":');

    assert.equal(proto.ok, false);
    assert.match(proto.message, /would not drop a value/);
    assert.equal(duplicate.ok, false);
    assert.match(duplicate.message, /repeats a name/);
    assert.equal(broken.ok, false);
    assert.equal(broken.message, 'Fix the JSON syntax before formatting.');
});

test('a large integer that differs from the form is kept', () => {
    const state = '{"n":9007199254740993}';
    const data = form([
        ['state', state],
        ['questions[0][name]', 'urgent'],
        ['questions[0][type]', 'noul'],
        ['questions[0][instructions]', 'Is this urgent?'],
    ]);
    const filled = syncFormToJson(data, '', false);

    assert.equal(shouldKeepExistingJson(data, filled.json, false), false);
    assert.equal(shouldKeepExistingJson(data, filled.json.replace('9007199254740993', '9007199254740992'), false), true);
});

const QUESTION_NAME = 'Use a letter in each question name. Names may also include numbers and underscores.';
