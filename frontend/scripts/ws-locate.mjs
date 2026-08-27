import {parseTemplate} from '@angular/compiler';
import {readFileSync, existsSync} from 'node:fs';
import {dirname, resolve} from 'node:path';

const INLINE_TAGS = new Set(['a','abbr','b','bdi','bdo','br','button','cite','code','data','dfn','em','i','img','input','kbd','label','mark','q','rp','rt','ruby','s','samp','small','span','strong','sub','sup','time','u','var','wbr']);
const TRANSPARENT = new Set(['Template','IfBlockBranch','ForLoopBlockEmpty','SwitchBlockCase','DeferredBlock','DeferredBlockPlaceholder','DeferredBlockLoading','DeferredBlockError']);
const isWs = (n) => n && n.constructor.name === 'Text' && n.value.trim() === '';

function boundaryInlineish(node, dir, surv) {
  if (!node) return null;
  const t = node.constructor.name;
  if (t === 'Text') { if (node.value.trim() !== '') return true; return surv.has(node.sourceSpan?.start.offset) ? true : null; }
  if (t === 'BoundText') return true;
  if (t === 'Element' || t === 'Component') {
    const name = (node.name ?? node.tagName ?? '').toLowerCase();
    if (name === 'ng-container') return boundaryOfList(node.children ?? [], dir, surv);
    const cls = (node.attributes ?? []).find((a) => a.name === 'class');
    if (cls && /\bd-inline(-block)?\b/.test(cls.value ?? '')) return true;
    return INLINE_TAGS.has(name);
  }
  if (TRANSPARENT.has(t)) return boundaryOfList(node.children ?? [], dir, surv);
  if (t === 'IfBlock') return union((node.branches ?? []).map((b) => b.children ?? []), dir, surv);
  if (t === 'ForLoopBlock') return union([node.children ?? [], node.empty?.children ?? []], dir, surv);
  if (t === 'SwitchBlock') return union((node.cases ?? []).map((c) => c.children ?? []), dir, surv);
  return false;
}
function union(branches, dir, surv) {
  let anyTrue = false, anyDef = false;
  for (const ch of branches) { const r = boundaryOfList(ch, dir, surv); if (r === true) { anyTrue = true; anyDef = true; } else if (r === false) anyDef = true; }
  return anyDef ? anyTrue : null;
}
function boundaryOfList(children, dir, surv) {
  const list = dir === 1 ? children : [...children].reverse();
  for (const c of list) { const r = boundaryInlineish(c, dir, surv); if (r !== null) return r; }
  return null;
}
function adj(sib, dir, surv) { for (const n of sib) { const r = boundaryInlineish(n, dir, surv); if (r !== null) return r; } return false; }
function targets(n) {
  const t = n.constructor.name, out = [];
  if (n.children) out.push(n.children);
  if (t === 'IfBlock') for (const b of n.branches ?? []) out.push(b.children ?? []);
  if (t === 'ForLoopBlock' && n.empty) out.push(n.empty.children ?? []);
  if (t === 'SwitchBlock') for (const c of n.cases ?? []) out.push(c.children ?? []);
  if (t === 'DeferredBlock') { if (n.placeholder) out.push(n.placeholder.children ?? []); if (n.loading) out.push(n.loading.children ?? []); if (n.error) out.push(n.error.children ?? []); }
  return out;
}
function surviving(nodes, set) { for (const n of nodes) { if (n.constructor.name === 'Text' && n.sourceSpan) set.add(n.sourceSpan.start.offset); for (const c of targets(n)) surviving(c, set); } }
function label(n) {
  const t = n.constructor.name;
  if (t === 'Text') return JSON.stringify(n.value.trim() || '·ws·');
  if (t === 'BoundText') return '{{'+(n.value.source||'').trim().slice(0,30)+'}}';
  if (t === 'Element' || t === 'Component') return '<'+(n.name||n.tagName)+'>';
  if (t === 'IfBlock') return '@if';
  if (t === 'ForLoopBlock') return '@for';
  if (t === 'SwitchBlock') return '@switch';
  return t;
}
function firstReal(list, dir) { const l = dir===1?list:[...list].reverse(); for (const n of l) { if (!isWs(n)) return n; } return null; }
function walk(nodes, surv, src, hits) {
  for (let i = 0; i < nodes.length; i++) {
    const node = nodes[i];
    if (isWs(node) && !surv.has(node.sourceSpan?.start.offset)) {
      const prev = nodes.slice(0, i).reverse(), next = nodes.slice(i + 1);
      if (adj(prev, -1, surv) && adj(next, 1, surv)) {
        const ln = node.sourceSpan.start.line + 1;
        const L = firstReal(prev, -1), R = firstReal(next, 1);
        hits.push(`  L${ln}: ${L?label(L):'?'} ␣ ${R?label(R):'?'}`);
      }
    }
    for (const c of targets(node)) walk(c, surv, src, hits);
  }
}
for (const tsFile of process.argv.slice(2)) {
  const src = readFileSync(tsFile, 'utf8');
  const m = src.match(/templateUrl:\s*['"`]([^'"`]+)['"`]/);
  if (!m) { console.log(tsFile + '  (inline template - skip)'); continue; }
  const html = resolve(dirname(tsFile), m[1]);
  if (!existsSync(html)) { console.log(tsFile + '  (no html)'); continue; }
  const tpl = readFileSync(html, 'utf8');
  const pf = parseTemplate(tpl, html, {preserveWhitespaces: false});
  const set = new Set(); surviving(pf.nodes, set);
  const pt = parseTemplate(tpl, html, {preserveWhitespaces: true});
  const hits = []; walk(pt.nodes, set, tpl, hits);
  console.log(html.replace(/^.*\/src\/app\//,'src/app/') + (pf.errors?.length ? '  PARSE ERRORS' : ''));
  console.log(hits.join('\n') || '  (no risky sites)');
  console.log();
}
