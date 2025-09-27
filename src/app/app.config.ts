import {DecimalPipe} from '@angular/common';
import {provideHttpClient, withInterceptors} from '@angular/common/http';
import {ApplicationConfig, enableProdMode, importProvidersFrom} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {BrowserModule} from '@angular/platform-browser';
import {provideRouter, withInMemoryScrolling} from '@angular/router';
import {environment} from '@environment/environment';
import {NgbCollapseModule, NgbDropdownModule, NgbModule, NgbTooltipModule} from '@ng-bootstrap/ng-bootstrap';
import {GRPC_INTERCEPTORS, GrpcCoreModule} from '@ngx-grpc/core';
import {GrpcWebClientModule} from '@ngx-grpc/grpc-web-client';
import {provideApi} from '@rest/provide-api';
import {authInterceptor$, GrpcAuthInterceptor, GrpcLogInterceptor} from '@services/api.service';
import {AuthService} from '@services/auth.service';
import {AppContactsService} from '@services/contacts';
import {ContentLanguageService} from '@services/content-language';
import {IpService} from '@services/ip';
import {ItemService} from '@services/item';
import {LanguageService} from '@services/language';
import {MessageService} from '@services/message';
import {PageService} from '@services/page';
import {PageEnvService} from '@services/page-env.service';
import {PictureService} from '@services/picture';
import {PictureModerVoteService} from '@services/picture-moder-vote';
import {ReCaptchaService} from '@services/recaptcha';
import {SpecService} from '@services/spec';
import {TimezoneService} from '@services/timezone';
import {UserService} from '@services/user';
import {VehicleTypeService} from '@services/vehicle-type';
import {Angulartics2Module} from 'angulartics2';
import {AutoRefreshTokenService, provideKeycloak, UserActivityService, withAutoRefreshToken} from 'keycloak-angular';
import {provideMonacoEditor} from 'ngx-monaco-editor-v2';
import {NgPipesModule} from 'ngx-pipes';

import {routes} from './app.routes';

if (environment.production) {
  enableProdMode();
}

export const appConfig: ApplicationConfig = {
  providers: [
    provideMonacoEditor(),
    importProvidersFrom(
      BrowserModule,
      FormsModule,
      NgPipesModule,
      NgbTooltipModule,
      NgbCollapseModule,
      NgbDropdownModule,
      GrpcCoreModule.forRoot(),
      GrpcWebClientModule.forRoot({
        settings: {host: environment.grpcHost},
      }),
      Angulartics2Module.forRoot(),
      NgbModule,
    ),
    {multi: true, provide: GRPC_INTERCEPTORS, useClass: GrpcLogInterceptor},
    {multi: true, provide: GRPC_INTERCEPTORS, useClass: GrpcAuthInterceptor},
    provideKeycloak({
      config: environment.keycloak,
      features: [
        withAutoRefreshToken({
          onInactivityTimeout: 'none',
          sessionTimeout: 60000,
        }),
      ],
      initOptions: {
        enableLogging: !environment.production,
        onLoad: 'check-sso',
        silentCheckSsoRedirectUri: window.location.origin + '/assets/silent-check-sso.html',
      },
    }),
    AutoRefreshTokenService,
    UserActivityService,
    AuthService,
    PictureService,
    ItemService,
    ReCaptchaService,
    MessageService,
    PageService,
    UserService,
    DecimalPipe,
    PictureModerVoteService,
    VehicleTypeService,
    SpecService,
    AppContactsService,
    PageEnvService,
    ContentLanguageService,
    LanguageService,
    TimezoneService,
    IpService,
    provideRouter(
      routes,
      withInMemoryScrolling({
        scrollPositionRestoration: 'enabled',
      }),
    ),
    provideHttpClient(withInterceptors([authInterceptor$])),
    provideApi({basePath: environment.apiUrl}),
  ],
};
