<?php

namespace OnlineService\Sync\Contract;

class CompanySyncPolicyService
{
    public function validateHeadHoldingTransition(array $ufValues, array $arFields, string $ufCompanyIsHead, string $ufCompanyHolding): array
    {
        $wasHeadCompany = $this->isTruthy($ufValues[$ufCompanyIsHead]['VALUE'] ?? null);
        $willBeHeadCompany = array_key_exists($ufCompanyIsHead, $arFields)
            ? $this->isTruthy($arFields[$ufCompanyIsHead] ?? null)
            : $wasHeadCompany;

        if ($willBeHeadCompany && !empty($arFields[$ufCompanyHolding] ?? null)) {
            return [
                'ok' => false,
                'message' => 'Компания не может быть головной И входить в другой холдинг',
                'was_head' => $wasHeadCompany,
                'will_head' => $willBeHeadCompany,
            ];
        }

        return [
            'ok' => true,
            'message' => '',
            'was_head' => $wasHeadCompany,
            'will_head' => $willBeHeadCompany,
        ];
    }

    private function isTruthy($value): bool
    {
        return $value === 'Y' || $value === true || $value === 1 || $value === '1';
    }
}
