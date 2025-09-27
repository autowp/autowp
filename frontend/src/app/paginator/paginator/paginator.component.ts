import {ChangeDetectionStrategy, Component, input} from '@angular/core';
import {RouterLink} from '@angular/router';
import {Pages} from '@grpc/spec.pb';
import {GoautowpPages} from '@rest/model/goautowpPages';

@Component({
  selector: 'app-paginator',
  imports: [RouterLink],
  templateUrl: './paginator.component.html',
  changeDetection: ChangeDetectionStrategy.OnPush,
})
export class PaginatorComponent {
  readonly data = input.required<GoautowpPages | Pages>();

  protected pagesInRange(): number[] {
    return Object.values(this.data().pagesInRange);
  }

  protected padd(page: number): string {
    const size = Math.max(2, this.data().pageCount.toString().length);
    return page.toString().padStart(size, '0');
  }
}
