<?php
if (!defined("B_PROLOG_INCLUDED") || B_PROLOG_INCLUDED !== true) die();
use Bitrix\Main\Localization\Loc;
?>
<tr>
	<td align="right" width="40%" valign="top"><span
				class="adm-required-field"><?= Loc::getMessage("CRMECBA_ENTITY_ID_TEXT") ?>:</span></td>
	<td width="60%">
		<?= CBPDocument::ShowParameterField("text", 'entityId', $arCurrentValues['entityId'], ['rows' => 1, 'cols' => 50]) ?>
	</td>
</tr>
<tr>
	<td align="right" width="40%" valign="top"><span
				class="adm-required-field"><?= Loc::getMessage("CRMECBA_ENTITY_TYPE_TEXT") ?>:</span></td>
	<td width="60%">
		<select name="<?= htmlspecialcharsbx("entityType"); ?>">
			<option value=""></option>
			<?php
			foreach ($crmTypes as $typeId => $typeName)
			{
				$selected = $arCurrentValues['entityType'] == $typeId;
				?>
					<option value="<?= $typeId; ?>" <?= ($selected ? "selected" : false); ?>><?= $typeName; ?></option>
				<?php
			}
			?>
		</select>
	</td>
</tr>