import {ApplicationConfig, mergeApplicationConfig} from '@angular/core';
import {provideServerRendering, withRoutes} from '@angular/ssr';
import {environment} from '@environment/environment';

import {provideGrpcWebClient} from '../grpc-web-client/grpc-web-client';
import {appConfig} from './app.config';
import {serverRoutes} from './app.routes.server';

const serverConfig: ApplicationConfig = {
  providers: [
    provideServerRendering(withRoutes(serverRoutes)),
    provideGrpcWebClient({
      settings: {host: environment.ssrGrpcHost},
    }),
  ],
};

export const config = mergeApplicationConfig(appConfig, serverConfig);
