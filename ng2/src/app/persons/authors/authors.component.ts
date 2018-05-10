import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../services/api.service';
import { APIItem, ItemService } from '../../services/item';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-persons-authors',
  templateUrl: './authors.component.html'
})
@Injectable()
export class PersonsAuthorsComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public paginator: APIPaginator;
  public items: APIItem[];

  constructor(
    private itemService: ItemService,
    private router: Router,
    private route: ActivatedRoute
  ) {}

  ngOnInit(): void {
    /*this.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: true
      },
      name: 'page/214/name',
      pageId: 214
    });*/

    this.querySub = this.route.queryParams.subscribe(params => {
      this.itemService
        .getItems({
          type_id: 8,
          fields: [
            'name_html,name_default,description,has_text',
            'url,more_pictures_url',
            'preview_pictures.picture.thumb_medium,total_pictures'
          ].join(','),
          descendant_pictures: {
            status: 'accepted',
            type_id: 2
          },
          preview_pictures: {
            type_id: 2
          },
          order: 'name',
          limit: 10,
          page: params.page
        })
        .subscribe(
          response => {
            this.items = response.items;
            this.paginator = response.paginator;
          },
          () => {
            this.router.navigate(['/error-404']);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
