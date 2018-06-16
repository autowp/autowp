import {
  Component,
  Injectable,
  Input,
  OnInit,
  OnChanges,
  SimpleChanges,
  OnDestroy
} from '@angular/core';
import { APIItem } from '../../../../services/item';
import { ACLService } from '../../../../services/acl.service';
import { HttpClient } from '@angular/common/http';
import { APIItemLink, ItemLinkService } from '../../../../services/item-link';
import { Subscription, Observable, forkJoin } from 'rxjs';
import { tap } from 'rxjs/operators';

@Component({
  selector: 'app-moder-items-item-links',
  templateUrl: './links.component.html'
})
@Injectable()
export class ModerItemsItemLinksComponent
  implements OnInit, OnChanges, OnDestroy {
  @Input() item: APIItem;

  public loading = 0;

  public canEditMeta = false;

  public links: APIItemLink[];
  public newLink = {
    name: '',
    url: '',
    type_id: 'default'
  };
  private aclSub: Subscription;

  constructor(
    private acl: ACLService,
    private http: HttpClient,
    private itemLinkService: ItemLinkService
  ) {}

  ngOnInit(): void {
    this.aclSub = this.acl
      .isAllowed('car', 'edit_meta')
      .subscribe(allow => (this.canEditMeta = allow));
  }

  ngOnDestroy(): void {
    this.aclSub.unsubscribe();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.item) {
      this.loadLinks();
    }
  }

  private loadLinks() {
    this.loading++;
    this.itemLinkService
      .getItems({
        item_id: this.item.id
      })
      .subscribe(
        response => {
          this.links = response.items;
          this.loading--;
        },
        () => {
          this.loading--;
        }
      );
  }

  public saveLinks() {
    const promises: Observable<void>[] = [];

    if (this.newLink.url) {
      promises.push(
        this.http
          .post<void>('/api/item-link', {
            item_id: this.item.id,
            name: this.newLink.name,
            url: this.newLink.url,
            type_id: this.newLink.type_id
          })
          .pipe(
            tap(() => {
              this.newLink.name = '';
              this.newLink.url = '';
              this.newLink.type_id = 'default';
            })
          )
      );
    }

    for (const link of this.links) {
      if (link.url) {
        promises.push(
          this.http.put<void>('/api/item-link/' + link.id, {
            name: link.name,
            url: link.url,
            type_id: link.type_id
          })
        );
      } else {
        promises.push(this.http.delete<void>('/api/item-link/' + link.id));
      }
    }

    this.loading++;
    forkJoin(...promises).subscribe(
      () => this.loadLinks(),
      () => {},
      () => this.loading--
    );
  }
}
