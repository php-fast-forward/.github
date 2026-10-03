// Compatibility fixes for @google/design.md@0.4.0's typography exports.
// DTCG: https://www.designtokens.org/tr/2025.10/format/#typography
import { isDeepStrictEqual } from 'node:util';

export function verifyBrowserAdapter(theme, adapter) {
  const declarations = text => [...text.matchAll(/(--[\w-]+)\s*:\s*([^;]+);/g)];
  const normalize = (name, value) => /^--font-(?!weight-)/.test(name)
    ? fontFamilies(value.trim()) : value.replace(/\s+/g, ' ').trim();
  const expected = new Map(declarations(theme).map(([, name, value]) => [name, normalize(name, value)]));
  const actual = new Map();
  for (const [, name, value] of declarations(adapter)) {
    if (!expected.has(name)) continue;
    if (actual.has(name)) throw new Error(`Browser token adapter duplicates ${name}`);
    actual.set(name, normalize(name, value));
  }
  if (!isDeepStrictEqual(actual, expected)) throw new Error('Browser token adapter is stale; update documentation.css from the generated theme');
}

export function fontFamilies(stack) {
  const names = [];
  let name = '', quote = '', escaped = false;
  for (const character of stack) {
    if (escaped) { name += character; escaped = false; }
    else if (character === '\\') escaped = true;
    else if (quote) {
      if (character === quote) quote = '';
      else name += character;
    } else if (character === '"' || character === "'") quote = character;
    else if (character === ',') { names.push(name.trim()); name = ''; }
    else name += character;
  }
  names.push(name.trim());
  if (quote || escaped || names.some(value => !value)) throw new Error('Invalid font-family stack');
  return names;
}

export function normalizeExport(format, raw) {
  if (format === 'css-tailwind') {
    // The upstream emitter quotes the whole list as a single family.
    let css = raw.replace(/(--font-[\w-]+):\s*"([^;]+)";/g, (_, property, stack) =>
      `${property}: ${fontFamilies(stack).map(name => /[\s,]/.test(name) ? JSON.stringify(name) : name).join(', ')};`);
    // Tailwind v4 text utilities read their associated typography properties.
    const metadata = [...css.matchAll(/--(leading|tracking|font-weight)-([\w-]+):\s*([^;]+);/g)];
    const property = { leading: 'line-height', tracking: 'letter-spacing', 'font-weight': 'font-weight' };
    const associated = metadata.map(([, kind, name, value]) => `  --text-${name}--${property[kind]}: ${value};`);
    css = css.trim().replace(/\s*}$/, `\n${associated.join('\n')}\n}`);
    return `${css}\n`;
  }
  const value = JSON.parse(raw);
  if (format === 'dtcg') {
    // The emitter advertises a schema URL not supplied by the DTCG report.
    delete value.$schema;
    for (const [name, token] of Object.entries(value.typography)) {
      const typography = token.$value;
      for (const key of ['fontFamily', 'fontSize', 'fontWeight', 'letterSpacing', 'lineHeight']) {
        if (!(key in typography)) throw new Error(`Typography ${name} is missing ${key}; declare it in DESIGN.md (quote unitless lineHeight for CLI 0.4.0)`);
      }
      typography.fontFamily = fontFamilies(typography.fontFamily);
      if (!Number.isFinite(typography.lineHeight) || typography.lineHeight <= 0) throw new Error(`Invalid lineHeight: ${name}`);
      if (!['px', 'rem'].includes(typography.fontSize.unit)) throw new Error(`Unsupported fontSize unit: ${name}`);
      if (typography.letterSpacing.unit === 'em') {
        typography.letterSpacing = {
          value: Number((typography.letterSpacing.value * typography.fontSize.value).toPrecision(12)),
          unit: typography.fontSize.unit,
        };
      }
      if (!['px', 'rem'].includes(typography.letterSpacing.unit)) throw new Error(`Unsupported letterSpacing unit: ${name}`);
    }
  } else if (format === 'json-tailwind') {
    const theme = value.theme.extend;
    for (const [name, stack] of Object.entries(theme.fontFamily)) theme.fontFamily[name] = fontFamilies(stack[0]);
  } else throw new Error(`Unsupported export format: ${format}`);
  return `${JSON.stringify(value, null, 2)}\n`;
}
