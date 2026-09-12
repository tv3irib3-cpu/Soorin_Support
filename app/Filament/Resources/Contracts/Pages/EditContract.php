<?php

namespace App\Filament\Resources\Contracts\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditContract extends EditRecord
{
    protected static string $resource = ContractResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * اگر تاریخِ پایانِ قرارداد گذشته ولی وضعیتِ ذخیره‌شده هنوز «جاری» است (چون
     * زمان‌بندِ شبانه هنوز اجرا نشده)، در فرمِ ویرایش «منقضی» نمایش داده می‌شود —
     * هماهنگ با فهرست. ذخیره هم همین وضعیت را ثابت می‌کند.
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['status'] = $this->getRecord()->effectiveStatus();

        return $data;
    }
}
