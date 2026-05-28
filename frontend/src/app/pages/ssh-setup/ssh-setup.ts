import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { FormsModule } from '@angular/forms';
import { Router } from '@angular/router';
import { LogFilesService, SavedConnection } from '../../services/log-files';

@Component({
  selector: 'app-ssh-setup',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './ssh-setup.html',
  styleUrl: './ssh-setup.css'
})

export class SshSetup implements OnInit {
  sshHost = '';
  logsPath = '';
  password = '';
  projectName = '';
  error = '';
  isLoading = false;

  constructor(
    private router: Router,
    private logService: LogFilesService,
    private cdr: ChangeDetectorRef
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
        this.cdr.detectChanges();
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

  savedConnections: SavedConnection[] = [];
  showSaved = false;

  ngOnInit() {
    this.logService.getSavedConnections().subscribe({
      next: (connections) => {
        this.savedConnections = connections;
        this.cdr.detectChanges();
      },
      error: () => { }
    });
  }

  loadConnection(connection: SavedConnection) {
    this.sshHost = connection.ssh_host;
    this.logsPath = connection.logs_path;
    this.projectName = connection.project_name;
    this.showSaved = false;
  }

  saveConnection() {
    if (!this.sshHost || !this.logsPath || !this.projectName) {
      this.error = 'Vul alle velden in om op te slaan.';
      return;
    }

    this.logService.saveConnection({
      project_name: this.projectName,
      ssh_host: this.sshHost,
      logs_path: this.logsPath,
    }).subscribe({
      next: (saved) => {
        this.savedConnections.unshift(saved);
        this.cdr.detectChanges();
      },
      error: () => {
        this.error = 'Kon verbinding niet opslaan.';
        this.cdr.detectChanges();
      }
    });
  }

  deleteConnection(id: number, event: MouseEvent) {
    event.stopPropagation();
    this.logService.deleteConnection(id).subscribe({
      next: () => {
        this.savedConnections = this.savedConnections.filter(c => c.id !== id);
        this.cdr.detectChanges();
      },
      error: () => { }
    });
  }
}

