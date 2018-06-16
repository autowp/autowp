import {
  Input,
  Component,
  Injectable,
  OnInit,
  OnChanges,
  SimpleChanges,
  OnDestroy
} from '@angular/core';
import { APIItem } from '../../../../services/item';
import { ACLService } from '../../../../services/acl.service';
import { HttpClient } from '@angular/common/http';
import { APIImage } from '../../../../services/api.service';
import * as $ from 'jquery';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-moder-items-item-logo',
  templateUrl: './logo.component.html'
})
@Injectable()
export class ModerItemsItemLogoComponent
  implements OnInit, OnChanges, OnDestroy {
  @Input() item: APIItem;

  public loading = 0;
  public canLogo = false;
  private aclSub: Subscription;

  constructor(private acl: ACLService, private http: HttpClient) {}

  ngOnInit(): void {
    this.aclSub = this.acl
      .isAllowed('brand', 'logo')
      .subscribe(allow => (this.canLogo = allow));
  }

  ngOnDestroy(): void {
    this.aclSub.unsubscribe();
  }

  ngOnChanges(changes: SimpleChanges): void {}

  public uploadLogo() {
    this.loading++;
    const element = $('#logo-upload') as any;
    this.http
      .put<void>('/api/item/' + this.item.id + '/logo', element[0].files[0], {
        headers: { 'Content-Type': undefined }
      })
      .subscribe(
        response => {
          this.loading++;
          this.http
            .get<APIImage>('/api/item/' + this.item.id + '/logo')
            .subscribe(
              subresponse => {
                this.item.logo = subresponse;
                this.loading--;
              },
              () => {
                this.loading--;
              }
            );

          this.loading--;
        },
        response => {
          this.loading--;
        }
      );
  }
}
