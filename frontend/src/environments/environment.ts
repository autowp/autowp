// The file contents for the current environment will overwrite these during build.
// The build system defaults to the dev environment which uses `environment.ts`, but if you do
// `ng build --env=prod` then `environment.prod.ts` will be used instead.
// The list of which env maps to which file can be found in `.angular-cli.json`.

export const environment = {
  apiUrl: '',
  grpcHost: '',
  ssrGrpcHost: 'http://goautowp-serve:8080',
  keycloak: {
    clientId: 'frontend',
    realm: 'autowp',
    url: 'http://localhost:8081/',
  },
  languages: [
    {
      code: 'en',
      flag: 'flag-icon flag-icon-gb',
      hostname: 'en.localhost',
      locale: 'en-GB',
      name: 'English',
    },
    {
      code: 'zh',
      flag: 'flag-icon flag-icon-cn',
      hostname: 'zh.localhost',
      locale: 'zh-CN',
      name: '中文',
    },
    {
      code: 'ru',
      flag: 'flag-icon flag-icon-ru',
      hostname: 'ru.localhost',
      locale: 'ru',
      name: 'Русский',
    },
    {
      code: 'pt-br',
      flag: 'flag-icon flag-icon-br',
      hostname: 'br.localhost',
      locale: 'pt-BR',
      name: 'Português brasileiro',
    },
    {
      code: 'fr',
      flag: 'flag-icon flag-icon-fr',
      hostname: 'fr.localhost',
      locale: 'fr',
      name: 'Français',
    },
    {
      code: 'be',
      flag: 'flag-icon flag-icon-by',
      hostname: 'be.localhost',
      locale: 'be',
      name: 'Беларуская',
    },
    {
      code: 'uk',
      flag: 'flag-icon flag-icon-ua',
      hostname: 'uk.localhost',
      locale: 'uk',
      name: 'Українська',
    },
    {
      code: 'es',
      flag: 'flag-icon flag-icon-es',
      hostname: 'es.localhost',
      locale: 'es-ES',
      name: 'Español',
    },
    {
      code: 'it',
      flag: 'flag-icon flag-icon-it',
      hostname: 'it.localhost',
      locale: 'it',
      name: 'Italiano',
    },
    {
      code: 'he',
      flag: 'flag-icon flag-icon-he',
      hostname: 'he.localhost',
      locale: 'he',
      name: 'עִברִית',
    },
  ],
  production: false,
};
