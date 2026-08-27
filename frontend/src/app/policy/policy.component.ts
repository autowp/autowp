import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';

@Component({
  selector: 'app-policy',
  imports: [RouterLink],
  templateUrl: './policy.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class PolicyComponent implements OnInit {
  readonly #pageEnv = inject(PageEnvService);

  ngOnInit(): void {
    this.#pageEnv.set({pageId: PageId.HOME});
  }
}
