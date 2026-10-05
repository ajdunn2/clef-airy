const QUESTION_NAME_LIMIT = 100;
const COPY_SUFFIX = /^(.*) copy(?: (\d+))?$/;

export function nextDuplicateName(source, existingNames) {
    const taken = new Set(existingNames.map((name) => name.trim()).filter((name) => name !== ''));
    const base = source.trim();

    if (base === '') {
        return '';
    }

    const match = base.match(COPY_SUFFIX);
    const stemSource = match ? match[1] : base;
    let index = match ? Number(match[2] ?? 1) + 1 : 1;

    for (let attempt = 0; attempt < 100; attempt += 1) {
        const suffix = index === 1 ? ' copy' : ` copy ${index}`;
        const stem = stemSource.slice(0, Math.max(0, QUESTION_NAME_LIMIT - suffix.length)).trimEnd();
        const candidate = `${stem}${suffix}`;

        if (candidate.trim() !== '' && candidate.length <= QUESTION_NAME_LIMIT && ! taken.has(candidate)) {
            return candidate;
        }

        index += 1;
    }

    return base.slice(0, QUESTION_NAME_LIMIT);
}
