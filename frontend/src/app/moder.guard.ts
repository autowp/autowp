import {inject} from '@angular/core';
import {CanActivateFn} from '@angular/router';
import {AuthService, Role} from '@services/auth.service';

export const moderGuard: CanActivateFn = () => {
  const auth = inject(AuthService);
  return auth.hasRole$(Role.MODER);
};
