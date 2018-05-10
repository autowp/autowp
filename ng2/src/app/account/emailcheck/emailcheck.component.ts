import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { ActivatedRoute } from '@angular/router';
import { Subscription } from 'rxjs';

@Component({
  selector: 'app-account-emailcheck',
  templateUrl: './emailcheck.component.html'
})
@Injectable()
export class AccountEmailcheckComponent implements OnInit, OnDestroy {
  private routeSub: Subscription;
  public success = false;
  public failure = false;

  constructor(private http: HttpClient, private route: ActivatedRoute) {}

  ngOnInit(): void {
    /*this.$scope.pageEnv({
      layout: {
        blankPage: false,
        needRight: false
      },
      name: 'page/54/name',
      pageId: 54
    });*/
    this.routeSub = this.route.params.subscribe(params => {
      this.http
        .post('/api/user/emailcheck', {
          code: params.code
        })
        .subscribe(() => (this.success = true), () => (this.failure = true));
    });
  }

  ngOnDestroy(): void {
    this.routeSub.unsubscribe();
  }
}
