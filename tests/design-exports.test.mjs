import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import { fontFamilies, normalizeExport, verifyBrowserAdapter } from '../scripts/design-exports.mjs';

test('font fallbacks retain order, spaces, and commas inside family names', () => {
  assert.deepEqual(fontFamilies('system-ui, "Family, One", \'Segoe UI\', sans-serif'),
    ['system-ui', 'Family, One', 'Segoe UI', 'sans-serif']);
  assert.throws(() => fontFamilies('system-ui,,sans-serif'));
  assert.throws(() => fontFamilies('"Unfinished'));
});

test('DTCG exports include complete typography and only supported dimension units', () => {
  const data = JSON.parse(readFileSync(new URL('../assets/tokens/tokens.json', import.meta.url)));
  for (const token of Object.values(data.typography)) {
    assert.equal(token.$type, 'typography');
    const value = token.$value;
    assert.deepEqual(Object.keys(value).sort(), ['fontFamily', 'fontSize', 'fontWeight', 'letterSpacing', 'lineHeight'].sort());
    assert.ok(Array.isArray(value.fontFamily));
    assert.ok(value.fontFamily.length > 1);
    assert.ok(value.fontFamily.every(name => !name.includes(',')));
    assert.ok(Number.isFinite(value.lineHeight) && value.lineHeight > 0);
    for (const key of ['fontSize', 'letterSpacing']) assert.ok(['px', 'rem'].includes(value[key].unit));
  }
  assert.equal(data.typography['body-md'].$value.lineHeight, 1.6);
  // -0.03em at a 3rem font size is -0.09rem, not -0.03rem.
  assert.deepEqual(data.typography.h1.$value.letterSpacing, { value: -0.09, unit: 'rem' });
});

test('Tailwind v3 preserves the fallback list and body line height', () => {
  const {theme: {extend}} = JSON.parse(readFileSync(new URL('../assets/tokens/tailwind.theme.json', import.meta.url)));
  assert.deepEqual(extend.fontFamily['body-md'], ['system-ui', '-apple-system', 'BlinkMacSystemFont', 'Segoe UI', 'sans-serif']);
  assert.equal(extend.fontSize['body-md'][1].lineHeight, '1.6');
});

test('Tailwind v4 uses separate font names and associated text line height', () => {
  const css = readFileSync(new URL('../assets/tokens/theme.css', import.meta.url), 'utf8');
  assert.match(css, /--font-body-md: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;/);
  assert.match(css, /--text-body-md--line-height: 1\.6;/);
  assert.match(css, /--text-h1--letter-spacing: -0\.03em;/);
});

test('missing unitless line height fails before committing an incomplete export', () => {
  const raw = JSON.stringify({typography: {body: {$value: {fontFamily:'sans-serif', fontSize:{value:1,unit:'rem'}, fontWeight:400, letterSpacing:{value:0,unit:'px'}}}}});
  assert.throws(() => normalizeExport('dtcg', raw), /missing lineHeight/);
});

test('browser adapter rejects quoted whole stacks and quoted numeric values', () => {
  const theme = '@theme { --font-body-md: system-ui, "Segoe UI", sans-serif; --leading-body-md: 1.6; }';
  assert.doesNotThrow(() => verifyBrowserAdapter(theme, ':root { --font-body-md: system-ui, Segoe UI, sans-serif; --leading-body-md: 1.6; }'));
  assert.throws(() => verifyBrowserAdapter(theme, theme.replace('system-ui, "Segoe UI", sans-serif', '"system-ui, Segoe UI, sans-serif"')), /stale/);
  assert.throws(() => verifyBrowserAdapter(theme, theme.replace(': 1.6;', ': "1.6";')), /stale/);
  assert.throws(() => verifyBrowserAdapter(theme, theme.replace('--leading-body-md: 1.6;', '--leading-body-md: 1.6; --leading-body-md: 1.6;')), /duplicates/);
});
