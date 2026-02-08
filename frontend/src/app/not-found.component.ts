import {ChangeDetectionStrategy, Component, inject, OnInit, RESPONSE_INIT} from '@angular/core';

@Component({
  selector: 'app-page-not-found',
  standalone: true,
  template: '<h2 i18n>Page not found</h2>',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PageNotFoundComponent implements OnInit {
  readonly #response = inject(RESPONSE_INIT);

  ngOnInit(): void {
    if (this.#response) {
      this.#response.status = 404;
      this.#response.statusText = 'Not Found';
    }
  }
}
