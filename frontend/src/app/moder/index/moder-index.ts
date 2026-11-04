import type {AfterViewInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {RouterLink} from '@angular/router';
import {ItemType} from '@grpc/spec.pb';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';

@Component({
  selector: 'app-moder-index',
  imports: [RouterLink],
  templateUrl: './moder-index.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerIndex implements AfterViewInit {
  readonly #pageEnv = inject(PageEnvService);

  protected readonly ItemType = ItemType;

  ngAfterViewInit() {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER,
    });
  }
}
