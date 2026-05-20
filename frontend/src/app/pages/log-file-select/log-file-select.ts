import { Component, OnInit, ChangeDetectorRef } from '@angular/core';
import { CommonModule } from '@angular/common';
import { Router, ActivatedRoute } from '@angular/router';
import { LogFilesService, LogFiles, Project } from '../../services/log-files';
import { FormsModule } from '@angular/forms';
import { take } from 'rxjs/operators';

@Component({
  selector: 'app-log-file-select',
  standalone: true,
  imports: [CommonModule, FormsModule],
  templateUrl: './log-file-select.html',
  styleUrl: './log-file-select.css',
})
export class LogFileSelect implements OnInit {

  logFiles: LogFiles[] = [];
  projects: Project[] = [];
  projectId = '';
  projectName = '';   // ← the human-readable label from ssh-setup
  selectedFile = '';
  isProjectsLoading = false;
  isLogsLoading = false;
  error = '';

  constructor(
    private logService: LogFilesService,
    private router: Router,
    private route: ActivatedRoute,
    private cdr: ChangeDetectorRef
  ) {}

  ngOnInit() {
    this.isProjectsLoading = true;

    this.route.queryParamMap.pipe(take(1)).subscribe((params) => {
      this.projectId   = params.get('project')     ?? '';
      this.projectName = params.get('projectName') ?? '';

      this.logService.getProjects().subscribe({
        next: (projects) => {
          this.projects = projects;
          this.isProjectsLoading = false;

          if (!this.projectId || this.projectId !== 'dynamic') {
            this.router.navigate(['/ssh']);
            return;
          }

          this.loadLogs();
        },
        error: (err) => {
          console.error(err);
          this.error = 'Kon projecten niet ophalen.';
          this.isProjectsLoading = false;
        }
      });
    });
  }

  loadLogs() {
    if (!this.projectId) return;

    this.isLogsLoading = true;
    this.selectedFile = '';
    this.error = '';

    this.logService.getLogs(this.projectId).subscribe({
      next: (files) => {
        this.logFiles = Array.isArray(files) ? files : [];
        this.isLogsLoading = false;
        this.cdr.detectChanges();
      },
      error: (err) => {
        console.error('getLogs error:', err);
        this.error = err.error?.error || 'Kon logbestanden niet ophalen.';
        this.isLogsLoading = false;
        this.cdr.detectChanges();
      }
    });
  }

  onProjectChange(event: Event) {
    this.projectId = (event.target as HTMLSelectElement).value;
    this.loadLogs();
  }

  get selectedProjectLabel(): string {
    if (this.projectName) return this.projectName;
    return this.projects.find(p => p.id === this.projectId)?.label ?? this.projectId;
  }

  selectFile(fileName: string) {
    this.selectedFile = fileName;
  }

  openDashboard() {
    if (!this.selectedFile) return;
    this.router.navigate(['/dashboard'], {
      queryParams: {
        project:     this.projectId,
        projectName: this.projectName, 
        file:        this.selectedFile,
      }
    });
  }

  goBack() {
    this.router.navigate(['/ssh']);
  }
}
