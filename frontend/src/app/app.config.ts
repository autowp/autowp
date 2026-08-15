import type {ApplicationConfig, EnvironmentProviders, Provider} from '@angular/core';
import type {ProvideKeycloakOptions} from 'keycloak-angular';

import {DecimalPipe, isPlatformBrowser} from '@angular/common';
import {provideHttpClient, withInterceptors} from '@angular/common/http';
import {
  enableProdMode,
  EnvironmentInjector,
  importProvidersFrom,
  inject,
  makeEnvironmentProviders,
  PLATFORM_ID,
  provideAppInitializer,
  runInInjectionContext,
} from '@angular/core';
import {FormsModule} from '@angular/forms';
import {
  BrowserModule,
  provideClientHydration,
  withHttpTransferCacheOptions,
  withI18nSupport,
} from '@angular/platform-browser';
import {provideRouter, withInMemoryScrolling} from '@angular/router';
import {environment} from '@environment/environment';
import {
  NgbCollapseModule,
  NgbDropdownModule,
  NgbModule,
  NgbToastConfig,
  NgbTooltipModule,
} from '@ng-bootstrap/ng-bootstrap';
import {GRPC_INTERCEPTORS, GrpcCoreModule} from '@ngx-grpc/core';
import {authInterceptor$, GrpcLogInterceptor} from '@services/api.service';
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
import {
  CONTENT_TYPE_HEADER,
  GRPC_MESSAGE_HEADER,
  GRPC_STATUS_DETAILS_BIN_HEADER,
  GRPC_STATUS_HEADER,
  provideGrpcWebClient,
} from 'grpc-web-client/grpc-web-client';
import {
  AutoRefreshTokenService,
  createKeycloakSignal,
  KEYCLOAK_EVENT_SIGNAL,
  UserActivityService,
  withAutoRefreshToken,
} from 'keycloak-angular';
import Keycloak from 'keycloak-js';
import {provideMonacoEditor} from 'ngx-monaco-editor-v2';
import {NgPipesModule} from 'ngx-pipes';

import {routes} from './app.routes';

if (environment.production) {
  enableProdMode();
}

const provideKeycloakInAppInitializer = (
  keycloak: Keycloak,
  options: ProvideKeycloakOptions,
  // eslint-disable-next-line sonarjs/function-return-type
): EnvironmentProviders | Provider[] => {
  const {initOptions, features = []} = options;

  if (!initOptions) {
    return [] as Provider[];
  }

  return provideAppInitializer(async () => {
    const platform = inject(PLATFORM_ID);

    // 👇 browser guard: only init keycloak in the browser
    if (isPlatformBrowser(platform)) {
      const injector = inject(EnvironmentInjector);
      runInInjectionContext(injector, () => {
        features.forEach((feature) => {
          feature.configure();
        });
      });

      await keycloak.init(initOptions).catch((error: unknown) => {
        console.error('Keycloak initialization failed', error);
      });
    } else {
      console.log('Keycloak initialization skipped on server side');
    }
  });
};

export function provideKeycloakSSR(options: ProvideKeycloakOptions): EnvironmentProviders {
  const keycloak = new Keycloak(options.config);

  const providers = options.providers ?? [];
  const keycloakSignal = createKeycloakSignal(keycloak);

  return makeEnvironmentProviders([
    {
      provide: KEYCLOAK_EVENT_SIGNAL,
      useValue: keycloakSignal,
    },
    {
      provide: Keycloak,
      useValue: keycloak,
    },
    ...providers,
    provideKeycloakInAppInitializer(keycloak, options),
  ]);
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
      Angulartics2Module.forRoot(),
      NgbModule,
    ),
    provideGrpcWebClient({
      settings: {host: environment.grpcHost},
    }),
    {multi: true, provide: GRPC_INTERCEPTORS, useClass: GrpcLogInterceptor},
    provideKeycloakSSR({
      config: environment.keycloak,
      features: [
        withAutoRefreshToken({
          onInactivityTimeout: 'none',
          sessionTimeout: 60000,
        }),
      ],
      initOptions: {
        enableLogging: false, // !environment.production,
        onLoad: 'check-sso',
        silentCheckSsoRedirectUri:
          // eslint-disable-next-line no-restricted-globals
          (typeof window !== 'undefined' ? window.location.origin : '') + '/assets/silent-check-sso.html',
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
    provideClientHydration(
      withI18nSupport(),
      withHttpTransferCacheOptions({
        includeHeaders: [CONTENT_TYPE_HEADER, GRPC_STATUS_HEADER, GRPC_MESSAGE_HEADER, GRPC_STATUS_DETAILS_BIN_HEADER],
        includePostRequests: true,
        includeRequestsWithAuthHeaders: false,
      }),
    ),
    provideAppInitializer(() => {
      const platform = inject(PLATFORM_ID);
      const config = inject(NgbToastConfig);

      config.animation = isPlatformBrowser(platform);
    }),
  ],
};
