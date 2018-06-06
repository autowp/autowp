import {
  OnChanges,
  OnInit,
  Injectable,
  Component,
  Input,
  SimpleChanges
} from '@angular/core';
import { APIItem } from '../../../services/item';
import { HttpClient } from '@angular/common/http';
import Notify from '../../../notify';

@Component({
  selector: 'app-cars-specifications-editor-result',
  templateUrl: './result.component.html'
})
@Injectable()
export class CarsSpecificationsEditorResultComponent
  implements OnInit, OnChanges {
  @Input() item: APIItem;
  public loading = 0;
  public resultHtml = '';

  constructor(private http: HttpClient) {}

  ngOnInit(): void {}

  ngOnChanges(changes: SimpleChanges): void {
    if (changes.item) {
      this.load();
    }
  }

  private load() {
    this.loading++;
    this.http
      .get('/api/item/' + this.item.id + '/specifications', {
        responseType: 'text'
      })
      .subscribe(
        response => {
          this.resultHtml = response;
          this.loading--;
        },
        response => {
          Notify.response(response);
          this.loading--;
        }
      );
  }
}
