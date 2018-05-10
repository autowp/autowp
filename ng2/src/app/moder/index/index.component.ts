import { Component, Injectable, AfterViewInit } from '@angular/core';

@Component({
  selector: 'app-moder-index',
  templateUrl: './index.component.html'
})
@Injectable()
export class ModerIndexComponent implements AfterViewInit {
  ngAfterViewInit() {
    /*this.$scope.pageEnv({
      layout: {
        isAdminPage: true,
        blankPage: false,
        needRight: false
      },
      name: 'page/67/name',
      pageId: 67
    });*/
  }
}
