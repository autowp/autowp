import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage} from 'app/grpc';

import {APIAttrsService} from '../../api/attrs/attrs.service';
import {ModerAttrsAttributeList} from './attribute-list/moder-attrs-attribute-list';

@Component({
  selector: 'app-moder-attrs',
  imports: [RouterLink, ModerAttrsAttributeList],
  templateUrl: './moder-attrs.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerAttrs implements OnInit {
  readonly #attrsService = inject(APIAttrsService);
  readonly #pageEnv = inject(PageEnvService);

  protected readonly attributesResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'moder-attrs-attributes',
    stream: () => this.#attrsService.getAttributes$(null, null),
  });

  protected readonly zonesResource = rxResource({
    // Seeds status as resolved from TransferState on hydration, avoiding a loading-state blink.
    id: 'moder-attrs-zones',
    stream: () => this.#attrsService.zones$,
  });

  protected readonly errorMessage = errorMessage;

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_ATTRS,
    });
  }
}
