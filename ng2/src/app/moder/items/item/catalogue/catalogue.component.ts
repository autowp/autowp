import {
  Component,
  Injectable,
  Input,
  OnInit,
  OnChanges,
  SimpleChanges,
  OnDestroy
} from '@angular/core';
import { APIItem, ItemService } from '../../../../services/item';
import {
  debounceTime,
  map,
  distinctUntilChanged,
  switchMap
} from 'rxjs/operators';
import { of, Observable, Subscription } from 'rxjs';
import { NgbTypeaheadSelectItemEvent } from '@ng-bootstrap/ng-bootstrap';
import { HttpClient } from '@angular/common/http';
import { ACLService } from '../../../../services/acl.service';
import {
  ItemParentService,
  APIItemParent
} from '../../../../services/item-parent';

@Component({
  selector: 'app-moder-items-item-catalogue',
  templateUrl: './catalogue.component.html'
})
@Injectable()
export class ModerItemsItemCatalogueComponent
  implements OnInit, OnChanges, OnDestroy {
  @Input() item: APIItem;

  public loading = 0;
  public childsLoading = 0;
  public parentsLoading = 0;

  public itemQuery = '';

  public canHaveParentBrand = false;
  public canHaveParents = false;
  public canMove = false;
  public suggestions: APIItem[] = [];
  public parents: APIItemParent[] = [];
  public childs: APIItemParent[] = [];

  public organizeTypeId: number;

  public itemsDataSource: (text$: Observable<string>) => Observable<APIItem[]>;
  private aclSub: Subscription;

  constructor(
    private acl: ACLService,
    private http: HttpClient,
    private itemService: ItemService,
    private itemParentService: ItemParentService
  ) {
    this.itemsDataSource = (text$: Observable<string>) =>
      text$.pipe(
        debounceTime(50),
        map(text => text.trim()),
        distinctUntilChanged(),
        switchMap(query => {
          if (query === '') {
            return of([]);
          }

          return this.itemService
            .getItems({
              autocomplete: query,
              exclude_self_and_childs: this.item.id,
              is_group: true,
              parent_types_of: this.item.item_type_id,
              fields: 'name_html,name_text,brandicon',
              limit: 15
            })
            .pipe(
              map(response => {
                return response.items;
              })
            );
        })
      );
  }

  ngOnInit(): void {
    this.aclSub = this.acl
      .isAllowed('car', 'move')
      .subscribe(allow => (this.canMove = !!allow));
  }

  ngOnDestroy(): void {
    this.aclSub.unsubscribe();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.item) {
      this.canHaveParentBrand = [1, 2].includes(this.item.item_type_id);
      this.canHaveParents = ![4, 6].includes(this.item.item_type_id);

      this.organizeTypeId = this.item.item_type_id;
      switch (this.organizeTypeId) {
        case 5:
          this.organizeTypeId = 1;
          break;
      }

      this.loadChilds();
      this.loadParents();
      this.loadSuggestions();
    }
  }

  private loadChilds() {
    this.childsLoading++;
    this.itemParentService
      .getItems({
        parent_id: this.item.id,
        limit: 500,
        fields:
          'name,duplicate_child.name_html,item.name_html,item.name,item.public_urls',
        order: 'type_auto'
      })
      .subscribe(
        response => {
          this.childs = response.items;
          this.childsLoading--;
        },
        () => {
          this.childsLoading--;
        }
      );
  }

  private loadParents() {
    this.parentsLoading++;
    this.itemParentService
      .getItems({
        item_id: this.item.id,
        limit: 500,
        fields:
          'name,duplicate_parent.name_html,parent.name_html,parent.name,parent.public_urls'
      })
      .subscribe(
        response => {
          this.parents = response.items;
          this.parentsLoading--;
        },
        () => {
          this.parentsLoading--;
        }
      );
  }

  private loadSuggestions() {
    this.loading++;
    this.itemService
      .getItems({
        suggestions_to: this.item.id,
        limit: 3,
        fields: 'name_text'
      })
      .subscribe(
        response => {
          this.suggestions = response.items;
          this.loading--;
        },
        () => {
          this.loading--;
        }
      );
  }

  public itemFormatter(x: APIItem) {
    return x.name_text;
  }

  public itemOnSelect(e: NgbTypeaheadSelectItemEvent): void {
    e.preventDefault();
    this.addParent(e.item.id);
    this.itemQuery = '';
  }

  public addParent(parentId: number) {
    this.loading++;
    this.http
      .post<void>('/api/item-parent', {
        item_id: this.item.id,
        parent_id: parentId
      })
      .subscribe(
        () => {
          this.loadParents();
          this.loadSuggestions();
          this.loading--;
        },
        () => {
          this.loading--;
        }
      );

    return false;
  }

  public deleteChild(itemId: number) {
    this.loading++;
    this.http
      .delete<void>('/api/item-parent/' + itemId + '/' + this.item.id)
      .subscribe(
        () => {
          this.loadChilds();
          this.loadParents();
          this.loadSuggestions();
          this.loading--;
        },
        () => {
          this.loading--;
        }
      );
  }

  public deleteParent(parentId: number) {
    this.loading++;
    this.http
      .delete<void>('/api/item-parent/' + this.item.id + '/' + parentId)
      .subscribe(
        () => {
          this.loadChilds();
          this.loadParents();
          this.loadSuggestions();
          this.loading--;
        },
        () => {
          this.loading--;
        }
      );
  }
}
