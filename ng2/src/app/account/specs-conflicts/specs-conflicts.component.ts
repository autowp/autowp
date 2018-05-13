import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { APIPaginator } from '../../services/api.service';
import { UserService, APIUser } from '../../services/user';
import Notify from '../../notify';
import { Router, ActivatedRoute } from '@angular/router';
import { AuthService } from '../../services/auth.service';
import { Subscription } from 'rxjs';
import {
  AttrsService,
  APIAttrConflict,
  APIAttrConflictValue
} from '../../services/attrs';
import { PageEnvService } from '../../services/page-env.service';

interface APIAttrConflictValueInList extends APIAttrConflictValue {
  user?: APIUser;
}

interface APIAttrConflictInList extends APIAttrConflict {
  values: APIAttrConflictValueInList[];
}

@Component({
  selector: 'app-account-specs-conflicts',
  templateUrl: './specs-conflicts.component.html'
})
@Injectable()
export class AccountSpecsConflictsComponent implements OnInit, OnDestroy {
  private querySub: Subscription;
  public filter: string;
  public conflicts: APIAttrConflictInList[] = [];
  public paginator: APIPaginator;
  public weight: number | null = null;
  public page: number;

  constructor(
    private http: HttpClient,
    private userService: UserService,
    private router: Router,
    public auth: AuthService,
    private route: ActivatedRoute,
    private attrService: AttrsService,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    this.querySub = this.route.queryParams.subscribe(params => {
      this.filter = params.filter || '0';
      this.page = params.page;

      this.pageEnv.set({
        layout: {
          needRight: false
        },
        name: 'page/188/name',
        pageId: 188
      });

      this.http
        .get<APIUser>('/api/user/me', {
          params: {
            fields: 'specs_weight'
          }
        })
        .subscribe(
          response => (this.weight = response.specs_weight),
          response => Notify.response(response)
        );

      this.attrService
        .getConfilicts({
          filter: this.filter,
          page: this.page,
          fields: 'values'
        })
        .subscribe(
          response => {
            this.conflicts = response.items;
            for (const conflict of this.conflicts) {
              for (const value of conflict.values) {
                if (this.auth.user.id !== value.user_id) {
                  this.userService.getUser(value.user_id, {}).then(user => {
                    value.user = user;
                  });
                }
              }
            }
            this.paginator = response.paginator;
          },
          response => {
            Notify.response(response);
          }
        );
    });
  }

  ngOnDestroy(): void {
    this.querySub.unsubscribe();
  }
}
