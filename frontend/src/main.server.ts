import type {BootstrapContext} from '@angular/platform-browser';

import {provideZonelessChangeDetection} from '@angular/core';
import {bootstrapApplication} from '@angular/platform-browser';
import '@angular/localize/init';

import {AppComponent} from './app/app.component';
import {config} from './app/app.config.server';

const bootstrap = (context: BootstrapContext) =>
  bootstrapApplication(
    AppComponent,
    {...config, providers: [provideZonelessChangeDetection(), ...config.providers]},
    context,
  );

export default bootstrap;
