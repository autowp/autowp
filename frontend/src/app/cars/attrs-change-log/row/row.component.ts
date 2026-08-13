import {AsyncPipe, DatePipe} from '@angular/common';
import {
  ChangeDetectionStrategy,
  Component,
  computed,
  inject,
  Injector,
  input,
  OnInit,
  ResourceRef,
} from '@angular/core';
import {rxResource} from '@angular/core/rxjs-interop';
import {RouterLink} from '@angular/router';
import {AttrUserValue, Item, User} from '@grpc/spec.pb';
import {NgbTooltip} from '@ng-bootstrap/ng-bootstrap';
import {AuthService, Role} from '@services/auth.service';
import {UserService} from '@services/user';
import {TimeAgoPipe} from '@utils/time-ago.pipe';
import {timestampToDate} from '@utils/timestamp';
import {getUnitAbbrTranslation} from '@utils/translations';
import {map} from 'rxjs/operators';

import {APIAttrsService} from '../../../api/attrs/attrs.service';
import {UserComponent} from '../../../user/user/user.component';
import {CarsAttrsChangeLogItemCacheService} from '../item-cache.service';

@Component({
  // Attribute selector on tr, not an element selector - a custom element between <tbody> and its
  // row content would get foster-parented out of the table entirely by the HTML parser (anything
  // other than the table-structure tags in "in table" insertion mode is moved to before the
  // table), breaking layout and SSR/hydration DOM shape. This way the host is a real <tr> already
  // in the right position, matching how e.g. Angular Material's mat-row/mat-cell do it.
  // eslint-disable-next-line @angular-eslint/component-selector -- attribute selector needed on tr, see above
  selector: 'tr[app-cars-attrs-change-log-row]',
  imports: [RouterLink, UserComponent, AsyncPipe, DatePipe, TimeAgoPipe, NgbTooltip],
  templateUrl: './row.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class CarsAttrsChangeLogRowComponent implements OnInit {
  readonly #itemCache = inject(CarsAttrsChangeLogItemCacheService);
  readonly #attrsService = inject(APIAttrsService);
  readonly #userService = inject(UserService);
  readonly #auth = inject(AuthService);
  readonly #injector = inject(Injector);

  // A required input isn't readable at construction time, so (like CommentsComponent) these
  // resources are built in ngOnInit() with an explicit injector rather than as field initializers.
  readonly userValue = input.required<AttrUserValue>();

  protected readonly isModer$ = this.#auth.hasRole$(Role.MODER);
  protected readonly date = computed(() => timestampToDate(this.userValue().updateTime));

  protected itemResource!: ResourceRef<Item | undefined>;
  protected pathResource!: ResourceRef<string[] | undefined>;
  protected unitAbbrResource!: ResourceRef<null | string | undefined>;
  protected userResource!: ResourceRef<null | undefined | User>;

  ngOnInit(): void {
    this.itemResource = rxResource({
      id: `cars-attrs-change-log-row-item-${this.userValue().itemId}`,
      injector: this.#injector,
      params: () => this.userValue().itemId,
      stream: ({params: id}) => this.#itemCache.getItem$(id),
    });

    this.pathResource = rxResource({
      id: `cars-attrs-change-log-row-path-${this.userValue().attributeId}`,
      injector: this.#injector,
      params: () => this.userValue().attributeId,
      stream: ({params: attributeId}) => this.#attrsService.getPath$(attributeId),
    });

    this.unitAbbrResource = rxResource({
      id: `cars-attrs-change-log-row-unit-${this.userValue().attributeId}`,
      injector: this.#injector,
      params: () => this.userValue().attributeId,
      stream: ({params: attributeId}) =>
        this.#attrsService
          .getAttribute$(attributeId)
          .pipe(map((attr) => (attr?.unitId ? getUnitAbbrTranslation(attr.unitId) : null))),
    });

    this.userResource = rxResource({
      id: `cars-attrs-change-log-row-user-${this.userValue().userId}`,
      injector: this.#injector,
      params: () => this.userValue().userId,
      stream: ({params: userId}) => this.#userService.getUser$(userId),
    });
  }
}
