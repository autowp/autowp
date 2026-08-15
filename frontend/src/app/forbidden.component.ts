import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject, RESPONSE_INIT} from '@angular/core';

@Component({
  selector: 'app-forbidden',
  standalone: true,
  template: '<h2 i18n>Access denied</h2>',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ForbiddenComponent implements OnInit {
  readonly #response = inject(RESPONSE_INIT);

  ngOnInit(): void {
    if (this.#response) {
      this.#response.status = 403;
      this.#response.statusText = 'Forbidden';
    }
  }
}
