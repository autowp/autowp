import type {BootstrapContext} from '@angular/platform-browser';

import {provideZoneChangeDetection} from '@angular/core';
import {bootstrapApplication} from '@angular/platform-browser';
import '@angular/localize/init';

import {AppComponent} from './app/app.component';
import {config} from './app/app.config.server';

const bootstrap = (context: BootstrapContext) =>
  bootstrapApplication(
    AppComponent,
    {...config, providers: [provideZoneChangeDetection(), ...config.providers]},
    context,
  );

export default bootstrap;
