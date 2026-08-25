import {parseTemplate} from '@angular/compiler';
import {readFileSync, existsSync} from 'node:fs';
import {dirname, resolve} from 'node:path';
import {globSync} from 'node:fs';

const INLINE_TAGS = new Set([
  'a', 'abbr', 'b', 'bdi', 'bdo', 'br', 'button', 'cite', 'code', 'data', 'dfn', 'em', 'i', 'img',
  'input', 'kbd', 'label', 'mark', 'q', 'rp', 'rt', 'ruby', 's', 'samp', 'small', 'span', 'strong',
  'sub', 'sup', 'time', 'u', 'var', 'wbr',
]);

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

function isWhitespaceOnly(node) {
  return node && node.constructor.name === 'Text' && node.value.trim() === '';
}

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
    if (name === 'ng-container') return boundaryOfList(node.children ?? [], dir, survivingOffsets);
    return INLINE_TAGS.has(name);
  }

  if (TRANSPARENT_CHILDREN_TYPES.has(type)) {
    return boundaryOfList(node.children ?? [], dir, survivingOffsets);
  }

  if (type === 'IfBlock') return unionOfBranches((node.branches ?? []).map((b) => b.children ?? []), dir, survivingOffsets);
  if (type === 'ForLoopBlock') return unionOfBranches([node.children ?? [], node.empty?.children ?? []], dir, survivingOffsets);
  if (type === 'SwitchBlock') return unionOfBranches((node.cases ?? []).map((c) => c.children ?? []), dir, survivingOffsets);

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

function adjacentInlineish(siblings, dir, survivingOffsets) {
  for (const node of siblings) {
    const r = boundaryInlineish(node, dir, survivingOffsets);
    if (r !== null) return r;
  }
  return false;
}

function traversalTargets(node) {
  const type = node.constructor.name;
  const targets = [];
  if (node.children) targets.push(node.children);
  if (type === 'IfBlock') {
    for (const branch of node.branches ?? []) targets.push(branch.children ?? []);
  }
  if (type === 'ForLoopBlock' && node.empty) targets.push(node.empty.children ?? []);
  if (type === 'SwitchBlock') {
    for (const c of node.cases ?? []) targets.push(c.children ?? []);
  }
  if (type === 'DeferredBlock') {
    if (node.placeholder) targets.push(node.placeholder.children ?? []);
    if (node.loading) targets.push(node.loading.children ?? []);
    if (node.error) targets.push(node.error.children ?? []);
  }
  return targets;
}

function collectSurvivingOffsets(nodes, set) {
  for (const node of nodes) {
    if (node.constructor.name === 'Text' && node.sourceSpan) set.add(node.sourceSpan.start.offset);
    for (const children of traversalTargets(node)) collectSurvivingOffsets(children, set);
  }
}

function walk(nodes, survivingOffsets, out) {
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    if (isWhitespaceOnly(node) && !survivingOffsets.has(node.sourceSpan?.start.offset)) {
      const prev = nodes.slice(0, i).reverse();
      const next = nodes.slice(i + 1);
      if (adjacentInlineish(prev, -1, survivingOffsets) && adjacentInlineish(next, 1, survivingOffsets)) out.risky++;
      out.removed++;
    }
    for (const children of traversalTargets(node)) walk(children, survivingOffsets, out);
  }
}

function auditFile(htmlPath) {
  const template = readFileSync(htmlPath, 'utf8');
  const parsedTrue = parseTemplate(template, htmlPath, {preserveWhitespaces: true});
  const parsedFalse = parseTemplate(template, htmlPath, {preserveWhitespaces: false});
  const survivingOffsets = new Set();
  collectSurvivingOffsets(parsedFalse.nodes, survivingOffsets);
  const out = {removed: 0, risky: 0};
  walk(parsedTrue.nodes, survivingOffsets, out);
  return out;
}

const tsFiles = globSync('src/app/**/*.component.ts');
const results = {safe: [], risky: [], alreadyMigratedRisky: [], skippedNoTemplateFile: [], errors: []};

for (const tsFile of tsFiles) {
  const src = readFileSync(tsFile, 'utf8');
  const alreadyMigrated = /preserveWhitespaces\s*:\s*false/.test(src);

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
    if (alreadyMigrated) {
      if (risky > 0) results.alreadyMigratedRisky.push({file: tsFile, removed, risky});
    } else if (risky > 0) {
      results.risky.push({file: tsFile, removed, risky});
    } else {
      results.safe.push({file: tsFile, removed});
    }
  } catch (err) {
    results.errors.push({file: tsFile, error: String(err)});
  }
}

results.safe.sort((a, b) => b.removed - a.removed);
results.risky.sort((a, b) => b.risky - a.risky);
results.alreadyMigratedRisky.sort((a, b) => b.risky - a.risky);

console.log(`Total component.ts files: ${tsFiles.length}`);
console.log(`No templateUrl / inline template: ${results.skippedNoTemplateFile.length}`);
console.log(`Errors parsing: ${results.errors.length}`);
console.log(`SAFE candidates not yet migrated (0 risk): ${results.safe.length}`);
console.log(`RISKY, not yet migrated: ${results.risky.length}`);
console.log(`ALREADY MIGRATED but now flagged RISKY (regression!): ${results.alreadyMigratedRisky.length}`);
console.log('');

console.log('=== ALREADY MIGRATED BUT RISKY - real regressions, fix these first ===');
for (const r of results.alreadyMigratedRisky) {
  console.log(`  ${r.file}  (removed=${r.removed}, risky=${r.risky})`);
}

console.log('');
console.log('=== RISKY, not yet migrated (needs manual review, skip for now) ===');
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
