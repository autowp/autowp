import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { APIPaginator } from '../../services/api.service';
import { APIItem, ItemService } from '../../services/item';
import { Router, ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';
import { PageEnvService } from '../../services/page-env.service';
import { distinctUntilChanged, debounceTime, switchMap } from 'rxjs/operators';

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
    private route: ActivatedRoute,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/214/name',
          pageId: 214
        }),
      0
    );

    this.querySub = this.route.queryParams
      .pipe(
        distinctUntilChanged(),
        debounceTime(30),
        switchMap(params =>
          this.itemService.getItems({
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
        )
      )
      .subscribe(data => {
        this.items = data.items;
        this.paginator = data.paginator;
      });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
