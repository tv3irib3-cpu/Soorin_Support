<?php

namespace App\Observers;

use App\Models\Contract;
use Hekmatinasser\Verta\Verta;

class ContractObserver
{
    public function creating(Contract $contract): void
    {
        if (blank($contract->number)) {
            $contract->number = $this->nextNumber();
        }
    }

    /** شماره قرارداد به قالب C-1405-0001 — سال شمسی + شمارنده. */
    private function nextNumber(): string
    {
        $year = Verta::now()->format('Y');

        $last = Contract::where('number', 'like', "C-{$year}-%")
            ->orderByDesc('id')
            ->value('number');

        $sequence = $last ? ((int) substr($last, -4)) + 1 : 1;

        return sprintf('C-%s-%04d', $year, $sequence);
    }
}
