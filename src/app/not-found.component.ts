import {ChangeDetectionStrategy, Component} from '@angular/core';

@Component({
  selector: 'app-page-not-found',
  standalone: true,
  template: '<h2 i18n>Page not found</h2>',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PageNotFoundComponent {}
