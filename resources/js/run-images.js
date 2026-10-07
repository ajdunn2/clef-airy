export const MAX_IMAGE_BYTES = 24 * 1024 * 1024;

export function imageKind(bytes) {
    const values = bytes instanceof Uint8Array ? bytes : new Uint8Array(bytes);

    if (values.length >= 8 && values[0] === 0x89 && values[1] === 0x50 && values[2] === 0x4e && values[3] === 0x47) {
        return 'png';
    }

    if (values.length >= 3 && values[0] === 0xff && values[1] === 0xd8 && values[2] === 0xff) {
        return 'jpeg';
    }

    if (values.length >= 12
        && values[0] === 0x52 && values[1] === 0x49 && values[2] === 0x46 && values[3] === 0x46
        && values[8] === 0x57 && values[9] === 0x45 && values[10] === 0x42 && values[11] === 0x50) {
        return 'webp';
    }

    return null;
}

export function withinImageBudget(currentBytes, nextBytes) {
    return currentBytes + nextBytes <= MAX_IMAGE_BYTES;
}
