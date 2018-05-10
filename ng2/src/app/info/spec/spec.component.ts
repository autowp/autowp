import { Component, Injectable } from '@angular/core';
import Notify from '../../notify';
import { SpecService, APISpec } from '../../services/spec';

@Component({
  selector: 'app-info-spec',
  templateUrl: './spec.component.html'
})
@Injectable()
export class InfoSpecComponent {
  public specs: APISpec[];

  constructor(private specService: SpecService) {
    /*his.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: true
      },
      name: 'page/174/name',
      pageId: 174
    });*/

    this.specService
      .getSpecs()
      .then(
        specs => (this.specs = specs),
        response => Notify.response(response)
      );
  }
}
