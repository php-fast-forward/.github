#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { isDeepStrictEqual } from 'node:util';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const cli = '@google/design.md@0.4.0';
const check = process.argv.includes('--check');
if (process.argv.slice(2).some(argument => argument !== '--check')) {
  console.error('Usage: node scripts/export-design.mjs [--check]');
  process.exit(2);
}
const env = { ...process.env, npm_config_cache: join(tmpdir(), 'php-fast-forward-design-cache') };
const run = (...args) => execFileSync('npx', ['-y', cli, ...args], {
  cwd: root, env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024,
});
const hash = text => createHash('sha256').update(text).digest('hex');

try {
  const lint = JSON.parse(run('lint', 'design.md'));
  if (lint.summary.errors || lint.summary.warnings) {
    throw new Error(`Design lint has findings: ${JSON.stringify(lint)}`);
  }
  const outputDirectory = join(root, 'assets', 'tokens');
  const outputs = [
    ['dtcg', 'tokens.json'],
    ['json-tailwind', 'tailwind.theme.json'],
    ['css-tailwind', 'theme.css'],
  ].map(([format, name]) => {
    const result = run('export', '--format', format, 'design.md');
    const value = name.endsWith('.json') ? `${JSON.stringify(JSON.parse(result), null, 2)}\n` : `${result.trim()}\n`;
    return { name, format, value };
  });
  const metadata = {
    schema_version: 1,
    source: 'design.md',
    source_sha256: hash(readFileSync(join(root, 'design.md'))),
    generator: cli,
    outputs: outputs.map(({ name, format, value }) => ({ path: name, format, sha256: hash(value) })),
  };
  if (check) {
    for (const { name, value } of outputs) {
      const actual = readFileSync(join(outputDirectory, name), 'utf8');
      const matches = name.endsWith('.json')
        ? isDeepStrictEqual(JSON.parse(actual), JSON.parse(value))
        : actual === value;
      if (!matches) throw new Error(`${name} is stale; run node scripts/export-design.mjs`);
    }
    const actualMetadata = JSON.parse(readFileSync(join(outputDirectory, 'exports.json'), 'utf8'));
    if (!isDeepStrictEqual(actualMetadata, metadata)) throw new Error('Export source or output hashes are stale');
    for (const { path, sha256 } of actualMetadata.outputs) {
      if (hash(readFileSync(join(outputDirectory, path))) !== sha256) throw new Error(`${path} hash does not match export metadata`);
    }
  } else {
    mkdirSync(outputDirectory, { recursive: true });
    for (const { name, value } of outputs) writeFileSync(join(outputDirectory, name), value);
    writeFileSync(join(outputDirectory, 'exports.json'), `${JSON.stringify(metadata, null, 2)}\n`);
  }
  console.log(JSON.stringify({ valid: true, mode: check ? 'check' : 'write', generator: cli, outputs: outputs.length }));
} catch (error) {
  console.error(error.message);
  process.exit(1);
}
