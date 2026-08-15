import type {AttrAttribute, AttrAttributeType, AttrListOptionsResponse, AttrZone} from '@grpc/spec.pb';
import type {Observable} from 'rxjs';

import {inject, Service} from '@angular/core';
import {AttrListOptionsRequest, ListAttributesRequest} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {getAttrsTranslation} from '@utils/translations';
import {map, of, shareReplay, switchMap} from 'rxjs';

export interface AttrAttributeTreeItem extends AttrAttribute.AsObject {
  childs: AttrAttributeTreeItem[];
}

function toTree(items: AttrAttribute[], parentID: string): AttrAttributeTreeItem[] {
  return items
    .filter((i) => i.parentId === parentID)
    .map((i) => {
      const o = i;
      return {...o.toObject(), childs: toTree(items, o.id)};
    });
}

@Service()
export class APIAttrsService {
  readonly #attrsClient = inject(AttrsClient);

  readonly #attrs$: Observable<AttrAttribute[]> = this.#attrsClient.listAttributes(new ListAttributesRequest()).pipe(
    map((response) => response.items ?? []),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public readonly attributeTypes$: Observable<AttrAttributeType[]> = this.#attrsClient
    .getAttributeTypes(new Empty())
    .pipe(
      map((response) => response.items ?? []),
      shareReplay({bufferSize: 1, refCount: false}),
    );

  public readonly zones$: Observable<AttrZone[]> = this.#attrsClient.getZones(new Empty()).pipe(
    map((response) => response.items ?? []),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public getZone$(id: string): Observable<AttrZone | null> {
    return this.zones$.pipe(
      map((zones) => {
        for (const zone of zones) {
          if (zone.id === id) {
            return zone;
          }
        }
        return null;
      }),
    );
  }

  public getAttribute$(id: string): Observable<AttrAttribute | undefined> {
    return this.#attrs$.pipe(map((attrs) => attrs.find((attr) => attr.id === id)));
  }

  public getAttributes$(zoneID: null | string, parentID: null | string): Observable<AttrAttributeTreeItem[]> {
    return this.#attrsClient
      .listAttributes(new ListAttributesRequest({parentId: parentID ?? undefined, zoneId: zoneID ?? undefined}))
      .pipe(map((response) => toTree(response.items ?? [], parentID ?? '0')));
  }

  public getListOptions$(attributeId: string | undefined): Observable<AttrListOptionsResponse> {
    return this.#attrsClient.getListOptions(new AttrListOptionsRequest({attributeId}));
  }

  public getPath$(id: string): Observable<string[]> {
    return this.getAttribute$(id).pipe(
      switchMap((attr) => {
        if (!attr) {
          return of([]);
        }

        return this.getPath$(attr.parentId).pipe(
          map((parentPath) => parentPath.concat(getAttrsTranslation(attr.name))),
        );
      }),
    );
  }
}
