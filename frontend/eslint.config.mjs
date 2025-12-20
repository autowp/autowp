import eslint from '@eslint/js';
import angular from 'angular-eslint';
import depend from 'eslint-plugin-depend';
import perfectionist from 'eslint-plugin-perfectionist';
import {default as eslintPluginPrettierRecommended} from 'eslint-plugin-prettier/recommended';
import sonarjs from 'eslint-plugin-sonarjs';
import {defineConfig, globalIgnores} from 'eslint/config';
import tseslint from 'typescript-eslint';

export default defineConfig([
  globalIgnores([
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
      // Apply the recommended TypeScript rules
      ...tseslint.configs.recommended,
      // Optionally apply stylistic rules from typescript-eslint that improve code consistency
      ...tseslint.configs.stylistic,
      // Apply the recommended Angular rules
      ...angular.configs.tsAll,
    ],
    // Everything in this config object targets our TypeScript files (Components, Directives, Pipes etc)
    files: ['**/*.ts'],
    // Set the custom processor which will allow us to have our inline Component templates extracted
    // and treated as if they are HTML files (and therefore have the .html config below applied to them)
    processor: angular.processInlineTemplates,
    // Override specific rules for TypeScript files (these will take priority over the extended configs above)
    rules: {
      'no-duplicate-imports': 'error',
      'no-restricted-globals': ['error', {globals: ['window', 'document', 'event']}],
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
    },
  },
  {
    ...sonarjs.configs.recommended,
    files: ['**/*.ts'],
  },
  eslintPluginPrettierRecommended,
  {
    ignores: ['src/grpc/**/*', 'src/rest/**/*'],
  },
  {
    files: ['**/*.ts'],
    languageOptions: {
      ecmaVersion: 5,
      parserOptions: {
        createDefaultProgram: true,
        project: ['tsconfig.json', 'e2e/tsconfig.json'],
      },
      sourceType: 'script',
    },
    rules: {
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
