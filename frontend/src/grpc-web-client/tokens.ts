import {InjectionToken} from '@angular/core';

import {NgGrpcWebClientSettings} from './grpc-web-client';

/**
 * Default configuration for grpc-web clients. Will be used for every GrpcWebClient unless service-specific config is provided
 *
 * Example:
 *
 * ```
 * providers: [
 *   { provide: NG_GRPC_WEB_CLIENT_DEFAULT_SETTINGS, useClass: { host: 'localhost:4321' } },
 * ]
 * ```
 */
export const NG_GRPC_WEB_CLIENT_DEFAULT_SETTINGS = new InjectionToken<NgGrpcWebClientSettings>(
  'BG_GRPC_WEB_CLIENT_DEFAULT_SETTINGS',
);
