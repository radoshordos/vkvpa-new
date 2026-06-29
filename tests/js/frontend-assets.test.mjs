import assert from 'node:assert/strict';
import { existsSync, readFileSync, readdirSync, statSync } from 'node:fs';
import { resolve } from 'node:path';
import test from 'node:test';

const root = process.cwd();

test('Blade @vite entries point to existing source files', () => {
    const entries = viteEntriesFromBlade();

    assert.ok(entries.size > 0, 'Expected at least one @vite entry in Blade views.');

    for (const entry of entries) {
        assert.ok(existsSync(resolve(root, entry)), `${entry} is referenced by @vite but does not exist.`);
    }
});

test('Vite config keeps explicit JS entries aligned with source files', () => {
    const config = readFileSync(resolve(root, 'vite.config.js'), 'utf8');
    const entries = [...config.matchAll(/['"](resources\/(?:css|js)\/[^'"]+)['"]/g)].map((match) => match[1]);

    assert.ok(entries.includes('resources/js/vizualizace.js'));

    for (const entry of entries) {
        assert.ok(existsSync(resolve(root, entry)), `${entry} is listed in vite.config.js but does not exist.`);
    }
});

test('removed statistics incubator is not exposed as a frontend entry', () => {
    const routeFile = readFileSync(resolve(root, 'routes/web.php'), 'utf8');
    const viteConfig = readFileSync(resolve(root, 'vite.config.js'), 'utf8');

    assert.equal(routeFile.includes('statistiky-inkubator'), false);
    assert.equal(viteConfig.includes('statistiky-inkubator'), false);
    assert.equal(existsSync(resolve(root, 'resources/js/statistiky-inkubator.js')), false);
    assert.equal(existsSync(resolve(root, 'resources/views/pages/statistiky-inkubator.blade.php')), false);
});

function viteEntriesFromBlade() {
    const entries = new Set();
    const views = walk(resolve(root, 'resources/views')).filter((file) => file.endsWith('.blade.php'));

    for (const view of views) {
        const source = readFileSync(view, 'utf8');
        for (const call of source.matchAll(/@vite\(([^)]*)\)/g)) {
            for (const entry of call[1].matchAll(/['"]([^'"]+)['"]/g)) {
                entries.add(entry[1]);
            }
        }
    }

    return entries;
}

function walk(dir) {
    return readdirSync(dir).flatMap((name) => {
        const path = resolve(dir, name);
        return statSync(path).isDirectory() ? walk(path) : [path];
    });
}
