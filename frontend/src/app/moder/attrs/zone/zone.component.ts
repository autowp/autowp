import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {AttrZone, AttrZoneAttributesRequest} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {PageEnvService} from '@services/page-env.service';
import {debounceTime, distinctUntilChanged, EMPTY, map, Observable, of, shareReplay, switchMap, tap} from 'rxjs';

import {APIAttrsService, AttrAttributeTreeItem} from '../../../api/attrs/attrs.service';
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
    debounceTime(10),
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
        pageId: 142,
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
      for (const item of zoneAttributes.items ? zoneAttributes.items : []) {
        zoneAttribute[item.attributeId] = true;
      }
      return zoneAttribute;
    }),
  );
}
