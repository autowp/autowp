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

// Constructs that don't render a wrapper element/box of their own - their content flows directly
// into the parent, so leading/trailing whitespace-adjacency has to be resolved through them
// instead of treating them as an opaque (non-inline) block.
const TRANSPARENT_CHILDREN_TYPES = new Set([
  'Template',
  'IfBlockBranch',
  'ForLoopBlockEmpty',
  'SwitchBlockCase',
  'DeferredBlock',
  'DeferredBlockPlaceholder',
  'DeferredBlockLoading',
  'DeferredBlockError',
]);

function describe(node) {
  if (!node) return '(none)';
  const type = node.constructor.name;
  if (type === 'Text') return `Text:${JSON.stringify(node.value.trim() || node.value)}`;
  if (type === 'BoundText') return `BoundText:${JSON.stringify(node.value?.source ?? '')}`;
  if (type === 'Element') return `<${node.name}>`;
  if (type === 'Component') return `<${node.tagName ?? node.componentName}>`;
  return `[${type}]`;
}

function isWhitespaceOnly(node) {
  return node && node.constructor.name === 'Text' && node.value.trim() === '';
}

// Resolves whether the rendered boundary of `node` on the given side (dir: 1 = leading/first,
// -1 = trailing/last) is inline content. Returns true/false, or null if `node` renders nothing at
// all on that side (whitespace-only text, empty transparent wrapper, empty control-flow block) -
// callers should then keep looking at the next sibling in that direction. `survivingOffsets` lets
// a whitespace-only-looking Text node that's actually an &ngsp; (or similar) count as real inline
// content instead of "renders nothing", the same way the top-level removal check does.
function boundaryInlineish(node, dir, survivingOffsets) {
  if (!node) return null;
  const type = node.constructor.name;

  if (type === 'Text') {
    if (node.value.trim() !== '') return true;
    return survivingOffsets.has(node.sourceSpan?.start.offset) ? true : null;
  }
  if (type === 'BoundText') return true;

  if (type === 'Element' || type === 'Component') {
    const name = (node.name ?? node.tagName ?? '').toLowerCase();
    if (name === 'ng-container') {
      return boundaryOfList(node.children ?? [], dir, survivingOffsets);
    }
    return INLINE_TAGS.has(name);
  }

  if (TRANSPARENT_CHILDREN_TYPES.has(type)) {
    return boundaryOfList(node.children ?? [], dir, survivingOffsets);
  }

  if (type === 'IfBlock') {
    // Which branch renders is data-dependent, so union across all of them: if any branch could
    // put inline content at this edge, treat the whole block as possibly-inline (conservative).
    return unionOfBranches((node.branches ?? []).map((b) => b.children ?? []), dir, survivingOffsets);
  }

  if (type === 'ForLoopBlock') {
    return unionOfBranches([node.children ?? [], node.empty?.children ?? []], dir, survivingOffsets);
  }

  if (type === 'SwitchBlock') {
    return unionOfBranches((node.cases ?? []).map((c) => c.children ?? []), dir, survivingOffsets);
  }

  // Unknown node type (or a real block-level Element not in INLINE_TAGS): treat as non-inline.
  return false;
}

function unionOfBranches(branches, dir, survivingOffsets) {
  let anyTrue = false;
  let anyDefinite = false;
  for (const children of branches) {
    const r = boundaryOfList(children, dir, survivingOffsets);
    if (r === true) {
      anyTrue = true;
      anyDefinite = true;
    } else if (r === false) {
      anyDefinite = true;
    }
  }
  if (!anyDefinite) return null;
  return anyTrue;
}

function boundaryOfList(children, dir, survivingOffsets) {
  const list = dir === 1 ? children : [...children].reverse();
  for (const child of list) {
    const r = boundaryInlineish(child, dir, survivingOffsets);
    if (r !== null) return r;
  }
  return null;
}

