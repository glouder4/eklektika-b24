<?php

namespace OnlineService\Sync\Contract;

class CompanySyncPolicyService
{
    /**
     * UF «головная по порталу» (177…) и UF «холдинг» (175…) взаимоисключающие: нельзя быть головной и одновременно привязанной к холдингу.
     *
     * @param array<string, mixed> $ufValues результат GetUserFields CRM_COMPANY (может быть [] при Add)
     * @param array<string, mixed> $arFields
     */
    public function validatePortalHeadFlagVsHoldingExclusive(
        array $ufValues,
        array $arFields,
        string $ufHeadPortal,
        string $ufHolding
    ): array {
        $wasPortalHead = $this->isTruthy($ufValues[$ufHeadPortal]['VALUE'] ?? null);
        $willPortalHead = \array_key_exists($ufHeadPortal, $arFields)
            ? $this->isTruthy($arFields[$ufHeadPortal] ?? null)
            : $wasPortalHead;

        $wasHolding = !$this->holdingUfValueIsEmpty($ufValues[$ufHolding]['VALUE'] ?? null);
        $willHolding = \array_key_exists($ufHolding, $arFields)
            ? !$this->holdingUfValueIsEmpty($arFields[$ufHolding] ?? null)
            : $wasHolding;

        if ($willPortalHead && $willHolding) {
            return [
                'ok' => false,
                'message' => 'Компания не может одновременно быть отмечена как головная (поле портала) и привязана к холдингу. Снимите одно из значений.',
            ];
        }

        return ['ok' => true, 'message' => ''];
    }

    /**
     * @param mixed $raw
     */
    private function holdingUfValueIsEmpty($raw): bool
    {
        if ($raw === null || $raw === false || $raw === '') {
            return true;
        }
        if (\is_array($raw) && \array_key_exists('VALUE', $raw)) {
            $raw = $raw['VALUE'];
        }
        if ($raw === null || $raw === false || $raw === '') {
            return true;
        }
        if ($raw === 0 || $raw === '0') {
            return true;
        }

        return false;
    }

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
