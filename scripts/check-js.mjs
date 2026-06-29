import { spawnSync } from 'node:child_process';
import { readFileSync, readdirSync, statSync } from 'node:fs';
import { resolve, relative } from 'node:path';

const root = process.cwd();
const jsRoot = resolve(root, 'resources/js');
const files = walk(jsRoot).filter((file) => file.endsWith('.js'));
let failed = false;

for (const file of files) {
    const label = relative(root, file);
    const check = spawnSync(process.execPath, ['--check', file], { encoding: 'utf8' });

    if (check.status !== 0) {
        failed = true;
        console.error(check.stderr.trim() || `${label}: syntax check failed`);
    }

    const source = readFileSync(file, 'utf8');
    if (/\bdebugger\b/.test(source)) {
        failed = true;
        console.error(`${label}: remove debugger statements before commit`);
    }
}

if (failed) {
    process.exit(1);
}

console.log(`Checked ${files.length} frontend scripts.`);

function walk(dir) {
    return readdirSync(dir).flatMap((name) => {
        const path = resolve(dir, name);
        return statSync(path).isDirectory() ? walk(path) : [path];
    });
}
