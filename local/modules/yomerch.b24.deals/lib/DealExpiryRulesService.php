<?php

namespace OnlineService;

use Bitrix\Crm\DealTable;
use Bitrix\Main\Type\DateTime;

class DealExpiryRulesService
{
    public const RESERVE_EXPIRY_FIELD = 'UF_CRM_1713789046772';
    public const SAMPLE_EXPIRY_FIELD = 'UF_CRM_1754666329972';
    public const RESERVE_STAGES = ['UC_JSQC9O', '6'];
    public const SAMPLE_STAGES = ['5', '7'];

    public function getExpiredDeals(): array
    {
        $now = new DateTime();

        $reserveExpired = DealTable::getList([
            'select' => ['ID', 'TITLE', 'ASSIGNED_BY_ID', 'STAGE_ID', self::RESERVE_EXPIRY_FIELD, self::SAMPLE_EXPIRY_FIELD],
            'filter' => [
                '=STAGE_ID' => self::RESERVE_STAGES,
                '<' . self::RESERVE_EXPIRY_FIELD => $now,
                '!' . self::RESERVE_EXPIRY_FIELD => false,
            ],
        ])->fetchAll();

        $sampleExpired = DealTable::getList([
            'select' => ['ID', 'TITLE', 'ASSIGNED_BY_ID', 'STAGE_ID', self::RESERVE_EXPIRY_FIELD, self::SAMPLE_EXPIRY_FIELD],
            'filter' => [
                '=STAGE_ID' => self::SAMPLE_STAGES,
                '<' . self::SAMPLE_EXPIRY_FIELD => $now,
                '!' . self::SAMPLE_EXPIRY_FIELD => false,
            ],
        ])->fetchAll();

        return [
            'reserve_expired' => $reserveExpired,
            'sample_expired' => $sampleExpired,
        ];
    }

    public function compareLegacyParity(array $legacyDecision, array $newDecision): array
    {
        $legacy = $this->normalizeDecision($legacyDecision);
        $current = $this->normalizeDecision($newDecision);

        return [
            'reserve_match' => $legacy['reserve'] === $current['reserve'],
            'sample_match' => $legacy['sample'] === $current['sample'],
            'legacy' => $legacy,
            'current' => $current,
        ];
    }

    private function normalizeDecision(array $decision): array
    {
        $reserve = array_map('intval', array_column((array)($decision['reserve_expired'] ?? []), 'ID'));
        $sample = array_map('intval', array_column((array)($decision['sample_expired'] ?? []), 'ID'));
        $reserve = array_values(array_unique(array_filter($reserve, static function (int $id): bool {
            return $id > 0;
        })));
        $sample = array_values(array_unique(array_filter($sample, static function (int $id): bool {
            return $id > 0;
        })));
        sort($reserve, SORT_NUMERIC);
        sort($sample, SORT_NUMERIC);

        return [
            'reserve' => $reserve,
            'sample' => $sample,
        ];
    }
}
