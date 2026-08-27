import type {PicturesPages} from '@grpc/spec.pb';

import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';

@Component({
  selector: 'app-picture-paginator',
  imports: [RouterLink],
  templateUrl: './paginator.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
  preserveWhitespaces: false,
})
export class PicturePaginatorComponent {
  readonly paginator = input.required<PicturesPages>();
  readonly prefix = input.required<string[]>();

  protected format(page: number, count: number) {
    const size = Math.max(2, count.toString().length);

    return page.toString().padStart(size, '0');
  }
}
