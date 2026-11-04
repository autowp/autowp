/// <reference types="@angular/localize" />

import {provideZonelessChangeDetection} from '@angular/core';
import {bootstrapApplication} from '@angular/platform-browser';

import {App} from './app/app';
import {appConfig} from './app/app.config';

bootstrapApplication(App, {
  ...appConfig,
  providers: [provideZonelessChangeDetection(), ...appConfig.providers],
}).catch((err: unknown) => {
  console.error(err);
});
