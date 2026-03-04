<?php
// app/Http/Controllers/RepairRequestController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\RepairRequest;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class RepairRequestController extends Controller
{
    // ─── Публичное создание заявки (без авторизации) ──────────────────────
    public function store(Request $request)
    {
        $validated = $request->validate([
            'clientName'  => 'required|string|max:255',
            'phone'       => 'required|string|max:50',
            'address'     => 'required|string|max:500',
            'problemText' => 'required|string',
        ]);

        $repairRequest = RepairRequest::create([
            ...$validated,
            'status' => 'new',
        ]);

        return response()->json($repairRequest, 201);
    }

    // ─── Диспетчер: список всех заявок с фильтром по статусу ──────────────
    public function index(Request $request)
    {
        $query = RepairRequest::with('master:id,name');

        if ($request->has('status') && $request->status !== '') {
            $query->where('status', $request->status);
        }

        $requests = $query->orderByDesc('created_at')->get();

        return response()->json($requests);
    }

    // ─── Диспетчер: назначить мастера → статус assigned ───────────────────
    public function assign(Request $request, int $id)
    {
        $request->validate([
            'assignedTo' => 'required|exists:users,id',
        ]);

        $master = User::find($request->assignedTo);
        if (!$master || $master->role !== 'master') {
            return response()->json(['message' => 'User is not a master'], 422);
        }

        $repairRequest = RepairRequest::findOrFail($id);

        if (!in_array($repairRequest->status, ['new', 'assigned'])) {
            return response()->json([
                'message' => 'Cannot assign: request status is "' . $repairRequest->status . '"',
            ], 409);
        }

        $repairRequest->update([
            'assignedTo' => $request->assignedTo,
            'status'     => 'assigned',
        ]);

        return response()->json($repairRequest->fresh('master'));
    }

    // ─── Диспетчер: отменить заявку ───────────────────────────────────────
    public function cancel(int $id)
    {
        $repairRequest = RepairRequest::findOrFail($id);

        if ($repairRequest->status === 'done') {
            return response()->json(['message' => 'Cannot cancel completed request'], 409);
        }

        $repairRequest->update(['status' => 'canceled']);

        return response()->json($repairRequest->fresh());
    }

    // ─── Мастер: мои заявки ───────────────────────────────────────────────
    public function myRequests(Request $request)
    {
        $requests = RepairRequest::where('assignedTo', $request->user()->id)
            ->orderByDesc('created_at')
            ->get();

        return response()->json($requests);
    }

    // ─── Мастер: взять в работу (защита от гонки) ─────────────────────────
    public function takeInProgress(Request $request, int $id)
    {
        $masterId = $request->user()->id;

        // DB transaction + pessimistic lock (SELECT FOR UPDATE)
        // Гарантирует: только один запрос изменит статус, второй получит 409
        try {
            $result = DB::transaction(function () use ($id, $masterId) {
                // lockForUpdate() блокирует строку до конца транзакции
                $repairRequest = RepairRequest::lockForUpdate()->findOrFail($id);

                if ($repairRequest->assignedTo !== $masterId) {
                    abort(403, 'This request is not assigned to you');
                }

                if ($repairRequest->status !== 'assigned') {
                    abort(409, 'Request is already in status: ' . $repairRequest->status);
                }

                $repairRequest->update(['status' => 'in_progress']);

                return $repairRequest->fresh();
            });

            return response()->json($result);
        } catch (\Illuminate\Http\Exceptions\HttpResponseException $e) {
            throw $e;
        } catch (\Exception $e) {
            // Второй параллельный запрос попадает сюда
            return response()->json(['message' => $e->getMessage()], 409);
        }
    }

    // ─── Мастер: завершить заявку ─────────────────────────────────────────
    public function complete(Request $request, int $id)
    {
        $masterId      = $request->user()->id;
        $repairRequest = RepairRequest::findOrFail($id);

        if ($repairRequest->assignedTo !== $masterId) {
            return response()->json(['message' => 'This request is not assigned to you'], 403);
        }

        if ($repairRequest->status !== 'in_progress') {
            return response()->json([
                'message' => 'Cannot complete: status is "' . $repairRequest->status . '"',
            ], 409);
        }

        $repairRequest->update(['status' => 'done']);

        return response()->json($repairRequest->fresh());
    }
}
