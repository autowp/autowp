import {parseTemplate} from '@angular/compiler';
import {readFileSync, existsSync} from 'node:fs';
import {dirname, resolve} from 'node:path';
import {globSync} from 'node:fs';

const INLINE_TAGS = new Set([
  'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'button', 'cite', 'code', 'data', 'dfn', 'em', 'i', 'img',
  'input', 'kbd', 'label', 'mark', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'small', 'span', 'strong',
  'sub', 'sup', 'time', 'u', 'var', 'wbr',
]);

function isWhitespaceOnly(node) {
  return node && node.constructor.name === 'Text' && node.value.trim() === '';
}

function isInlineish(node) {
  if (!node) return false;
  const type = node.constructor.name;
  if (type === 'Text' || type === 'BoundText') return true;
  if (type === 'Element' || type === 'Component') return INLINE_TAGS.has((node.name ?? node.tagName ?? '').toLowerCase());
  return false;
}

function walk(nodes, out) {
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    if (isWhitespaceOnly(node)) {
      const prev = nodes[i - 1];
      const next = nodes[i + 1];
      if (isInlineish(prev) && isInlineish(next)) out.risky++;
      out.removed++;
    }
    const children = node.children ?? (node.branches ? node.branches.flatMap((b) => b.children ?? []) : null);
    if (children) walk(children, out);
  }
}

function auditFile(htmlPath) {
  const template = readFileSync(htmlPath, 'utf8');
  const parsed = parseTemplate(template, htmlPath, {preserveWhitespaces: true});
  const out = {removed: 0, risky: 0};
  walk(parsed.nodes, out);
  return out;
}

const tsFiles = globSync('src/app/**/*.component.ts');
const results = {safe: [], risky: [], skippedAlready: [], skippedNoTemplateFile: [], errors: []};

for (const tsFile of tsFiles) {
  const src = readFileSync(tsFile, 'utf8');
  if (/preserveWhitespaces\s*:/.test(src)) {
    results.skippedAlready.push(tsFile);
    continue;
  }

  const m = src.match(/templateUrl:\s*['"`]([^'"`]+)['"`]/);
  if (!m) {
    results.skippedNoTemplateFile.push(tsFile);
    continue;
  }

  const htmlPath = resolve(dirname(tsFile), m[1]);
  if (!existsSync(htmlPath)) {
    results.skippedNoTemplateFile.push(tsFile);
    continue;
  }

  try {
    const {removed, risky} = auditFile(htmlPath);
    if (risky > 0) {
      results.risky.push({file: tsFile, removed, risky});
    } else {
      results.safe.push({file: tsFile, removed});
    }
  } catch (err) {
    results.errors.push({file: tsFile, error: String(err)});
  }
}

results.safe.sort((a, b) => b.removed - a.removed);

console.log(`Total component.ts files: ${tsFiles.length}`);
console.log(`Already has preserveWhitespaces: ${results.skippedAlready.length}`);
console.log(`No templateUrl / inline template: ${results.skippedNoTemplateFile.length}`);
console.log(`Errors parsing: ${results.errors.length}`);
console.log(`SAFE candidates (0 risk): ${results.safe.length}`);
console.log(`RISKY (has inline-adjacent whitespace removal): ${results.risky.length}`);
console.log('');

console.log('=== RISKY (needs manual review, skip for now) ===');
for (const r of results.risky) {
  console.log(`  ${r.file}  (removed=${r.removed}, risky=${r.risky})`);
}

console.log('');
console.log('=== SAFE candidates with removed > 0, sorted by impact ===');
for (const r of results.safe.filter((x) => x.removed > 0)) {
  console.log(`  ${r.file}  (removed=${r.removed})`);
}

console.log('');
console.log(`=== SAFE candidates with removed === 0 (no-op, ${results.safe.filter((x) => x.removed === 0).length} files) ===`);

if (results.errors.length) {
  console.log('');
  console.log('=== ERRORS ===');
  for (const e of results.errors) {
    console.log(`  ${e.file}: ${e.error}`);
  }
}
