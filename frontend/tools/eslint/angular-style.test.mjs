import assert from 'node:assert/strict';
import path from 'node:path';
import {test} from 'node:test';

import {Linter} from 'eslint';
import tseslint from 'typescript-eslint';

import angularStyle from './angular-style.mjs';

const linter = new Linter();
function lint(code, filename = 'user-profile.ts') {
  return linter.verify(
    code,
    [
      {
        files: ['**/*.ts'],
        languageOptions: {parser: tseslint.parser},
        plugins: {'local-angular': angularStyle},
        rules: {
          'local-angular/component-names': 'error',
          'local-angular/member-order': 'error',
        },
      },
    ],
    {filename: path.resolve('src/app', filename)},
  );
}

const component = (name, metadata = '{}') =>
  `import {Component} from '@angular/core'; @Component(${metadata}) export class ${name} {}`;

test('accepts matching component names and additional descriptive styles', () => {
  assert.deepEqual(
    lint(
      component(
        'UserProfile',
        "{templateUrl: './user-profile.html', styleUrls: ['./user-profile.scss', './user-profile-settings.scss']}",
      ),
    ),
    [],
  );
});

test('rejects the legacy suffix even if the filename matches the class', () => {
  assert.deepEqual(
    lint(component('UserProfileComponent'), 'user-profile-component.ts').map((m) => m.messageId),
    ['suffix'],
  );
});

test('rejects a mismatched filename', () => {
  assert.deepEqual(
    lint(component('UserProfile'), 'profile.ts').map((m) => m.messageId),
    ['filename'],
  );
});

test('rejects generic and non-colocated resource names', () => {
  assert.deepEqual(
    lint(
      component('UserProfile', "{templateUrl: './profile.html', styleUrls: ['./styles.scss', '../user-profile.scss']}"),
    ).map((m) => m.messageId),
    ['resource', 'resource', 'resource'],
  );
});

test('recognizes Angular aliases and ignores unrelated decorators', () => {
  assert.deepEqual(lint("import {Component as View} from '@angular/core'; @View({}) class UserProfile {}"), []);
  assert.deepEqual(lint("import * as ng from '@angular/core'; @ng.Component({}) class UserProfile {}"), []);
  assert.deepEqual(lint("import {Component} from 'another-library'; @Component({}) class LegacyComponent {}"), []);
});

test('allows private, public and protected fields in their initialization order', () => {
  assert.deepEqual(
    lint(
      'class Example { private first = 1; public second = this.first; protected third = this.second; constructor() {} protected run() {} private stop() {} }',
    ),
    [],
  );
});

test('rejects fields below methods without offering an unsafe automatic reorder', () => {
  const messages = lint('class Example { run() {} protected value = 1; }');
  assert.equal(messages.length, 1);
  assert.equal(messages[0].ruleId, 'local-angular/member-order');
  assert.equal(messages[0].fix, undefined);
});

test('keeps arrow-function properties with fields without sorting their initializers', () => {
  assert.deepEqual(
    lint(
      'class Example { private callback = () => this.value; protected value = 1; public handler = this.callback; constructor() {} run() {} }',
    ),
    [],
  );
});

test('requires constructors before methods, but leaves interfaces alone', () => {
  assert.deepEqual(
    lint('class Example { run() {} constructor() {} }').map((m) => m.messageId),
    ['order'],
  );
  assert.deepEqual(lint('interface Example { run(): void; value: number; }'), []);
});
