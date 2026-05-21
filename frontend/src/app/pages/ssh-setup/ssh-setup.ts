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

  connect() {
    if (!this.sshHost || !this.logsPath || !this.password || !this.projectName) {
      this.error = 'Vul alle velden in.';
      return;
    }

    this.error = '';
    this.isLoading = true;

    this.logService.connectDynamicProject({
      sshHost: this.sshHost,
      logsPath: this.logsPath,
      password: this.password,
      projectName: this.projectName,
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
        this.error = err.error?.error || 'Verbinding mislukt. Controleer de gegevens.';
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

