import {readFileSync} from 'node:fs';

const AUTH_SIGNALS = ['isModer', 'isAuth', 'canEdit', 'hasRole', 'isLogged', 'isAuthenticated', 'authenticated'];

const files = process.argv.slice(2);

function findResourceBlocks(src) {
  const blocks = [];
  const re = /rxResource\(\{/g;
  let m;
  while ((m = re.exec(src))) {
    const start = m.index;
    let i = m.index + 'rxResource({'.length - 1; // at the opening brace
    let depth = 1;
    while (depth > 0 && i < src.length - 1) {
      i++;
      if (src[i] === '{') depth++;
      else if (src[i] === '}') depth--;
    }
    blocks.push(src.slice(start, i + 1));
  }
  return blocks;
}

for (const file of files) {
  const src = readFileSync(file, 'utf8');
  const blocks = findResourceBlocks(src);
  for (const block of blocks) {
    const idMatch = block.match(/(?<![A-Za-z])id:\s*(`[^`]*`|'[^']*'|"[^"]*")/);
    if (!idMatch) continue;
    const idExpr = idMatch[1];

    const paramsMatch = block.match(/params:\s*\(\)\s*=>[\s\S]*?(?=,\s*\n\s*stream:|,\s*\n\s*loader:)/);
    const paramsSrc = paramsMatch ? paramsMatch[0] : '';

    for (const signal of AUTH_SIGNALS) {
      const usedInParams = new RegExp(`\\b${signal}\\b`).test(paramsSrc);
      const usedInId = new RegExp(`\\b${signal}\\b`).test(idExpr);
      if (usedInParams && !usedInId) {
        console.log(`${file}`);
        console.log(`  id: ${idExpr}`);
        console.log(`  params references '${signal}' but id doesn't include it`);
        console.log('');
      }
    }
  }
}
