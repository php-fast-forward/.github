#!/usr/bin/env node
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { readFileSync, writeFileSync, mkdirSync } from 'node:fs';
import { tmpdir } from 'node:os';
import { dirname, join, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';
import { isDeepStrictEqual } from 'node:util';
import { normalizeExport, verifyBrowserAdapter } from './design-exports.mjs';

const root = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const cli = '@google/design.md@0.4.0';
const check = process.argv.includes('--check');
const tokensOnly = process.argv.includes('--tokens-only');
if ((check && tokensOnly) || process.argv.slice(2).some(argument => !['--check', '--tokens-only'].includes(argument))) {
  console.error('Usage: node scripts/export-design.mjs [--check | --tokens-only]');
  process.exit(2);
}
const env = { ...process.env, npm_config_cache: join(tmpdir(), 'php-fast-forward-design-cache') };
const run = (...args) => execFileSync('npx', ['-y', cli, ...args], {
  cwd: root, env, encoding: 'utf8', maxBuffer: 10 * 1024 * 1024,
});
const hash = text => createHash('sha256').update(text).digest('hex');

try {
  const lint = JSON.parse(run('lint', 'DESIGN.md'));
  if (lint.summary.errors || lint.summary.warnings) {
    throw new Error(`Design lint has findings: ${JSON.stringify(lint)}`);
  }
  const outputDirectory = join(root, 'assets', 'tokens');
  const outputs = [
    ['dtcg', 'tokens.json'],
    ['json-tailwind', 'tailwind.theme.json'],
    ['css-tailwind', 'theme.css'],
  ].map(([format, name]) => {
    const result = run('export', '--format', format, 'DESIGN.md');
    const value = normalizeExport(format, result);
    return { name, format, value };
  });
  if (!tokensOnly) verifyBrowserAdapter(outputs.find(output => output.name === 'theme.css').value,
    readFileSync(join(root, 'assets', 'styles', 'documentation.css'), 'utf8'));
  const metadata = {
    schema_version: 1,
    source: 'DESIGN.md',
    source_sha256: hash(readFileSync(join(root, 'DESIGN.md'))),
    generator: cli,
    normalization: 'scripts/design-exports.mjs',
    normalization_sha256: hash(readFileSync(join(root, 'scripts', 'design-exports.mjs'))),
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
  console.log(JSON.stringify({ valid: true, mode: check ? 'check' : tokensOnly ? 'tokens-only' : 'write', browser_adapter_checked: !tokensOnly, generator: cli, outputs: outputs.length }));
} catch (error) {
  console.error(error.message);
  process.exit(1);
}
