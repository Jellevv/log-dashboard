import { Injectable } from '@angular/core';
import { HttpClient } from '@angular/common/http';
import { Observable } from 'rxjs';
import { map, tap } from 'rxjs/operators';
import { environment } from '../../environments/environment';

export interface Project {
  id: string;
  label: string;
}

export interface DynamicProject {
  id: 'dynamic';
  label: string;
  ssh: {
    host: string;
    user: string;
    password: string;
    path: string;
  };
}

export interface LogFiles {
  name: string;
  size: string;
  modified: string;
}

export interface LogEntry {
  timestamp: string;
  level: string;
  component?: string;
  message: string;

  stackTrace?: string | null;
  context?: any | null;
  exception?: string | null;

  requestUrl?: string | null;
  codeLocation?: string | null;
  memory?: number | null;
}

export interface LogPage {
  entries: LogEntry[];
  total: number;
  totalFiltered: number;
  counts: { ERROR: number; WARNING: number; INFO: number };
  page: number;
  limit: number;
}

export type Filter = 'ALL' | 'ERROR' | 'WARNING' | 'INFO';

@Injectable({ providedIn: 'root' })
export class LogFilesService {

  private readonly base = environment.apiUrl + '/api';
  private dynamicProject: DynamicProject | null = null;

  constructor(private http: HttpClient) { }

  getProjects(): Observable<Project[]> {
    return this.http.get<Project[]>(`${this.base}/projects`).pipe(
      map(projects => {
        if (!this.dynamicProject) {
          return [];
        }
        return [{ id: this.dynamicProject.id, label: this.dynamicProject.label }];
      })
    );
  }

  connectDynamicProject(payload: {
    sshHost: string;
    logsPath: string;
    password: string;
    projectName: string;
  }): Observable<DynamicProject> {
    return this.http.post<DynamicProject>(`${this.base}/connect`, payload).pipe(
      tap((project) => {
        this.dynamicProject = project;
      })
    );
  }

  getLogs(projectId: string): Observable<LogFiles[]> {
    if (projectId === 'dynamic' && this.dynamicProject) {
      return this.http.post<LogFiles[]>(`${this.base}/logs`, {
        project: projectId,
        ssh: this.dynamicProject.ssh,
      });
    }

    return this.http.get<LogFiles[]>(`${this.base}/logs`, {
      params: { project: projectId }
    });
  }

  getLogContent(
    projectId: string,
    fileName: string,
    options: { page?: number; limit?: number; level?: Filter; search?: string } = {}
  ): Observable<LogPage> {
    const payload = {
      project: projectId,
      file: fileName,
      page: String(options.page ?? 1),
      limit: String(options.limit ?? 100),
      level: options.level ?? 'ALL',
      search: options.search ?? '',
    };

    if (projectId === 'dynamic' && this.dynamicProject) {
      return this.http.post<LogPage>(`${this.base}/log-content`, {
        ...payload,
        ssh: this.dynamicProject.ssh,
      });
    }

    return this.http.get<LogPage>(`${this.base}/log-content`, { params: payload });
  }

  clearDynamicProject(): void {
    this.dynamicProject = null;
  }
}
