import type {ApplicationConfig} from '@angular/core';

import {HTTP_TRANSFER_CACHE_ORIGIN_MAP} from '@angular/common/http';
import {inject, mergeApplicationConfig, REQUEST} from '@angular/core';
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
    {
      // Lets the HttpTransferCache recognize SSR requests to environment.ssrGrpcHost (an
      // intra-pod address) and browser requests to the public site origin as the same request,
      // so hydration reuses the SSR-fetched gRPC responses instead of re-fetching them.
      provide: HTTP_TRANSFER_CACHE_ORIGIN_MAP,
      useFactory: () => {
        const host = inject(REQUEST)?.headers.get('host');
        return host ? {[environment.ssrGrpcHost]: `https://${host}`} : {};
      },
    },
  ],
};

export const config = mergeApplicationConfig(appConfig, serverConfig);
