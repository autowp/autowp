import {ChangeDetectionStrategy, Component, inject, OnInit} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {PageEnvService} from '@services/page-env.service';

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
    stream: () => this.#attrsService.getAttributes$(null, null),
  });

  protected readonly zonesResource = rxResource({
    stream: () => this.#attrsService.zones$,
  });

  ngOnInit(): void {
    this.#pageEnv.set({
      layout: {isAdminPage: true},
      pageId: 100,
    });
  }
}