// Scans siblings away from a removed whitespace-only node in the given direction, resolving
// through anything that renders nothing on that side, to find the real adjacent content.
function adjacentInlineish(siblings, dir, survivingOffsets) {
  for (const node of siblings) {
    const r = boundaryInlineish(node, dir, survivingOffsets);
    if (r !== null) return r;
  }
  return false;
}

// Separate (tag, children) traversal targets for a node - kept as distinct lists rather than
// flattened together, since e.g. two @if branches or a @defer block's main/@placeholder content
// are mutually exclusive and never actually adjacent siblings in the rendered DOM.
function traversalTargets(node) {
  const type = node.constructor.name;
  const targets = [];
  const tag = node.name ?? node.tagName ?? type;

  if (node.children) targets.push({tag, children: node.children});
  if (type === 'IfBlock') {
    for (const branch of node.branches ?? []) targets.push({tag: 'IfBlockBranch', children: branch.children ?? []});
  }
  if (type === 'ForLoopBlock' && node.empty) {
    targets.push({tag: 'ForLoopBlockEmpty', children: node.empty.children ?? []});
  }
  if (type === 'SwitchBlock') {
    for (const c of node.cases ?? []) targets.push({tag: 'SwitchBlockCase', children: c.children ?? []});
  }
  if (type === 'DeferredBlock') {
    if (node.placeholder) targets.push({tag: 'DeferredBlockPlaceholder', children: node.placeholder.children ?? []});
    if (node.loading) targets.push({tag: 'DeferredBlockLoading', children: node.loading.children ?? []});
    if (node.error) targets.push({tag: 'DeferredBlockError', children: node.error.children ?? []});
  }
  return targets;
}

// Collects every text node's source start-offset that actually survives under
// preserveWhitespaces:false. &ngsp; (and any other escape) decodes to a plain space character
// indistinguishable from raw whitespace once parsed - in *either* mode - so the only reliable way
// to know a given whitespace-only node won't really be removed is to check whether something with
// the same source position exists in a real preserveWhitespaces:false parse.
function collectSurvivingOffsets(nodes, set) {
  for (const node of nodes) {
    if (node.constructor.name === 'Text' && node.sourceSpan) {
      set.add(node.sourceSpan.start.offset);
    }
    for (const {children} of traversalTargets(node)) {
      collectSurvivingOffsets(children, set);
    }
  }
}

function walk(nodes, path, survivingOffsets, out) {
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    if (isWhitespaceOnly(node) && !survivingOffsets.has(node.sourceSpan?.start.offset)) {
      const prev = nodes.slice(0, i).reverse();
      const next = nodes.slice(i + 1);
      const risky = adjacentInlineish(prev, -1, survivingOffsets) && adjacentInlineish(next, 1, survivingOffsets);
      out.push({path: path.join(' > ') || '(root)', prev: describe(nodes[i - 1]), next: describe(nodes[i + 1]), risky});
    }
    for (const {tag, children} of traversalTargets(node)) {
      walk(children, [...path, tag], survivingOffsets, out);
    }
  }
}

const file = process.argv[2];
const template = readFileSync(file, 'utf8');
const parsed = parseTemplate(template, file, {preserveWhitespaces: true});
const parsedFalse = parseTemplate(template, file, {preserveWhitespaces: false});
const survivingOffsets = new Set();
collectSurvivingOffsets(parsedFalse.nodes, survivingOffsets);
const removed = [];
walk(parsed.nodes, [], survivingOffsets, removed);

if (removed.length === 0) {
  console.log(`${file}: no whitespace-only text nodes found (nothing changes)`);
} else {
  const anyRisky = removed.some((r) => r.risky);
  console.log(`${file}: ${removed.length} whitespace-only node(s) would be removed${anyRisky ? '  [!] POSSIBLE RISK' : ''}`);
  for (const r of removed) {
    console.log(`  ${r.risky ? 'RISK' : 'safe'}  in ${r.path}:  ${r.prev}  [removed]  ${r.next}`);
  }
}
