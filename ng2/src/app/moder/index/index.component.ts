import { Component, Injectable, AfterViewInit } from '@angular/core';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-moder-index',
  templateUrl: './index.component.html'
})
@Injectable()
export class ModerIndexComponent implements AfterViewInit {
  constructor(private pageEnv: PageEnvService) {}

  ngAfterViewInit() {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/67/name',
          pageId: 67
        }),
      0
    );
  }
}
