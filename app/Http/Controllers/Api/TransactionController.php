<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TransactionResource;
use App\Models\Accounting\Transaction;
use App\Models\Accounting\Account;
use App\Models\Banking\BankAccount;
use App\Models\Common\Contact;
use App\Enums\Accounting\TransactionType;
use App\Enums\Accounting\PaymentMethod;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class TransactionController extends Controller
{
    /**
     * Display a listing of transactions.
     */
    public function index(Request $request): JsonResponse
    {
        $query = Transaction::with([
            'account',
            'bankAccount',
            'contact',
            'journalEntries',
            'transactionable',
            'payeeable'
        ]);

        // Filter by company (if using multi-tenancy)
        if (Auth::check() && Auth::user()->currentCompany) {
            $query->where('company_id', Auth::user()->currentCompany->id);
        }

        // Filter by transaction type
        if ($request->has('type')) {
            $query->where('type', $request->get('type'));
        }

        // Filter by payment method
        if ($request->has('payment_method')) {
            $query->where('payment_method', $request->get('payment_method'));
        }

        // Filter by account
        if ($request->has('account_id')) {
            $query->where('account_id', $request->get('account_id'));
        }

        // Filter by bank account
        if ($request->has('bank_account_id')) {
            $query->where('bank_account_id', $request->get('bank_account_id'));
        }

        // Filter by contact
        if ($request->has('contact_id')) {
            $query->where('contact_id', $request->get('contact_id'));
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('posted_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('posted_at', '<=', $request->get('date_to'));
        }

        // Filter by amount range
        if ($request->has('amount_min')) {
            $query->where('amount', '>=', $request->get('amount_min'));
        }

        if ($request->has('amount_max')) {
            $query->where('amount', '<=', $request->get('amount_max'));
        }

        // Filter by reviewed status
        if ($request->has('reviewed')) {
            $query->where('reviewed', $request->boolean('reviewed'));
        }

        // Filter by pending status
        if ($request->has('pending')) {
            $query->where('pending', $request->boolean('pending'));
        }

        // Search by description or reference
        if ($request->has('search')) {
            $search = $request->get('search');
            $query->where(function ($q) use ($search) {
                $q->where('description', 'like', "%{$search}%")
                  ->orWhere('reference', 'like', "%{$search}%")
                  ->orWhere('notes', 'like', "%{$search}%");
            });
        }

        // Sorting
        $sortBy = $request->get('sort_by', 'posted_at');
        $sortOrder = $request->get('sort_order', 'desc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = $request->get('per_page', 15);
        $transactions = $query->paginate($perPage);

        return response()->json([
            'data' => TransactionResource::collection($transactions->items()),
            'pagination' => [
                'current_page' => $transactions->currentPage(),
                'last_page' => $transactions->lastPage(),
                'per_page' => $transactions->perPage(),
                'total' => $transactions->total(),
                'from' => $transactions->firstItem(),
                'to' => $transactions->lastItem(),
            ]
        ]);
    }

    /**
     * Store a newly created transaction.
     */
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'required|exists:accounts,id',
            'bank_account_id' => 'required|exists:bank_accounts,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'type' => ['required', Rule::enum(TransactionType::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'is_payment' => 'boolean',
            'description' => 'required|string|max:255',
            'notes' => 'nullable|string',
            'reference' => 'nullable|string|max:255',
            'amount' => 'required|numeric|min:0',
            'pending' => 'boolean',
            'reviewed' => 'boolean',
            'posted_at' => 'required|date',
            'meta' => 'nullable|array',
        ]);

        // Multiply amount by 100 to store as integer (cents)
        if (isset($validated['amount'])) {
            $validated['amount'] = (int) round($validated['amount'] * 100);
        }

        // Add company_id if using multi-tenancy
        if (Auth::check() && Auth::user()->currentCompany) {
            $validated['company_id'] = Auth::user()->currentCompany->id;
        }

        $transaction = Transaction::create($validated);

        return response()->json([
            'data' => new TransactionResource($transaction->load([
                'account',
                'bankAccount',
                'contact',
                'journalEntries'
            ]))
        ], Response::HTTP_CREATED);
    }

    /**
     * Display the specified transaction.
     */
    public function show(Transaction $transaction): JsonResponse
    {
        return response()->json([
            'data' => new TransactionResource($transaction->load([
                'account',
                'bankAccount',
                'contact',
                'journalEntries',
                'transactionable',
                'payeeable'
            ]))
        ]);
    }

    /**
     * Update the specified transaction.
     */
    public function update(Request $request, Transaction $transaction): JsonResponse
    {
        $validated = $request->validate([
            'account_id' => 'sometimes|exists:accounts,id',
            'bank_account_id' => 'sometimes|exists:bank_accounts,id',
            'contact_id' => 'nullable|exists:contacts,id',
            'type' => ['sometimes', Rule::enum(TransactionType::class)],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            'is_payment' => 'boolean',
            'description' => 'sometimes|string|max:255',
            'notes' => 'nullable|string',
            'reference' => 'nullable|string|max:255',
            'amount' => 'sometimes|numeric|min:0',
            'pending' => 'boolean',
            'reviewed' => 'boolean',
            'posted_at' => 'sometimes|date',
            'meta' => 'nullable|array',
        ]);

        // Multiply amount by 100 to store as integer (cents) if present
        if (isset($validated['amount'])) {
            $validated['amount'] = (int) round($validated['amount'] * 100);
        }

        $transaction->update($validated);

        return response()->json([
            'data' => new TransactionResource($transaction->load([
                'account',
                'bankAccount',
                'contact',
                'journalEntries'
            ]))
        ]);
    }

    /**
     * Remove the specified transaction.
     */
    public function destroy(Transaction $transaction): JsonResponse
    {
        $transaction->delete();

        return response()->json(null, Response::HTTP_NO_CONTENT);
    }

    /**
     * Get transaction options for forms.
     */
    public function options(Request $request): JsonResponse
    {
        $type = $request->get('type');
        
        $options = [
            'accounts' => $type ? Transaction::getTransactionAccountOptions(TransactionType::from($type)) : Transaction::getChartAccountOptions(),
            'bank_accounts' => Transaction::getBankAccountOptions(),
            'contacts' => Contact::select('id', 'name')->get()->pluck('name', 'id'),
            'transaction_types' => collect(TransactionType::cases())->mapWithKeys(fn($type) => [$type->value => $type->getLabel()]),
            'payment_methods' => collect(PaymentMethod::cases())->mapWithKeys(fn($method) => [$method->value => $method->getLabel()]),
        ];

        return response()->json(['data' => $options]);
    }

    /**
     * Get transaction statistics.
     */
    public function statistics(Request $request): JsonResponse
    {
        $query = Transaction::query();

        // Filter by company if using multi-tenancy
        if (Auth::check() && Auth::user()->currentCompany) {
            $query->where('company_id', Auth::user()->currentCompany->id);
        }

        // Filter by date range
        if ($request->has('date_from')) {
            $query->where('posted_at', '>=', $request->get('date_from'));
        }

        if ($request->has('date_to')) {
            $query->where('posted_at', '<=', $request->get('date_to'));
        }

        $statistics = [
            'total_transactions' => $query->count(),
            'total_amount' => $query->sum('amount'),
            'deposits' => $query->clone()->where('type', TransactionType::Deposit)->sum('amount'),
            'withdrawals' => $query->clone()->where('type', TransactionType::Withdrawal)->sum('amount'),
            'pending_transactions' => $query->clone()->where('pending', true)->count(),
            'unreviewed_transactions' => $query->clone()->where('reviewed', false)->count(),
        ];

        return response()->json(['data' => $statistics]);
    }
}
