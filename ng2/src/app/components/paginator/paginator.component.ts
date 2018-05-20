import { Component, Input, Injectable } from '@angular/core';
import * as leftPad from 'left-pad';
import { APIPaginator } from '../../services/api.service';

@Component({
  selector: 'app-paginator',
  templateUrl: './paginator.component.html'
})
@Injectable()
export class PaginatorComponent {
  @Input() data: APIPaginator;

  public pagesInRange(): number[] {
    return Object.values(this.data.pagesInRange);
  }

  public padd(page: number): string {
    const size = Math.max(2, this.data.pageCount.toString().length);
    return leftPad(page, size, '0');
  }
}
