import { Component, Injectable, OnInit, OnDestroy } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import {
  APIACL,
  APIACLRole,
  APIACLRule,
  APIACLResource
} from '../../services/acl.service';
import { PageEnvService } from '../../services/page-env.service';
import { BehaviorSubject, combineLatest, Subscription } from 'rxjs';
import { switchMapTo } from 'rxjs/operators';

// Acl.inheritsRole('moder', 'unauthorized');

interface IAddRoleParentForm {
  role: null | string;
  parentRole: null | string;
}

interface IAddRoleForm {
  name: null | string;
}

interface IAddRuleForm {
  role: null | string;
  privilege: null | string;
  allowed: number;
}

@Component({
  selector: 'app-moder-rights',
  templateUrl: './rights.component.html'
})
@Injectable()
export class ModerRightsComponent implements OnInit, OnDestroy {
  public rules: APIACLRule[] = [];
  public resources: APIACLResource[] = [];
  public roles: APIACLRole[] = [];
  public rolesTree: APIACLRole[] = [];
  private $loadRoles = new BehaviorSubject<null>(null);
  private $loadRolesTree = new BehaviorSubject<null>(null);
  private $loadRules = new BehaviorSubject<null>(null);

  public addRoleParentForm: IAddRoleParentForm = {
    role: null,
    parentRole: null
  };

  public addRoleForm: IAddRoleForm = {
    name: null
  };
  public addRuleForm: IAddRuleForm = {
    role: null,
    privilege: null,
    allowed: 0
  };
  private sub: Subscription;

  constructor(
    private http: HttpClient,
    private acl: APIACL,
    private pageEnv: PageEnvService
  ) {}

  ngOnInit(): void {
    setTimeout(
      () =>
        this.pageEnv.set({
          layout: {
            isAdminPage: true,
            needRight: false
          },
          name: 'page/71/name',
          pageId: 71
        }),
      0
    );

    this.sub = combineLatest(
      this.$loadRolesTree.pipe(switchMapTo(this.acl.getRoles(true))),
      this.$loadRoles.pipe(switchMapTo(this.acl.getRoles(false))),
      this.acl.getResources(),
      this.$loadRules.pipe(switchMapTo(this.acl.getRules()))
    ).subscribe(data => {
      this.rolesTree = data[0].items;
      this.roles = data[1].items;
      this.resources = data[2].items;
      this.rules = data[3].items;
    });
  }

  ngOnDestroy(): void {
    this.sub.unsubscribe();
  }

  public addRoleParent() {
    if (this.addRoleParentForm.role) {
      this.http
        .post<void>(
          '/api/acl/roles/' +
            encodeURIComponent(this.addRoleParentForm.role) +
            '/parents',
          {
            role: this.addRoleParentForm.parentRole
          }
        )
        .subscribe(() => {
          this.$loadRoles.next(null);
          this.$loadRolesTree.next(null);
        });
    }
  }

  public addRole() {
    this.http
      .post<void>('/api/acl/roles', {
        data: this.addRoleForm
      })
      .subscribe(() => {
        this.$loadRoles.next(null);
        this.$loadRolesTree.next(null);
      });
  }

  public addRule() {
    if (!this.addRuleForm.privilege) {
      return;
    }

    const privilege = this.addRuleForm.privilege.split('/');
    this.http
      .post<void>('/api/acl/rules', {
        role: this.addRuleForm.role,
        resource: privilege[0],
        privilege: privilege[1],
        allowed: this.addRuleForm.allowed
      })
      .subscribe(() => {
        this.$loadRules.next(null);
      });
  }
}
