import { Component, Injectable } from '@angular/core';
import Notify from '../../notify';
import { SpecService, APISpec } from '../../services/spec';
import { PageEnvService } from '../../services/page-env.service';

@Component({
  selector: 'app-info-spec',
  templateUrl: './spec.component.html'
})
@Injectable()
export class InfoSpecComponent {
  public specs: APISpec[];

  constructor(
    private specService: SpecService,
    private pageEnv: PageEnvService
  ) {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            needRight: true
          },
          name: 'page/174/name',
          pageId: 174
        }),
      0
    );

    this.specService
      .getSpecs()
      .then(
        specs => (this.specs = specs),
        response => Notify.response(response)
      );
  }
}
