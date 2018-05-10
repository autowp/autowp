import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import * as $ from 'jquery';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import { VehicleTypeService } from '../../services/vehicle-type';
import { SpecService } from '../../services/spec';
import { ItemService, GetItemsServiceOptions, APIItem } from '../../services/item';
import { ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';

// Acl.inheritsRole('moder', 'unauthorized');

function toPlain(options: any[], deep: number): any[] {
  const result: any[] = [];
  for (const item of options) {
    item.deep = deep;
    result.push(item);
    for (const subitem of toPlain(item.childs, deep + 1)) {
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
  ancestor_id: number | null;
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
  public vehicleTypeOptions: any[] = [];
  public specOptions: any[] = [];
  public page: number;
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
    order: DEFAULT_ORDER,
    ancestor_id: null
  };

  constructor(
    private http: HttpClient,
    private vehicleTypeService: VehicleTypeService,
    private specService: SpecService,
    private itemService: ItemService,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    /*$scope.pageEnv({
            layout: {
                isAdminPage: true,
                blankPage: false,
                needRight: false
            },
            name: 'page/131/name',
            pageId: 131
        });*/

    this.vehicleTypeService.getTypes().then(types => {
      this.vehicleTypeOptions = toPlain(types, 0);
    });

    this.specService.getSpecs().then(types => {
      this.specOptions = toPlain(types, 0);
    });

    /* const $itemIdElement = $($element[0]).find(':input[name=ancestor_id]');
    $itemIdElement.val(
      this.filter.ancestor_id ? '#' + this.filter.ancestor_id : ''
    );
    let itemIdLastValue = $itemIdElement.val();
    $itemIdElement
      .on('typeahead:select', (ev: any, item: any) => {
        itemIdLastValue = item.name_text;
        this.filter.ancestor_id = item.id;
        this.load();
      })
      .bind('change blur', (ev: any, item: any) => {
        const curValue = $(this).val();
        if (itemIdLastValue && !curValue) {
          this.filter.ancestor_id = null;
          this.load();
        }
        itemIdLastValue = curValue;
      })
      .typeahead(
        {},
        {
          display: (item: any) => {
            return item.name_text;
          },
          templates: {
            suggestion: (item: any) => {
              return $('<div class="tt-suggestion tt-selectable"></div>').html(
                item.name_html
              );
            }
          },
          source: (
            query: string,
            syncResults: Function,
            asyncResults: Function
          ) => {
            const params: GetItemsServiceOptions = {
              limit: 10,
              fields: 'name_text,name_html',
              id: 0,
              name: ''
            };
            if (query.substring(0, 1) === '#') {
              params.id = parseInt(query.substring(1), 10);
            } else {
              params.name = query + '%';
            }

            this.itemService.getItems(params).subscribe(response => {
              asyncResults(response.items);
            });
          }
        }
      );*/

    this.querySub = this.route.queryParams.subscribe(params => {
      this.filter = {
        name: params.name || null,
        name_exclude: params.name_exclude || null,
        item_type_id: parseInt(params.item_type_id, 10) || null,
        vehicle_type_id: params.vehicle_type_id || null,
        vehicle_childs_type_id: parseInt(params.vehicle_childs_type_id, 10) || null,
        spec: params.spec || null,
        no_parent: params.no_parent ? true : false,
        text: params.text || null,
        from_year: params.from_year || null,
        to_year: params.to_year || null,
        order: params.order || DEFAULT_ORDER,
        ancestor_id: params.ancestor_id || null
      };
      this.listMode = !!params.list;

      this.page = params.page;

      this.load();
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }

  public getStateParams() {
    return {
      name: this.filter.name ? this.filter.name : null,
      name_exclude: this.filter.name_exclude ? this.filter.name_exclude : null,
      item_type_id: this.filter.item_type_id,
      vehicle_type_id: this.filter.vehicle_type_id,
      vehicle_childs_type_id: this.filter.vehicle_childs_type_id,
      spec: this.filter.spec,
      order: this.filter.order === DEFAULT_ORDER ? null : this.filter.order,
      no_parent: this.filter.no_parent ? 1 : null,
      text: this.filter.text ? this.filter.text : null,
      from_year: this.filter.from_year ? this.filter.from_year : null,
      to_year: this.filter.to_year ? this.filter.to_year : null,
      ancestor_id: this.filter.ancestor_id ? this.filter.ancestor_id : null,
      page: this.page,
      list: this.listMode ? '1' : ''
    };
  }

  public load() {
    this.loading++;
    this.items = [];

    const stateParams = this.getStateParams();

    /*this.$state.go(this.$state.current.name, stateParams, {
      reload: false,
      location: 'replace',
      notify: false
    });*/

    let fields = 'name_html';
    let limit = 500;
    if (!this.listMode) {
      fields = [
        'name_html,name_default,description,has_text,produced',
        'design,engine_vehicles',
        'url,spec_editor_url,specs_url,more_pictures_url',
        'categories.url,categories.name_html,twins_groups',
        'preview_pictures.picture.thumb_medium,childs_count,total_pictures'
      ].join(',');
      limit = 10;
    }

    this.itemService
      .getItems({
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
        ancestor_id: this.filter.ancestor_id ? this.filter.ancestor_id : null,
        page: this.page,
        fields: fields,
        limit: limit
      })
      .subscribe(
        response => {
          this.items = response.items;
          this.paginator = response.paginator;
          this.loading--;
        },
        () => {
          this.loading--;
        }
      );
  }

  public setListModeEnabled(value: boolean) {
    this.listMode = value;
    if (value) {
      this.filter.order = 'name';
    }
    this.load();
  }
}
