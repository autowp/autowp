import type {AttrZone} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {AttrZoneAttributesRequest} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {PageId} from '@services/page-id';
import {distinctUntilChanged, EMPTY, map, of, shareReplay, switchMap, tap} from 'rxjs';

import type {AttrAttributeTreeItem} from '../../../api/attrs/attrs.service';

import {APIAttrsService} from '../../../api/attrs/attrs.service';
import {ModerAttrsZoneAttributeListComponent} from './attribute-list/attribute-list.component';

@Component({
  selector: 'app-moder-attrs-zone',
  imports: [RouterLink, ModerAttrsZoneAttributeListComponent, AsyncPipe],
  templateUrl: './zone.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerAttrsZoneComponent {
  readonly #attrsService = inject(APIAttrsService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #attrsClient = inject(AttrsClient);
  readonly #router = inject(Router);

  readonly #zoneID$ = this.#route.paramMap.pipe(
    map((params) => params.get('id')),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly zone$: Observable<AttrZone> = this.#zoneID$.pipe(
    switchMap((id) => (id ? this.#attrsService.getZone$(id) : of(null))),
    switchMap((zone) => {
      if (!zone) {
        void this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(zone);
    }),
    tap((zone) => {
      this.#pageEnv.set({
        layout: {isAdminPage: true},
        pageId: PageId.MODER_ATTRS_ZONE,
        title: zone.name,
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly attributes$: Observable<AttrAttributeTreeItem[]> = this.#attrsService.getAttributes$(null, null);

  protected readonly zoneAttributes$ = this.#zoneID$.pipe(
    switchMap((zoneID) =>
      zoneID ? this.#attrsClient.getZoneAttributes(new AttrZoneAttributesRequest({zoneId: zoneID})) : EMPTY,
    ),
    map((zoneAttributes) => {
      const zoneAttribute: Record<string, boolean> = {};
      for (const item of zoneAttributes.items ?? []) {
        zoneAttribute[item.attributeId] = true;
      }
      return zoneAttribute;
    }),
  );
}
