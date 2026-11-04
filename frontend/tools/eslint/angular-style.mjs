import path from 'node:path';

function kebabCase(name) {
  return name
    .replace(/([A-Z]+)([A-Z][a-z])/g, '$1-$2')
    .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
    .toLowerCase();
}

const componentNames = {
  meta: {
    type: 'suggestion',
    schema: [],
    messages: {
      suffix: 'Use a descriptive component name without the Component suffix.',
      filename: 'Name this component file {{expected}} to match its class.',
      resource: 'Keep component resources beside the TypeScript file with the same base name ({{expected}}).',
    },
  },
  create(context) {
    const componentImports = new Set();
    const namespaceImports = new Set();
    return {
      ImportDeclaration(node) {
        if (node.source.value !== '@angular/core') return;
        for (const specifier of node.specifiers) {
          if (specifier.type === 'ImportSpecifier' && specifier.imported.name === 'Component') {
            componentImports.add(specifier.local.name);
          } else if (specifier.type === 'ImportNamespaceSpecifier') {
            namespaceImports.add(specifier.local.name);
          }
        }
      },
      ClassDeclaration(node) {
        if (!node.id) return;
        const decorator = node.decorators.find(({expression}) => {
          if (expression.type !== 'CallExpression') return false;
          const callee = expression.callee;
          return (
            (callee.type === 'Identifier' && componentImports.has(callee.name)) ||
            (callee.type === 'MemberExpression' &&
              !callee.computed &&
              namespaceImports.has(callee.object.name) &&
              callee.property.name === 'Component')
          );
        });
        if (!decorator) return;
        if (node.id.name.endsWith('Component')) {
          context.report({node: node.id, messageId: 'suffix'});
        }
        const base = kebabCase(node.id.name);
        const filename = context.filename;
        if (path.basename(filename) !== `${base}.ts`) {
          context.report({node: node.id, messageId: 'filename', data: {expected: `${base}.ts`}});
        }
        const metadata = decorator.expression.arguments[0];
        if (metadata?.type !== 'ObjectExpression') return;
        for (const property of metadata.properties) {
          if (property.type !== 'Property' || property.computed) continue;
          const name = property.key.name ?? property.key.value;
          if (!['templateUrl', 'styleUrl', 'styleUrls'].includes(name)) continue;
          const urls = property.value.type === 'ArrayExpression' ? property.value.elements : [property.value];
          for (const url of urls) {
            // Computed metadata is left to Angular's compiler; lint only static resource paths.
            if (url?.type !== 'Literal' || typeof url.value !== 'string') continue;
            const resource = path.resolve(path.dirname(filename), url.value);
            const resourceBase = path.parse(resource).name;
            const matches = resourceBase === base || (name !== 'templateUrl' && resourceBase.startsWith(`${base}-`));
            if (path.dirname(resource) !== path.dirname(filename) || !matches) {
              context.report({node: url, messageId: 'resource', data: {expected: base}});
            }
          }
        }
      },
    };
  },
};

const memberOrder = {
  meta: {
    type: 'suggestion',
    schema: [],
    messages: {
      order: 'Declare fields first, then the constructor, then methods/accessors. Preserve field initialization order.',
    },
  },
  create(context) {
    return {
      ClassBody(node) {
        let previousRank = 0;
        for (const member of node.body) {
          // Arrow-function properties are fields too: their position affects initialization.
          const field = [
            'PropertyDefinition',
            'TSAbstractPropertyDefinition',
            'AccessorProperty',
            'StaticBlock',
            'TSIndexSignature',
          ].includes(member.type);
          const rank = field ? 0 : member.kind === 'constructor' ? 1 : 2;
          if (rank < previousRank) {
            context.report({node: member, messageId: 'order'});
          }
          previousRank = Math.max(previousRank, rank);
        }
      },
    };
  },
};

export default {rules: {'component-names': componentNames, 'member-order': memberOrder}};
