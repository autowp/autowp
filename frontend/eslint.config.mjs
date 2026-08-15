import eslint from '@eslint/js';
import angular from 'angular-eslint';
import depend from 'eslint-plugin-depend';
import perfectionist from 'eslint-plugin-perfectionist';
import {default as eslintPluginPrettierRecommended} from 'eslint-plugin-prettier/recommended';
import rxjsX from 'eslint-plugin-rxjs-x';
import sonarjs from 'eslint-plugin-sonarjs';
import {defineConfig, globalIgnores} from 'eslint/config';
import tseslint from 'typescript-eslint';

export default defineConfig([
  globalIgnores([
    'dist',
    'dist/**/*',
    '.scannerwork/**/*',
    'node_modules/**/*',
    '.idea/**/*',
    '.angular/**/*',
    'src/rest/**/*',
    'src/grpc/**/*',
  ]),
  {
    extends: [
      // Apply the recommended core rules
      eslint.configs.recommended,
      // Type-checked TypeScript rules (superset of recommended/stylistic - see the comment on
      // languageOptions.parserOptions below for what makes the type info available).
      ...tseslint.configs.strictTypeChecked,
      ...tseslint.configs.stylisticTypeChecked,
      // Apply the recommended Angular rules
      ...angular.configs.tsAll,
    ],
    // Everything in this config object targets our TypeScript files (Components, Directives, Pipes etc)
    files: ['**/*.ts'],
    // Set the custom processor which will allow us to have our inline Component templates extracted
    // and treated as if they are HTML files (and therefore have the .html config below applied to them)
    processor: angular.processInlineTemplates,
    languageOptions: {
      // Required for the *TypeChecked rule sets above - they need real TypeScript type info, not
      // just syntax. `projectService` auto-discovers and builds the program from tsconfig.json
      // (no per-tsconfig list to keep in sync, unlike the old parserOptions.project array this
      // replaced - that one pointed at a nonexistent e2e/tsconfig.json).
      parserOptions: {
        projectService: true,
        tsconfigRootDir: import.meta.dirname,
      },
    },
    // Override specific rules for TypeScript files (these will take priority over the extended configs above)
    rules: {
      // allowSeparateTypeImports: consistent-type-imports below splits a module's value and
      // type-only imports into two separate `import`/`import type` statements when only some of
      // its named imports are type-only - that's two imports from the same source by design, not
      // an accidental duplicate.
      'no-duplicate-imports': ['error', {allowSeparateTypeImports: true}],
      'no-restricted-globals': ['error', {globals: ['window', 'document', 'event']}],
      'no-restricted-syntax': [
        'error',
        {
          message:
            "Do not call .toDate() directly on a protobuf Timestamp - a resource value seeded from TransferState during SSR hydration is a plain JSON-shaped object, not a real Timestamp class instance, so .toDate() doesn't exist on it even though seconds/nanos do. Use timestampToDate() from '@utils/timestamp' instead.",
          selector: "CallExpression[callee.type='MemberExpression'][callee.property.name='toDate']",
        },
        {
          message:
            "Do not embed an Observable-typed field (name ending in '$') in the value returned from rxResource's stream()/loader(). It doesn't survive the TransferState JSON round-trip on hydration - RxJS Observable instances serialize to '{}', and AsyncPipe throws on that non-Observable, non-Promise value. Resolve it (e.g. via forkJoin/switchMap) into a plain value before returning it, or fetch it as a separate resource.",
          selector:
            "CallExpression[callee.name='rxResource'] :matches(Property[key.name='stream'], Property[key.name='loader']) Property[key.name=/\\$$/]",
        },
      ],
      '@typescript-eslint/prefer-readonly': 'error',
      // Angular's own Validators.required/Validators.maxLength(...)/etc are static methods passed
      // by bare reference into a validators: [...] array - the exact pattern this rule normally
      // warns about, but they're pure (never touch `this`), so there's no real unbound-`this`
      // hazard. Every current occurrence in this codebase is this one safe, standard idiom.
      '@typescript-eslint/unbound-method': ['error', {ignoreStatic: true}],
      '@typescript-eslint/prefer-nullish-coalescing': 'error',
      '@typescript-eslint/no-non-null-assertion': 'error',
      // Prefer import-x's overlapping consistent-type-imports rule not being enabled: this one
      // gets full type info for free from the projectService config above, so there's no reason to
      // duplicate it via import-x.
      '@typescript-eslint/consistent-type-imports': ['error', {prefer: 'type-imports'}],
      // strictTypeChecked's defaults for these two (allowNumberAndString/allowNumber: false)
      // require every `numberValue + 'px'`/`` `${numberValue}px` `` to be spelled out as
      // `numberValue.toString() + 'px'` - ~90% of this codebase's ~80 flagged sites were exactly
      // that pattern (building CSS pixel strings, ids, aspect-ratio text), which is safe, common,
      // and not a real bug source. Relaxed back to allowing plain number+string (matching each
      // rule's own upstream default for that one flag), while re-stating strictTypeChecked's
      // other flags explicitly - passing a partial options object merges with the *rule's*
      // permissive defaults, not with strictTypeChecked's, so leaving them unstated here would
      // have silently also re-permitted `any`/`unknown`/nullish operands, which is what these
      // rules are actually for.
      '@typescript-eslint/restrict-plus-operands': [
        'error',
        {allowAny: false, allowBoolean: false, allowNullish: false, allowNumberAndString: true, allowRegExp: false},
      ],
      '@typescript-eslint/restrict-template-expressions': [
        'error',
        {
          allowAny: false,
          allowBoolean: false,
          allowNever: false,
          allowNullish: false,
          allowNumber: true,
          allowRegExp: false,
        },
      ],
      '@angular-eslint/runtime-localize': 'off',
      '@angular-eslint/component-selector': [
        'error',
        {
          prefix: 'app',
          style: 'kebab-case',
          type: 'element',
        },
      ],
      '@angular-eslint/directive-selector': [
        'error',
        {
          prefix: 'app',
          style: 'camelCase',
          type: 'attribute',
        },
      ],
    },
  },
  {
    extends: [...angular.configs.templateAll],
    // Everything in this config object targets our HTML files (external templates,
    // and inline templates as long as we have the `processor` set on our TypeScript config above)
    files: ['**/*.html'],
    rules: {
      '@angular-eslint/template/prefer-template-literal': 'off',
      '@angular-eslint/template/no-interpolation-in-attributes': ['error', {allowSubstringInterpolation: true}],
      '@angular-eslint/template/no-call-expression': 'off',
      '@angular-eslint/template/no-inline-styles': 'off',
      '@angular-eslint/template/i18n': 'off',
      '@angular-eslint/template/elements-content': 'off',
      '@angular-eslint/template/cyclomatic-complexity': 'off',
      '@angular-eslint/template/prefer-ngsrc': 'off',
    },
  },
  {
    extends: ['depend/flat/recommended'],
    files: ['**/*.ts'],
    plugins: {
      depend,
    },
  },
  {
    ...perfectionist.configs['recommended-natural'],
    rules: {
      ...perfectionist.configs['recommended-natural'].rules,
      'perfectionist/sort-classes': 'off',
      'perfectionist/sort-objects': 'off',
      'perfectionist/sort-modules': 'off',
    },
  },
  {
    ...sonarjs.configs.recommended,
    files: ['**/*.ts'],
  },
  {
    ...rxjsX.configs.recommended,
    files: ['**/*.ts'],
  },
  eslintPluginPrettierRecommended,
  {
    ignores: ['src/grpc/**/*', 'src/rest/**/*'],
  },
  {
    files: ['**/*.html'],
    ignores: ['**/*inline-template-*.component.html'],
    rules: {
      'prettier/prettier': [
        'error',
        {
          parser: 'angular',
        },
      ],
    },
  },
]);
