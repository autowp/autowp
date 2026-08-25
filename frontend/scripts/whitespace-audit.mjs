import {parseTemplate} from '@angular/compiler';
import {readFileSync} from 'node:fs';

const INLINE_TAGS = new Set([
  'a',
  'abbr',
  'b',
  'bdi',
  'bdo',
  'br',
  'button',
  'cite',
  'code',
  'data',
  'dfn',
  'em',
  'i',
  'img',
  'input',
  'kbd',
  'label',
  'mark',
  'q',
  'rp',
  'rt',
  'ruby',
  's',
  'samp',
  'small',
  'span',
  'strong',
  'sub',
  'sup',
  'time',
  'u',
  'var',
  'wbr',
]);

function describe(node) {
  if (!node) return '(none)';
  const type = node.constructor.name;
  if (type === 'Text') return `Text:${JSON.stringify(node.value.trim() || node.value)}`;
  if (type === 'BoundText') return `BoundText:${JSON.stringify(node.value?.source ?? '')}`;
  if (type === 'Element') return `<${node.name}>`;
  if (type === 'Component') return `<${node.tagName ?? node.componentName}>`;
  if (type === 'Template') return '<template>';
  return `[${type}]`;
}

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

function walk(nodes, path, out) {
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    if (isWhitespaceOnly(node)) {
      const prev = nodes[i - 1];
      const next = nodes[i + 1];
      const risky = isInlineish(prev) && isInlineish(next);
      out.push({path: path.join(' > ') || '(root)', prev: describe(prev), next: describe(next), risky});
    }
    const children = node.children ?? (node.branches ? node.branches.flatMap((b) => b.children ?? []) : null);
    if (children) {
      const tag = node.name ?? node.tagName ?? node.constructor.name;
      walk(children, [...path, tag], out);
    }
  }
}

const file = process.argv[2];
const template = readFileSync(file, 'utf8');
const parsed = parseTemplate(template, file, {preserveWhitespaces: true});
const removed = [];
walk(parsed.nodes, [], removed);

if (removed.length === 0) {
  console.log(`${file}: no whitespace-only text nodes found (nothing changes)`);
} else {
  const anyRisky = removed.some((r) => r.risky);
  console.log(`${file}: ${removed.length} whitespace-only node(s) would be removed${anyRisky ? '  [!] POSSIBLE RISK' : ''}`);
  for (const r of removed) {
    console.log(`  ${r.risky ? 'RISK' : 'safe'}  in ${r.path}:  ${r.prev}  [removed]  ${r.next}`);
  }
}
