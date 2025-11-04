import {ModuleWithProviders, NgModule, Provider} from '@angular/core';
import {GRPC_CLIENT_FACTORY} from '@ngx-grpc/core';

import {NgGrpcWebClientFactory, NgGrpcWebClientSettings} from './grpc-web-client';
import {NG_GRPC_WEB_CLIENT_DEFAULT_SETTINGS} from './tokens';

export interface NgGrpcWebClientRootOptions {
  settings?: NgGrpcWebClientSettings;
}

@NgModule()
export class NgGrpcWebClientModule {
  /**
   * Create NgGrpcWebClientModule for using in AppModule (application root module)
   * You can provide the options here instead of injecting corresponding tokens separately
   */
  public static forRoot(options?: NgGrpcWebClientRootOptions): ModuleWithProviders<NgGrpcWebClientModule> {
    const providers: Provider[] = [{provide: GRPC_CLIENT_FACTORY, useClass: NgGrpcWebClientFactory}];

    if (options?.settings) {
      providers.push({provide: NG_GRPC_WEB_CLIENT_DEFAULT_SETTINGS, useValue: options.settings});
    }

    return {ngModule: NgGrpcWebClientModule, providers};
  }
}
