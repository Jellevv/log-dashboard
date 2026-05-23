import { Component } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { LogFilesService } from '../../services/log-files';

@Component({
  selector: 'app-ssh-setup',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './ssh-setup.html',
  styleUrl: './ssh-setup.css'
})
export class SshSetup {
  sshHost = '';
  logsPath = '';
  password = '';
  projectName = '';
  error = '';
  isLoading = false;

  constructor(
    private router: Router,
    private logService: LogFilesService
  ) { }

  authMode: 'password' | 'key' = 'password';

  connect() {
    if (!this.sshHost || !this.logsPath || !this.projectName) {
      this.error = 'Vul alle velden in.';
      return;
    }

    if (this.authMode === 'password' && !this.password) {
      this.error = 'Wachtwoord vereist.';
      return;
    }

    this.isLoading = true;
    this.error = '';

    this.logService.connectDynamicProject({
      sshHost: this.sshHost,
      logsPath: this.logsPath,
      projectName: this.projectName,
      authMode: this.authMode,
      password: this.authMode === 'password' ? this.password : null,
    }).subscribe({
      next: () => {
        this.isLoading = false;
        this.router.navigate(['/logs'], {
          queryParams: {
            project: 'dynamic',
            projectName: this.projectName, 
          }
        });
      },
      error: (err) => {
        this.isLoading = false;
        this.error = err.error?.error || 'Verbinding mislukt.';
      }
    });
  }

  //TIJDELIJK (live debug)
  connectLocal() {
    this.logService.connectLocalProject(
      '/var/www/html/storage/logs',
      'Local Dev'
    );
    this.router.navigate(['/logs'], {
      queryParams: { project: 'dynamic', projectName: 'Local Dev' }
    });
  }
}

