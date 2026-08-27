import type {OnInit} from '@angular/core';

import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {errorMessage} from 'app/grpc';

import {APIAttrsService} from '../../api/attrs/attrs.service';
import {ModerAttrsAttributeListComponent} from './attribute-list/attribute-list.component';

@Component({
  selector: 'app-moder-attrs',
  imports: [RouterLink, ModerAttrsAttributeListComponent],
  templateUrl: './attrs.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerAttrsComponent implements OnInit {
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

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: PageId.MODER_ATTRS,
    });
  }

  protected readonly errorMessage = errorMessage;
}
