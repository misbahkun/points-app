<?php

namespace App\Http\Controllers;

use App\Models\Point;
use App\Models\Transaction;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TransactionController extends Controller
{
    public function store(Request $request)
    {
        try {
            $data = $request->validate([
                'user_id' => 'required|exists:users,id',
                'amount' => 'required|integer|min:1',
                'description' => 'required|string',
                'transacted_at' => 'required',
            ]);

            $transactedAt = is_numeric($data['transacted_at'])
                ? Carbon::createFromTimestamp((int)$data['transacted_at'])
                : Carbon::parse($data['transacted_at']);

            $points = intdiv((int)$data['amount'], 1000);

            Log::info('Transacted at: ' . $transactedAt->toISOString());
            Log::info('Points earned: ' . $points);

            return DB::transaction(function () use ($data, $transactedAt, $points) {
                $transaction = Transaction::create([
                    'user_id' => $data['user_id'],
                    'amount' => (int)$data['amount'],
                    'description' => $data['description'],
                    'transacted_at' => $transactedAt,
                ]);

                Point::create([
                    'transaction_id' => $transaction->id,
                    'points' => $points,
                ]);

                return response()->json([
                    'user_id' => $transaction->user_id,
                    'amount' => $transaction->amount,
                    'points' => $points,
                    'description' => $transaction->description,
                    'transacted_at' => $transaction->transacted_at->toISOString(),
                ], 201);
            });
        } catch (Exception $e) {
            Log::error('Error processing transaction: ' . $e->getMessage());
            return response()->json(['message' => $e->getMessage()], 500);
        }
    }

    public function index(Request $request)
    {
        try {
            $validated = $request->validate([
                'start_date' => 'nullable|date_format:Y-m-d',
                'end_date' => 'nullable|date_format:Y-m-d',
                'per_page' => 'nullable|integer|min:1|max:100',
            ]);

            $perPage = (int)($validated['per_page'] ?? 20);

            $q = Transaction::query()->with('point');

            if ($request->filled('start_date')) {
                $q->whereDate('transacted_at', '>=', Carbon::parse($request->start_date)->startOfDay());
            }
            if ($request->filled('end_date')) {
                $q->whereDate('transacted_at', '<=', Carbon::parse($request->end_date)->endOfDay());
            }

            $transactionWithPoints = $q->orderByDesc('transacted_at')->paginate($perPage);

            return response()->json([
                'data' => $transactionWithPoints->getCollection()->map(function ($transaction) {
                    return [
                        'user_id' => $transaction->user_id,
                        'amount' => (int)$transaction->amount,
                        'points' => (int)optional($transaction->point)->points,
                        'description' => $transaction->description,
                        'transacted_at' => $transaction->transacted_at->toISOString(),
                    ];
                })->values(),
                'pagination' => [
                    'current_page' => $transactionWithPoints->currentPage(),
                    'total_pages' => $transactionWithPoints->lastPage(),
                ],
            ]);
        } catch (Exception $e) {
            Log::error('Error fetching transactions: ' . $e->getMessage());
            return response()->json(['message' => 'Error fetching transactions'], 500);
        }
    }

    public function seed(Request $request)
    {
        try {
            $count = (int)($request->input('count', 1000));
            $userId = 1;
            $now = Carbon::now();
            $start = $now->copy()->subYear();

            DB::transaction(function () use ($count, $userId, $start, $now) {
                for ($i = 0; $i < $count; $i++) {
                    $amount = random_int(10_000, 500_000);
                    $dateRandomOneYear = Carbon::createFromTimestamp(random_int($start->timestamp, $now->timestamp));

                    $trx = Transaction::create([
                        'user_id' => $userId,
                        'amount' => $amount,
                        'description' => 'Dummy transaction #' . ($i + 1),
                        'transacted_at' => $dateRandomOneYear,
                    ]);

                    Point::create([
                        'transaction_id' => $trx->id,
                        'points' => intdiv($amount, 1000),
                    ]);
                }
            });

            return response()->json(['message' => "Seeded {$count} transactions"], 201);
        } catch (Exception $e) {
            Log::error('Error seeding transactions: ' . $e->getMessage());
            return response()->json(['message' => 'Error seeding transactions'], 500);
        }
    }
}