import {inject, Injectable} from '@angular/core';
import {
  AttrAttribute,
  AttrAttributesRequest,
  AttrAttributeType,
  AttrListOptionsRequest,
  AttrListOptionsResponse,
  AttrZone,
} from '@grpc/spec.pb';
import {AttrsClient} from '@grpc/spec.pbsc';
import {Empty} from '@ngx-grpc/well-known-types';
import {getAttrsTranslation} from '@utils/translations';
import {Observable, of} from 'rxjs';
import {map, shareReplay, switchMap} from 'rxjs/operators';

export interface APIAttrUnit {
  abbr: string;
  id: number;
  name: string;
}

export interface AttrAttributeTreeItem extends AttrAttribute.AsObject {
  childs: AttrAttributeTreeItem[];
}

function toTree(items: AttrAttribute[], parentID: string): AttrAttributeTreeItem[] {
  return items
    .filter((i) => i.parentId === parentID)
    .map((i) => {
      const o = i.toObject();
      return {...o, childs: toTree(items, o.id)};
    });
}

@Injectable({
  providedIn: 'root',
})
export class APIAttrsService {
  readonly #attrsClient = inject(AttrsClient);

  readonly #attrs$ = this.#attrsClient.getAttributes(new AttrAttributesRequest()).pipe(
    map((response) => response.items),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public readonly attributeTypes$: Observable<AttrAttributeType[]> = this.#attrsClient
    .getAttributeTypes(new Empty())
    .pipe(
      map((response) => (response.items ? response.items : [])),
      shareReplay({bufferSize: 1, refCount: false}),
    );

  public readonly zones$: Observable<AttrZone[]> = this.#attrsClient.getZones(new Empty()).pipe(
    map((response) => (response.items ? response.items : [])),
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
    return this.#attrs$.pipe(map((attrs) => attrs?.find((attr) => attr.id === id)));
  }

  public getAttributes$(zoneID: null | string, parentID: null | string): Observable<AttrAttributeTreeItem[]> {
    return this.#attrsClient
      .getAttributes(
        new AttrAttributesRequest({parentId: parentID ? parentID : undefined, zoneId: zoneID ? zoneID : undefined}),
      )
      .pipe(map((response) => toTree(response.items ? response.items : [], parentID ? parentID : '0')));
  }

  public getListOptions$(attributeID: string | undefined): Observable<AttrListOptionsResponse> {
    return this.#attrsClient.getListOptions(new AttrListOptionsRequest({attributeId: attributeID}));
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
