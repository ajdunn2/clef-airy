import { isLosslessNumber, parse, stringify } from 'lossless-json';

const QUESTION_NAME = 'Enter a name for each question.';
const OPTION_NAME = 'Enter a name for each option.';
const STATE_TOKEN = '__CLEF_AIRY_STATE__';

/**
 * @param {FormData} formData
 * @param {string} existingBody
 * @param {boolean} loadedAsJson
 * @returns {boolean}
 */
export function shouldKeepExistingJson(formData, existingBody, loadedAsJson) {
    const existing = String(existingBody ?? '');

    if (loadedAsJson && existing.trim() !== '') {
        return true;
    }

    if (existing.trim() === '') {
        return false;
    }

    const built = syncFormToJson(formData, '', false);

    if (built.action !== 'replace') {
        return true;
    }

    return ! samePayload(existing, built.json);
}

/**
 * @param {FormData} formData
 * @param {string} existingBody
 * @param {boolean} edited
 * @returns {{action: 'keep'|'replace', message: string, tone: 'muted'|'danger', json?: string}}
 */
export function syncFormToJson(formData, existingBody, edited) {
    const existing = String(existingBody ?? '');

    if (edited && existing.trim() !== '') {
        return keep('JSON kept. Edits here are not copied to the form. Clear this box and switch to JSON again to fill it from the form.');
    }

    const shape = inspectExisting(existing);

    if (shape === 'invalid') {
        return keep('JSON left unchanged because it is not valid JSON.', 'danger');
    }

    if (shape === 'other') {
        return keep('JSON kept because it has fields the form cannot store. Clear this box and switch to JSON again to fill it from the form.');
    }

    const built = build(formData);

    if (built.error) {
        const message = existing.trim() === '' ? built.error : `JSON left unchanged. ${built.error}`;

        return keep(message, 'danger');
    }

    return {
        action: 'replace',
        json: built.json,
        message: 'JSON filled from the form.',
        tone: 'muted',
    };
}

/**
 * @param {string} message
 * @param {'muted'|'danger'} tone
 */
function keep(message, tone = 'muted') {
    return { action: 'keep', message, tone };
}

/**
 * @param {string} existing
 * @returns {'empty'|'form'|'invalid'|'other'}
 */
function inspectExisting(existing) {
    const text = existing.trim();

    if (text === '') {
        return 'empty';
    }

    if (text.includes('"__proto__"')) {
        return 'other';
    }

    let parsed;

    try {
        parsed = parse(text);
    } catch {
        return 'invalid';
    }

    if (parsed === null || typeof parsed !== 'object' || Array.isArray(parsed)) {
        return 'other';
    }

    for (const key of Object.keys(parsed)) {
        if (key !== 'state' && key !== 'questions') {
            return 'other';
        }
    }

    if (parsed.questions === undefined) {
        return 'form';
    }

    const questions = parsed.questions;

    if (questions === null || typeof questions !== 'object' || Array.isArray(questions)) {
        return 'other';
    }

    for (const question of Object.values(questions)) {
        if (question === null || typeof question !== 'object' || Array.isArray(question)) {
            return 'other';
        }

        for (const key of Object.keys(question)) {
            if (key !== 'type' && key !== 'instructions' && key !== 'criteria') {
                return 'other';
            }
        }
    }

    return 'form';
}

/**
 * @param {FormData} formData
 * @returns {{json: string}|{error: string}}
 */
function build(formData) {
    const questions = new Map();

    const entry = (index) => {
        if (! questions.has(index)) {
            questions.set(index, {
                name: '',
                type: '',
                instructions: '',
                true: '',
                false: '',
                options: new Map(),
                levels: new Map(),
            });
        }

        return questions.get(index);
    };

    for (const [field, raw] of formData.entries()) {
        if (typeof raw !== 'string') {
            continue;
        }

        const value = raw.trim();
        let match;

        if ((match = field.match(/^questions\[(\d+)\]\[(name|type|instructions|true|false)\]$/)) !== null) {
            entry(match[1])[match[2]] = value;
        } else if ((match = field.match(/^questions\[(\d+)\]\[options\]\[(\d+)\]\[(name|description)\]$/)) !== null) {
            const options = entry(match[1]).options;

            if (! options.has(match[2])) {
                options.set(match[2], { name: '', description: '' });
            }

            options.get(match[2])[match[3]] = value;
        } else if ((match = field.match(/^questions\[(\d+)\]\[levels\]\[(\d+)\]$/)) !== null) {
            entry(match[1]).levels.set(match[2], value);
        }
    }

    const built = Object.create(null);
    const seen = new Set();

    for (const question of questions.values()) {
        if (isBlank(question)) {
            continue;
        }

        const nameError = invalidName(question.name, QUESTION_NAME);

        if (nameError) {
            return { error: nameError };
        }

        if (seen.has(question.name)) {
            return { error: 'Question names must be unique.' };
        }

        seen.add(question.name);

        if (! ['noul', 'choice', 'score'].includes(question.type)) {
            return { error: 'Choose a question type.' };
        }

        if (question.instructions === '') {
            return { error: 'Enter instructions for each question.' };
        }

        const criteria = criteriaFor(question);

        if (criteria.error) {
            return { error: criteria.error };
        }

        const payload = Object.create(null);
        payload.type = question.type;
        payload.instructions = contentValue(question.instructions);

        if (criteria.value !== null) {
            payload.criteria = criteria.value;
        }

        built[question.name] = payload;
    }

    if (Object.keys(built).length === 0) {
        return { error: 'Add a question.' };
    }

    const stateField = formData.get('state');
    const state = typeof stateField === 'string' ? stateField : '';

    return { json: documentJson(state, built) };
}

/**
 * @param {{name: string, instructions: string, true: string, false: string, options: Map<string, {name: string, description: string}>, levels: Map<string, string>}} question
 */
