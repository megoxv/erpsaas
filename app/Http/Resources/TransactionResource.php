<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TransactionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'company_id' => $this->company_id,
            'account_id' => $this->account_id,
            'bank_account_id' => $this->bank_account_id,
            'contact_id' => $this->contact_id,
            'plaid_transaction_id' => $this->plaid_transaction_id,
            'type' => [
                'value' => $this->type->value,
                'label' => $this->type->getLabel(),
            ],
            'payment_channel' => $this->payment_channel,
            'payment_method' => $this->payment_method ? [
                'value' => $this->payment_method->value,
                'label' => $this->payment_method->getLabel(),
            ] : null,
            'is_payment' => $this->is_payment,
            'description' => $this->description,
            'notes' => $this->notes,
            'reference' => $this->reference,
            'amount' => $this->amount,
            'pending' => $this->pending,
            'reviewed' => $this->reviewed,
            'posted_at' => $this->posted_at?->format('Y-m-d'),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'meta' => $this->meta,
            
            // Relationships
            'account' => $this->whenLoaded('account', function () {
                return [
                    'id' => $this->account->id,
                    'name' => $this->account->name,
                    'type' => $this->account->type,
                    'category' => $this->account->category,
                ];
            }),
            
            'bank_account' => $this->whenLoaded('bankAccount', function () {
                return [
                    'id' => $this->bankAccount->id,
                    'name' => $this->bankAccount->account->name,
                    'account_number' => $this->bankAccount->account_number,
                ];
            }),
            
            'contact' => $this->whenLoaded('contact', function () {
                return [
                    'id' => $this->contact->id,
                    'name' => $this->contact->name,
                    'type' => $this->contact->type,
                ];
            }),
            
            'journal_entries' => $this->whenLoaded('journalEntries', function () {
                return $this->journalEntries->map(function ($entry) {
                    return [
                        'id' => $entry->id,
                        'account_id' => $entry->account_id,
                        'account_name' => $entry->account->name,
                        'debit' => $entry->debit,
                        'credit' => $entry->credit,
                        'description' => $entry->description,
                    ];
                });
            }),
            
            'transactionable' => $this->whenLoaded('transactionable', function () {
                return [
                    'id' => $this->transactionable->id,
                    'type' => $this->transactionable_type,
                    'name' => $this->transactionable->name ?? $this->transactionable->title ?? 'N/A',
                ];
            }),
            
            'payeeable' => $this->whenLoaded('payeeable', function () {
                return [
                    'id' => $this->payeeable->id,
                    'type' => $this->payeeable_type,
                    'name' => $this->payeeable->name,
                ];
            }),
        ];
    }
}

