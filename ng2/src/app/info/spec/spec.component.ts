import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import Notify from '../../notify';
import { SpecService, APISpec } from '../../services/spec';
import { PageEnvService } from '../../services/page-env.service';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-info-spec',
  templateUrl: './spec.component.html'
})
@Injectable()
export class InfoSpecComponent implements OnInit, OnDestroy {
  public specs: APISpec[];
  private sub: Subscription;

  constructor(
    private specService: SpecService,
    private pageEnv: PageEnvService
  ) {

  }

  ngOnInit(): void {
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

    this.sub = this.specService.getSpecs().subscribe(
      specs => (this.specs = specs),
      response => Notify.response(response)
    );
  }
  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }
}
