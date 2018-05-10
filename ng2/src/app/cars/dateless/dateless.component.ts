import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import { ItemService, APIItem } from '../../services/item';
import Notify from '../../notify';
import { Subscription } from 'rxjs';
import { ActivatedRoute } from '@angular/router';

@Component({
  selector: 'app-cars-deteless',
  templateUrl: './dateless.component.html'
})
@Injectable()
export class CarsDatelessComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public items: APIItem[] = [];
  public paginator: APIPaginator;

  constructor(
    private http: HttpClient,
    private itemService: ItemService,
    private route: ActivatedRoute
  ) {
    /*this.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: false
      },
      name: 'page/103/name',
      pageId: 1
    });*/
  }

  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe(params => {
      this.itemService
        .getItems({
          dateless: true,
          fields: [
            'name_html,name_default,description,has_text,produced',
            'design,engine_vehicles',
            'url,spec_editor_url,specs_url,more_pictures_url',
            'categories.url,categories.name_html,twins_groups',
            'preview_pictures.picture.thumb_medium,childs_count,total_pictures'
          ].join(','),
          order: 'age',
          page: params.page,
          limit: 10
        })
        .subscribe(
          result => {
            this.items = result.items;
            this.paginator = result.paginator;
          },
          response => {
            Notify.response(response);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
