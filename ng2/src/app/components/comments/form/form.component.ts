import { HttpClient, HttpResponse } from '@angular/common/http';
import { Input, Component, Injectable } from '@angular/core';
import Notify from '../../../notify';

@Component({
  selector: 'app-comments-form',
  templateUrl: './form.component.html'
})
@Injectable()
export class CommentsFormComponent {
  @Input() parentId: number;
  @Input() itemId: number;
  @Input() typeId: number;
  @Input() onSent: Function;

  public invalidParams: any = {};
  public form = {
    message: '',
    moderator_attention: false
  };

  constructor(private http: HttpClient) {}

  public sendMessage() {
    this.invalidParams = {};

    this.http
      .post<void>(
        '/api/comment',
        {
          type_id: this.typeId,
          item_id: this.itemId,
          parent_id: this.parentId,
          moderator_attention: this.form.moderator_attention ? 1 : 0,
          message: this.form.message
        },
        {
          observe: 'response'
        }
      )
      .subscribe(
        response => {
          this.form.message = '';
          this.form.moderator_attention = false;

          const location = response.headers.get('Location');

          this.onSent(location);
        },
        response => {
          if (response.status === 400) {
            this.invalidParams = response.data.invalid_params;
          } else {
            Notify.response(response);
          }
        }
      );
  }
}
