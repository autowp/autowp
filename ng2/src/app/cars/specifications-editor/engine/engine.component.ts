import {
  Injectable,
  Component,
  OnInit,
  Input,
  OnChanges,
  SimpleChanges,
  Output,
  EventEmitter,
  OnDestroy
} from '@angular/core';
import { APIItem, ItemService } from '../../../services/item';
import { ACLService } from '../../../services/acl.service';
import Notify from '../../../notify';
import { HttpClient } from '@angular/common/http';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-cars-specifications-editor-engine',
  templateUrl: './engine.component.html'
})
@Injectable()
export class CarsSpecificationsEditorEngineComponent
  implements OnInit, OnChanges, OnDestroy {
  @Input() item: APIItem;
  @Output() changed = new EventEmitter<void>();
  public isAllowedEditEngine = false;
  public engine: APIItem;
  public loading = 0;
  private aclSub: Subscription;

  constructor(
    private acl: ACLService,
    private itemService: ItemService,
    private http: HttpClient
  ) {}

  ngOnInit(): void {
    this.aclSub = this.acl
      .isAllowed('specifications', 'edit-engine')
      .subscribe(allow => (this.isAllowedEditEngine = !!allow));
  }

  ngOnDestroy(): void {
    this.aclSub.unsubscribe();
  }

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.item) {
      if (this.item.engine_id) {
        this.loading++;
        this.itemService
          .getItem(this.item.engine_id, {
            fields: 'name_html,name_text,engine_id'
          })
          .subscribe(
            engine => {
              this.engine = engine;
              this.loading--;
            },
            response => {
              Notify.response(response);
              this.loading--;
            }
          );
      } else {
        this.engine = null;
      }
    }
  }

  public inheritEngine() {
    this.http
      .put<void>('/api/item/' + this.item.id, {
        engine_id: 'inherited'
      })
      .subscribe(
        response => this.changed.emit(),
        response => Notify.response(response)
      );
  }

  public cancelInheritance() {
    this.http
      .put<void>('/api/item/' + this.item.id, {
        engine_id: ''
      })
      .subscribe(
        response => this.changed.emit(),
        response => Notify.response(response)
      );
  }
}
