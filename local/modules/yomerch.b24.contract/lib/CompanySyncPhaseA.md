# CompanySync Phase A Mapping

- `CompanySyncReadService::loadCompanySnapshot()` reads CRM company row + UF + requisites + multifields.
- `CompanySyncNormalizeService::normalizeForOutbound()` normalizes site payload primitives (для `site_element_id` сначала канонический UF `company.site_element_id`, при пустом — legacy `company.site_element_id_legacy_alias`).
- `CompanySyncPolicyService::validateHeadHoldingTransition()` validates head/holding policy before mutation.

## Shadow compare note

Phase A keeps the legacy mutation flow in `\OnlineService\Sync\ToSite\CompanySync` intact.
New services are executed in active mode and traced via:

- `CompanySync::phaseA_read_snapshot`
- `CompanySync::phaseA_normalize_shadow`

These trace markers are used for shadow compare against legacy outbound payload behavior during cutover.
