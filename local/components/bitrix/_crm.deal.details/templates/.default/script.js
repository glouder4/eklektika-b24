;(function()
{
	var MLDETAILS_URL = "/crm/ml/deal/#id#/detail";

	BX.Crm.DealDetails = function(params)
	{
		if(!params)
		{
			params = {};
		}
		this.dealId = params.entityId;
		this.mlInstalled = params.mlInstalled;
		this.scoringEnabled = params.scoringEnabled;

		this.init();
	};
	BX.Crm.DealDetails.prototype = {
		init: function()
		{
			BX.addCustomEvent(window, "BX.Crm.EntityEditorSection:onLayout", this.onEntityEditorLayout.bind(this));
		},
		onEntityEditorLayout: function(editorSection, e)
		{
			if (e.id === "main" && this.mlInstalled)
			{
				e.customNodes.push(BX.create("div", {
					props: {className: "crm-entity-widget-scoring"},
					events: {
						click: this.onScoringButtonClick.bind(this)
					},
					children: [
						BX.create("div", {
							props: {className: "crm-entity-widget-scoring-icon"}
						}),
						BX.create("div", {
							props: {className: "crm-entity-widget-scoring-text"},
							text: BX.message("CRM_DEAL_DETAIL_SCORING_TITLE")
						})
					]
				}))
			}
		},
		onScoringButtonClick: function(e)
		{
			if(this.scoringEnabled)
			{
				var url = MLDETAILS_URL.replace("#id#", this.dealId);
				BX.SidePanel.Instance.open(url, {
					cacheable: false,
					width: 840
				});
			}
			else
			{
				B24.licenseInfoPopup.show(
					"crm_scoring",
					BX.message("CRM_SCORING_LICENSE_TITLE"),
					"<span>" + BX.message("CRM_SCORING_LICENSE_TEXT") + "</span>"
				);
			}
		}
	}
})();