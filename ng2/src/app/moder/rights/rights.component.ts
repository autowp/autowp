import { Component, Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import {
  APIACL,
  APIACLRole,
  APIACLRule,
  APIACLResource
} from '../../services/acl.service';
import { PageEnvService } from '../../services/page-env.service';

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
export class ModerRightsComponent {
  public rules: APIACLRule[] = [];
  public resources: APIACLResource[] = [];
  public roles: APIACLRole[] = [];
  public rolesTree: APIACLRole[] = [];

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

  constructor(
    private http: HttpClient,
    private acl: APIACL,
    private pageEnv: PageEnvService
  ) {
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

    this.loadRules();
    this.loadResources();
    this.loadRoles();
    this.loadRolesTree();
  }

  private loadResources() {
    this.acl.getResources().subscribe(response => {
      this.resources = response.items;
    });
  }

  private loadRolesTree() {
    this.acl.getRoles(true).then(response => {
      this.rolesTree = response.items;
    });
  }

  private loadRoles() {
    this.acl.getRoles(false).then(response => {
      this.roles = response.items;
    });
  }

  private loadRules() {
    this.acl.getRules().subscribe(response => {
      this.rules = response.items;
    });
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
          this.loadRoles();
          this.loadRolesTree();
        });
    }
  }

  public addRole() {
    this.http
      .post<void>('/api/acl/roles', {
        data: this.addRoleForm
      })
      .subscribe(() => {
        this.loadRoles();
        this.loadRolesTree();
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
        this.loadRules();
      });
  }
}
