import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../services/api.service';
import {
  VehicleTypeService,
  APIVehicleType
} from '../../services/vehicle-type';
import { SpecService, APISpec } from '../../services/spec';
import {
  ItemService,
  APIItem,
  GetItemsServiceOptions
} from '../../services/item';
import { ActivatedRoute, Router } from '@angular/router';
import {
  Subscription,
  combineLatest,
  BehaviorSubject,
  Observable,
  of,
  empty
} from 'rxjs';
import { PageEnvService } from '../../services/page-env.service';
import {
  tap,
  switchMap,
  distinctUntilChanged,
  debounceTime,
  catchError,
  map
} from 'rxjs/operators';
import { NgbTypeaheadSelectItemEvent } from '@ng-bootstrap/ng-bootstrap';

// Acl.inheritsRole('moder', 'unauthorized');

interface APIVehicleTypeInItems extends APIVehicleType {
  deep?: number;
}

interface APISpecInItems extends APISpec {
  deep?: number;
}

function toPlainVehicleType(
  options: APIVehicleTypeInItems[],
  deep: number
): any[] {
  const result: APIVehicleTypeInItems[] = [];
  for (const item of options) {
    item.deep = deep;
    result.push(item);
    for (const subitem of toPlainVehicleType(item.childs, deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

function toPlainSpec(options: APISpecInItems[], deep: number): any[] {
  const result: APISpecInItems[] = [];
  for (const item of options) {
    item.deep = deep;
    result.push(item);
    for (const subitem of toPlainSpec(item.childs, deep + 1)) {
      result.push(subitem);
    }
  }
  return result;
}

interface IFilter {
  name: string | null;
  name_exclude: string | null;
  item_type_id: number | null;
  vehicle_type_id: number | null;
  vehicle_childs_type_id: number | null;
  spec: any;
  no_parent: boolean;
  text: string | null;
  from_year: number | null;
  to_year: number | null;
  order: string;
}

const DEFAULT_ORDER = 'id_desc';

@Component({
  selector: 'app-items',
  templateUrl: './items.component.html'
})
@Injectable()
export class ModerItemsComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public listMode: boolean;

  public loading = 0;
  public items: APIItem[] = [];
  public paginator: APIPaginator;
  public vehicleTypeOptions: APIVehicleTypeInItems[] = [];
  public specOptions: APISpecInItems[] = [];
  public filter: IFilter = {
    name: null,
    name_exclude: null,
    item_type_id: null,
    vehicle_type_id: null,
    vehicle_childs_type_id: null,
    spec: null,
    no_parent: false,
    text: null,
    from_year: null,
    to_year: null,
    order: DEFAULT_ORDER
  };
  private vehicleTypeSub: Subscription;
  private specsSub: Subscription;
  private load$ = new BehaviorSubject<null>(null);

  public ancestorID: number;
  public ancestorQuery = '';
  public ancestorsDataSource: (text$: Observable<string>) => Observable<any[]>;

  constructor(
    private vehicleTypeService: VehicleTypeService,
    private specService: SpecService,
    private itemService: ItemService,
    private route: ActivatedRoute,
    private router: Router,
    private pageEnv: PageEnvService
  ) {
    this.ancestorsDataSource = (text$: Observable<string>) =>
      text$.pipe(
        debounceTime(200),
        switchMap(query => {
          if (query === '') {
            return of([]);
          }

          const params: GetItemsServiceOptions = {
            limit: 10,
            fields: 'name_text,name_html',
            id: 0,
            name: ''
          };
          if (query.substring(0, 1) === '#') {
            params.id = parseInt(query.substring(1), 10);
          } else {
            params.name = '%' + query + '%';
          }

          return this.itemService.getItems(params).pipe(
            catchError((err, caught) => {
              console.log(err, caught);
              return empty();
            }),
            map(response => response.items)
          );
        })
      );
  }

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/131/name',
          pageId: 131
        }),
      0
    );

    this.vehicleTypeSub = this.vehicleTypeService
      .getTypes()
      .subscribe(types => {
        this.vehicleTypeOptions = toPlainVehicleType(types, 0);
      });

    this.specsSub = this.specService.getSpecs().subscribe(types => {
      this.specOptions = toPlainSpec(types, 0);
    });

    this.querySub = combineLatest(
      this.route.queryParams.pipe(
        distinctUntilChanged(),
        debounceTime(30),
        tap(params => {
          this.filter = {
            name: params.name || null,
            name_exclude: params.name_exclude || null,
            item_type_id: parseInt(params.item_type_id, 10) || null,
            vehicle_type_id: params.vehicle_type_id || null,
            vehicle_childs_type_id:
              parseInt(params.vehicle_childs_type_id, 10) || null,
            spec: params.spec || null,
            no_parent: params.no_parent ? true : false,
            text: params.text || null,
            from_year: params.from_year || null,
            to_year: params.to_year || null,
            order: params.order || DEFAULT_ORDER
          };
          this.ancestorID = params.ancestor_id || null;
          this.listMode = !!params.list;
        })
      ),
      this.load$,
      (params, load) => params
    )
      .pipe(
        switchMap(params => {
          this.loading = 1;
          this.items = [];

          let fields = 'name_html';
          let limit = 500;
          if (!params.listMode) {
            fields = [
              'name_html,name_default,description,has_text,produced',
              'design,engine_vehicles',
              'url,spec_editor_url,specs_url,more_pictures_url',
              'categories.url,categories.name_html,twins_groups',
              'preview_pictures.picture.thumb_medium,childs_count,total_pictures'
            ].join(',');
            limit = 10;
          }

          return this.itemService.getItems({
            name: this.filter.name ? this.filter.name + '%' : null,
            name_exclude: this.filter.name_exclude
              ? this.filter.name_exclude + '%'
              : null,
            type_id: this.filter.item_type_id,
            vehicle_type_id: this.filter.vehicle_type_id,
            vehicle_childs_type_id: this.filter.vehicle_childs_type_id,
            spec: this.filter.spec,
            order: this.filter.order,
            no_parent: this.filter.no_parent ? true : null,
            text: this.filter.text ? this.filter.text : null,
            from_year: this.filter.from_year ? this.filter.from_year : null,
            to_year: this.filter.to_year ? this.filter.to_year : null,
            ancestor_id: this.ancestorID ? this.ancestorID : null,
            page: params.page,
            fields: fields,
            limit: limit
          });
        }),
        tap(() => (this.loading = 0))
      )
      .subscribe(response => {
        this.items = response.items;
        this.paginator = response.paginator;
      });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
    this.vehicleTypeSub.unsubscribe();
    this.specsSub.unsubscribe();
  }

  public ancestorFormatter(x: APIItem) {
    return x.name_text;
  }

  public ancestorOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        ancestor_id: e.item.id
      }
    });
  }

  public clearAncestor(): void {
    this.ancestorQuery = '';
    this.router.navigate([], {
      queryParamsHandling: 'merge',
      queryParams: {
        ancestor_id: null
      }
    });
  }

  public load() {
    this.load$.next(null);
  }
}
