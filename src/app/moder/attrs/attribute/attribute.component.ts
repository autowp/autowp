import {AsyncPipe} from '@angular/common';
import {ChangeDetectionStrategy, Component, inject} from '@angular/core';
import {ActivatedRoute, Router, RouterLink} from '@angular/router';
import {AttrAttribute} from '@grpc/spec.pb';
import {PageEnvService} from '@services/page-env.service';
import {getAttrListOptionsTranslation, getAttrsTranslation, getUnitNameTranslation} from '@utils/translations';
import {combineLatest, EMPTY, Observable, of} from 'rxjs';
import {distinctUntilChanged, map, shareReplay, switchMap, tap} from 'rxjs/operators';

import {APIAttrsService} from '../../../api/attrs/attrs.service';

@Component({
  selector: 'app-moder-attrs-attribute',
  imports: [RouterLink, AsyncPipe],
  templateUrl: './attribute.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class ModerAttrsAttributeComponent {
  readonly #attrsService = inject(APIAttrsService);
  readonly #route = inject(ActivatedRoute);
  readonly #pageEnv = inject(PageEnvService);
  readonly #router = inject(Router);

  readonly #attributeID$ = this.#route.paramMap.pipe(
    map((params) => params.get('id')),
    distinctUntilChanged(),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly attribute$: Observable<AttrAttribute> = this.#attributeID$.pipe(
    switchMap((id) => (id ? this.#attrsService.getAttribute$(id) : of(null))),
    switchMap((attribute) => {
      if (!attribute) {
        this.#router.navigate(['/error-404'], {
          skipLocationChange: true,
        });
        return EMPTY;
      }
      return of(attribute);
    }),
    tap((attribute) => {
      this.#pageEnv.set({
        layout: {isAdminPage: true},
        pageId: 101,
        title: getAttrsTranslation(attribute.name),
      });
    }),
    shareReplay({bufferSize: 1, refCount: false}),
  );

  protected readonly attributes$ = this.#attributeID$.pipe(
    switchMap((attributeID) => this.#attrsService.getAttributes$(null, attributeID)),
  );

  protected readonly listOptions$: Observable<string[]> = this.#attributeID$.pipe(
    switchMap((attributeID) => (attributeID ? this.#attrsService.getListOptions$(attributeID) : EMPTY)),
    map((response) => (response.items ? response.items : []).map((l) => getAttrListOptionsTranslation(l.name))),
  );

  protected readonly typeOption$ = combineLatest([this.attribute$, this.#attrsService.attributeTypes$]).pipe(
    map(([attribute, options]) => options.find((o) => o.id === attribute.typeId)),
  );

  protected readonly typeMap$: Observable<Record<number, string>> = this.#attrsService.attributeTypes$.pipe(
    map((types) => {
      const typeMap: Record<string, string> = {};
      for (const item of types) {
        typeMap[item.id] = item.name;
      }
      return typeMap;
    }),
  );

  protected getUnitNameTranslation(id: string): string {
    return getUnitNameTranslation(id);
  }

  protected getAttrsTranslation(id: string): string {
    return getAttrsTranslation(id);
  }
}
