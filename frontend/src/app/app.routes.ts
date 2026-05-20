import { Routes } from '@angular/router';
import { SshSetup } from './pages/ssh-setup/ssh-setup';
import { LogFileSelect } from './pages/log-file-select/log-file-select';
import { LogDashboard } from './pages/log-dashboard/log-dashboard';

export const routes: Routes = [

    { path: '', redirectTo: 'ssh', pathMatch: 'full' },

    { path: 'ssh', component: SshSetup },

    { path: 'logs',component: LogFileSelect },

    { path: 'dashboard',component: LogDashboard },
];
