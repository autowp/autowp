import {inject, Injectable} from '@angular/core';
import {AttrAttribute} from '@grpc/spec.pb';
import {AttrsService} from '@rest/api/attrs.service';
import {GoautowpAttrAttribute} from '@rest/model/goautowpAttrAttribute';
import {GoautowpAttrAttributeType} from '@rest/model/goautowpAttrAttributeType';
import {GoautowpAttrListOptionsResponse} from '@rest/model/goautowpAttrListOptionsResponse';
import {GoautowpAttrZone} from '@rest/model/goautowpAttrZone';
import {getAttrsTranslation} from '@utils/translations';
import {Observable, of} from 'rxjs';
import {map, shareReplay, switchMap} from 'rxjs/operators';

export interface AttrAttributeTreeItem extends AttrAttribute.AsObject {
  childs: AttrAttributeTreeItem[];
}

function toTree(items: GoautowpAttrAttribute[], parentID: string): AttrAttributeTreeItem[] {
  return items
    .filter((i) => i.parentId === parentID)
    .map((i) => {
      const o = i;
      return {...o, childs: toTree(items, o.id)};
    });
}

@Injectable({
  providedIn: 'root',
})
export class APIAttrsService {
  readonly #attrsService = inject(AttrsService);

  readonly #attrs$: Observable<GoautowpAttrAttribute[]> = this.#attrsService.attrsListAttributes({}).pipe(
    map((response) => response.items),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public readonly attributeTypes$: Observable<GoautowpAttrAttributeType[]> = this.#attrsService
    .attrsGetAttributeTypes()
    .pipe(
      map((response) => (response.items ? response.items : [])),
      shareReplay({bufferSize: 1, refCount: false}),
    );

  public readonly zones$: Observable<GoautowpAttrZone[]> = this.#attrsService.attrsGetZones().pipe(
    map((response) => (response.items ? response.items : [])),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  public getZone$(id: string): Observable<GoautowpAttrZone | null> {
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

  public getAttribute$(id: string): Observable<GoautowpAttrAttribute | undefined> {
    return this.#attrs$.pipe(map((attrs) => attrs?.find((attr) => attr.id === id)));
  }

  public getAttributes$(zoneID: null | string, parentID: null | string): Observable<AttrAttributeTreeItem[]> {
    return this.#attrsService
      .attrsListAttributes({parentId: parentID ?? undefined, zoneId: zoneID ?? undefined})
      .pipe(map((response) => toTree(response.items ?? [], parentID ? parentID : '0')));
  }

  public getListOptions$(attributeId: string | undefined): Observable<GoautowpAttrListOptionsResponse> {
    return this.#attrsService.attrsGetListOptions({attributeId});
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