function isBlank(question) {
    if (question.name !== '' || question.instructions !== '' || question.true !== '' || question.false !== '') {
        return false;
    }

    for (const option of question.options.values()) {
        if (option.name !== '' || option.description !== '') {
            return false;
        }
    }

    for (const level of question.levels.values()) {
        if (level !== '') {
            return false;
        }
    }

    return true;
}

/**
 * @returns {{value: object|Array<string>|null}|{error: string}}
 */
function criteriaFor(question) {
    if (question.type === 'noul') {
        if (question.true === '' && question.false === '') {
            return { value: null };
        }

        const criteria = Object.create(null);
        if (question.true !== '') {
            criteria.true = contentValue(question.true);
        }
        if (question.false !== '') {
            criteria.false = contentValue(question.false);
        }

        return { value: criteria };
    }

    if (question.type === 'choice') {
        const criteria = Object.create(null);
        const names = new Set();
        let count = 0;

        for (const option of question.options.values()) {
            if (option.name === '' && option.description === '') {
                continue;
            }

            if (option.name === '') {
                return { error: 'Each choice option needs a name.' };
            }

            const nameError = invalidName(option.name, OPTION_NAME);

            if (nameError) {
                return { error: nameError };
            }

            if (names.has(option.name)) {
                return { error: 'Option names must be unique.' };
            }

            names.add(option.name);
            criteria[option.name] = option.description === '' ? null : contentValue(option.description);
            count += 1;
        }

        if (count < 2) {
            return { error: 'Choice questions need at least two options.' };
        }

        return { value: criteria };
    }

    const levels = [];

    for (const level of question.levels.values()) {
        if (level !== '') {
            levels.push(contentValue(level));
        }
    }

    if (levels.length < 2) {
        return { error: 'Score questions need at least two levels, lowest first.' };
    }

    return { value: levels };
}

function invalidName(name, message) {
    if (name.trim() === '') {
        return message;
    }

    return null;
}

function documentJson(state, questions) {
    const shell = Object.create(null);
    shell.state = STATE_TOKEN;
    shell.questions = questions;

    return stringify(shell, null, 2).replace(JSON.stringify(STATE_TOKEN), encodeState(state));
}

export function contentValue(source) {
    try {
        const value = parse(source);

        if (value === null || (typeof value === 'object' && ! isLosslessNumber(value))) {
            return value;
        }
    } catch {}

    return source;
}

export function formFieldValue(value) {
    return typeof value === 'string' ? value : stringify(value, null, 2);
}

export function parseFormBody(source) {
    return parse(source);
}

export function formBodyError(body) {
    const object = (value) => value !== null && typeof value === 'object' && ! Array.isArray(value) && ! isLosslessNumber(value);
    const content = (value) => value === null || typeof value === 'string' || Array.isArray(value) || object(value);
    const message = 'This JSON contains fields the form cannot preserve. Keep using the JSON view.';

    if (! object(body) || ! object(body.questions)) {
        return 'Add a questions object before switching to the form.';
    }

    if (Object.keys(body).some((key) => ! ['state', 'questions', 'model'].includes(key))
        || (Object.hasOwn(body, 'state') && (body.state === null || ! content(body.state)))) {
        return message;
    }

    for (const question of Object.values(body.questions)) {
        if (! object(question) || ! ['noul', 'choice', 'score'].includes(question.type)
            || Object.keys(question).some((key) => ! ['type', 'instructions', 'criteria'].includes(key))
            || ! content(question.instructions)) {
            return message;
        }

        if (! Object.hasOwn(question, 'criteria')) {
            continue;
        }

        if (question.type === 'score') {
            if (! Array.isArray(question.criteria) || ! question.criteria.every(content)) {
                return message;
            }
        } else if (! object(question.criteria) || ! Object.values(question.criteria).every(content)
            || (question.type === 'noul' && Object.keys(question.criteria).some((key) => ! ['true', 'false'].includes(key)))) {
            return message;
        }
    }

    return null;
}

export function withSelectedModel(source, model) {
    try {
        const body = parse(source);

        if (body !== null && typeof body === 'object' && ! Array.isArray(body) && ! isLosslessNumber(body)) {
            return stringify(Object.assign(Object.create(null), body, { model }), null, 2);
        }
    } catch {}

    return source;
}

function encodeState(state) {
    const trimmed = state.trim();

    if ((trimmed.startsWith('{') || trimmed.startsWith('[')) && parses(trimmed)) {
        return trimmed;
    }

    return JSON.stringify(state);
}

export function formatJson(text) {
    if (text.includes('"__proto__"')) {
        return { ok: false, message: 'JSON left unchanged so formatting would not drop a value.' };
    }

    try {
        return { ok: true, json: stringify(parse(text), null, 2) };
    } catch (error) {
        const duplicate = error instanceof SyntaxError && error.message.startsWith('Duplicate key');

        return {
            ok: false,
            message: duplicate
                ? 'JSON left unchanged because it repeats a name.'
                : 'Fix the JSON syntax before formatting.',
        };
    }
}

function parses(value) {
    try {
        JSON.parse(value);

        return true;
    } catch {
        return false;
    }
}

function samePayload(left, right) {
    try {
        return JSON.stringify(normalize(parse(left))) === JSON.stringify(normalize(parse(right)));
    } catch {
        return false;
    }
}

function normalize(value) {
    if (isLosslessNumber(value)) {
        return value.value;
    }

    if (Array.isArray(value)) {
        return value.map(normalize);
    }

    if (value !== null && typeof value === 'object') {
        const sorted = {};

        for (const key of Object.keys(value).sort()) {
            sorted[key] = normalize(value[key]);
        }

        return sorted;
    }

    return value;
}
