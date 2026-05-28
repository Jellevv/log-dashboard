<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\SavedConnection;

class SavedConnectionController extends Controller
{
    public function index()
    {
        return response()->json(SavedConnection::orderBy('created_at', 'desc')->get());
    }

    public function store(Request $request)
    {
        $request->validate([
            'project_name' => 'required|string',
            'ssh_host' => 'required|string',
            'logs_path' => 'required|string',
        ]);

        $connection = SavedConnection::create([
            'project_name' => trim($request->input('project_name')),
            'ssh_host' => trim($request->input('ssh_host')),
            'logs_path' => trim($request->input('logs_path')),
        ]);

        return response()->json($connection, 201);
    }

    public function destroy(int $id)
    {
        $connection = SavedConnection::find($id);

        if (!$connection) {
            return response()->json(['error' => 'Connection not found'], 404);
        }

        $connection->delete();

        return response()->json(['success' => true]);
    }
}
